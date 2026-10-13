<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Correspondence;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageCorrespondenceService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PorteriaDoorService $doors,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, Correspondence>
     */
    public function register(User $actor, array $input, ?UploadedFile $photo = null): Collection
    {
        $clientId = (int) $this->tenant->clientId();
        $door = $this->doors->operatingOrFirst(request());
        if ($door === null) {
            throw ValidationException::withMessages(['structure_id' => 'No hay puerta activa para registrar la correspondencia.']);
        }

        $structureId = (int) ($input['structure_id'] ?? 0);
        $memberId = (int) ($input['structure_member_id'] ?? 0);
        $recipientText = trim((string) ($input['recipient_text'] ?? ''));

        if ($structureId < 1 && $recipientText === '') {
            throw ValidationException::withMessages(['structure_id' => 'Elige la unidad o escribe el destinatario.']);
        }
        if ($structureId > 0 && ! Structure::query()->where('client_id', $clientId)->whereKey($structureId)->exists()) {
            throw ValidationException::withMessages(['structure_id' => 'Unidad inválida.']);
        }
        if ($memberId > 0) {
            $ok = StructureMember::query()->where('client_id', $clientId)->whereKey($memberId)
                ->when($structureId > 0, fn ($q) => $q->where('structure_id', $structureId))
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['structure_member_id' => 'La persona no pertenece a esa unidad.']);
            }
        }

        $photoPath = $photo?->store('porteria/correspondencia', 'public');
        $quantity = max(1, min(20, (int) ($input['quantity'] ?? 1)));

        return DB::transaction(function () use ($actor, $input, $door, $structureId, $memberId, $recipientText, $photoPath, $quantity): Collection {
            $created = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $item = Correspondence::query()->create([
                    'location_id' => $door->id,
                    'structure_id' => $structureId > 0 ? $structureId : null,
                    'structure_member_id' => $memberId > 0 ? $memberId : null,
                    'recipient_text' => $recipientText !== '' ? $recipientText : null,
                    'carrier' => $this->nullable($input['carrier'] ?? null),
                    'courier_guide' => $this->nullable($input['courier_guide'] ?? null),
                    'package_type' => (string) $input['package_type'],
                    'notes' => $this->nullable($input['notes'] ?? null),
                    'photo_path' => $photoPath,
                    'received_at' => now(),
                    'received_by' => $actor->id,
                    'status' => 'pending',
                ]);
                $this->audit->record($item, 'correspondence.create', null, [
                    'structure_id' => $item->structure_id,
                    'structure_member_id' => $item->structure_member_id,
                    'package_type' => $item->package_type,
                    'carrier' => $item->carrier,
                ]);
                $created->push($item);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deliver(User $actor, Correspondence $item, array $input, ?UploadedFile $photo = null): void
    {
        if ($item->status !== 'pending') {
            throw ValidationException::withMessages(['delivered_to_name' => 'Este paquete ya no está pendiente.']);
        }

        $receiver = $this->receiver($input, $item->structure_id);
        $photoPath = $photo?->store('porteria/correspondencia', 'public');
        $this->markDelivered($actor, $item, $receiver, $photoPath);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deliverUnit(User $actor, int $structureId, array $input, ?UploadedFile $photo = null): int
    {
        $items = Correspondence::query()->where('status', 'pending')->where('structure_id', $structureId)->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['structure_id' => 'Esa unidad no tiene paquetes pendientes.']);
        }

        $receiver = $this->receiver($input, $structureId);
        $photoPath = $photo?->store('porteria/correspondencia', 'public');

        DB::transaction(function () use ($actor, $items, $receiver, $photoPath): void {
            foreach ($items as $item) {
                $this->markDelivered($actor, $item, $receiver, $photoPath);
            }
        });

        return $items->count();
    }

    public function void(User $actor, Correspondence $item, string $reason): void
    {
        if ($item->status !== 'pending') {
            throw ValidationException::withMessages(['void_reason' => 'Solo se anulan paquetes pendientes.']);
        }

        $item->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => $reason,
        ]);
        $this->audit->record($item, 'correspondence.void', ['status' => 'pending'], ['status' => 'voided', 'reason' => $reason]);
    }

    /**
     * @param  array{member_id: ?int, name: ?string, document: ?string}  $receiver
     */
    private function markDelivered(User $actor, Correspondence $item, array $receiver, ?string $photoPath): void
    {
        $item->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'delivered_by' => $actor->id,
            'delivered_to_member_id' => $receiver['member_id'],
            'delivered_to_name' => $receiver['name'],
            'delivered_to_document' => $receiver['document'],
            'delivery_photo_path' => $photoPath,
        ]);
        $this->audit->record($item, 'correspondence.deliver', ['status' => 'pending'], [
            'status' => 'delivered',
            'delivered_to_member_id' => $receiver['member_id'],
            'delivered_to_name' => $receiver['name'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{member_id: ?int, name: ?string, document: ?string}
     */
    private function receiver(array $input, ?int $structureId): array
    {
        $clientId = (int) $this->tenant->clientId();
        $memberId = (int) ($input['delivered_to_member_id'] ?? 0);
        $name = $this->nullable($input['delivered_to_name'] ?? null);
        $document = $this->nullable($input['delivered_to_document'] ?? null);

        if ($memberId > 0) {
            $member = StructureMember::query()->where('client_id', $clientId)->whereKey($memberId)
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
                ->first();
            if ($member === null) {
                throw ValidationException::withMessages(['delivered_to_member_id' => 'La persona no pertenece a la unidad.']);
            }

            return ['member_id' => (int) $member->id, 'name' => null, 'document' => null];
        }

        if ($name === null) {
            throw ValidationException::withMessages(['delivered_to_name' => 'Indica quién recibe el paquete.']);
        }

        return ['member_id' => null, 'name' => $name, 'document' => $document];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
