<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationType;
use App\Enums\VisitorCategory;
use App\Models\AccessLog;
use App\Models\AuthorizationGuest;
use App\Models\Installation;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorPreAuthorization;
use App\Services\Access\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageAuthorizationService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     type?: string, structure_id?: int|null, destination_text?: string|null,
     *     member_id?: int|null, authorized_text?: string|null,
     *     valid_from: string, valid_until?: string|null, multi_entry?: bool|null,
     *     vehicle_plate?: string|null, notes?: string|null, origin?: string,
     *     guests: list<array{document_type?: string|null, document_number?: string|null, first_name: string, last_name?: string|null, phone?: string|null}>
     * }  $data
     */
    public function create(?User $actor, array $data): VisitorPreAuthorization
    {
        $clientId = (int) $this->tenant->clientId();
        $type = AuthorizationType::tryFrom((string) ($data['type'] ?? 'visit')) ?? AuthorizationType::Visit;
        $structureId = (int) ($data['structure_id'] ?? 0) ?: null;
        $destinationText = $this->clean($data['destination_text'] ?? null);
        $memberId = (int) ($data['member_id'] ?? 0) ?: null;
        $authorizedText = $this->clean($data['authorized_text'] ?? null);

        if ($structureId === null && $destinationText === null) {
            throw ValidationException::withMessages(['structure_id' => 'Elige la unidad o escribe la zona de destino.']);
        }

        $allowed = $this->tenant->installationIds();
        if ($structureId !== null) {
            $structure = Structure::query()->where('client_id', $clientId)->whereKey($structureId)->first();
            if ($structure === null || ($allowed !== null && ! in_array((int) $structure->installation_id, $allowed, true))) {
                throw ValidationException::withMessages(['structure_id' => 'No puedes autorizar para esa unidad.']);
            }
            $installationId = $structure->installation_id !== null ? (int) $structure->installation_id : null;
        } else {
            $installationId = $this->zoneInstallation($clientId, $allowed, (int) ($data['installation_id'] ?? 0) ?: null);
        }

        if ($memberId !== null) {
            $ok = StructureMember::query()->where('client_id', $clientId)->whereKey($memberId)
                ->when($structureId !== null, fn ($q) => $q->where('structure_id', $structureId))
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['member_id' => 'Quien autoriza debe pertenecer a la unidad.']);
            }
        }

        $from = Carbon::parse($data['valid_from'])->startOfDay();
        $until = Carbon::parse($data['valid_until'] ?? $data['valid_from'])->endOfDay();
        if ($until->lt($from)) {
            throw ValidationException::withMessages(['valid_until' => 'La fecha final no puede ser anterior a la inicial.']);
        }
        if ($until->isPast()) {
            throw ValidationException::withMessages(['valid_until' => 'La vigencia ya terminó.']);
        }

        $timeFrom = $this->clean($data['entry_time_from'] ?? null);
        $timeUntil = $this->clean($data['entry_time_until'] ?? null);
        if (($timeFrom === null) !== ($timeUntil === null)) {
            throw ValidationException::withMessages(['entry_time_from' => 'Indica la hora de inicio y la hora final.']);
        }
        if ($timeFrom !== null && $timeUntil <= $timeFrom) {
            throw ValidationException::withMessages(['entry_time_until' => 'La hora final debe ser posterior a la inicial.']);
        }

        $guests = $this->normalizeGuests($data['guests'] ?? []);
        if ($guests === []) {
            throw ValidationException::withMessages(['guests' => 'Agrega al menos una persona.']);
        }

        return DB::transaction(function () use ($actor, $clientId, $installationId, $type, $structureId, $destinationText, $memberId, $authorizedText, $from, $until, $timeFrom, $timeUntil, $guests, $data): VisitorPreAuthorization {
            $first = $guests[0];
            $authorization = VisitorPreAuthorization::query()->create([
                'client_id' => $clientId,
                'installation_id' => $installationId,
                'structure_id' => $structureId,
                'type' => $type,
                'origin' => (string) ($data['origin'] ?? 'admin'),
                'destination_text' => $structureId === null ? $destinationText : null,
                'member_id' => $memberId,
                'authorized_text' => $memberId === null ? ($authorizedText ?? 'Administración') : null,
                'visitor_name' => count($guests) > 1
                    ? $type->label().' · '.count($guests).' personas'
                    : trim($first['first_name'].' '.($first['last_name'] ?? '')),
                'visitor_document' => count($guests) === 1 ? $first['document_number'] : null,
                'visitor_category' => $type === AuthorizationType::Work ? VisitorCategory::Contractor : VisitorCategory::Visitor,
                'valid_for_date' => $from->toDateString(),
                'valid_from' => $from,
                'valid_until' => $until,
                'entry_time_from' => $timeFrom,
                'entry_time_until' => $timeUntil,
                'multi_entry' => array_key_exists('multi_entry', $data) && $data['multi_entry'] !== null
                    ? (bool) $data['multi_entry']
                    : $type->defaultMultiEntry(),
                'vehicle_plate' => ($plate = $this->clean($data['vehicle_plate'] ?? null)) ? strtoupper($plate) : null,
                'notes' => $this->clean($data['notes'] ?? null),
                'status' => AuthorizationStatus::Pending,
                'qr_auth_token' => Str::random(40),
                'created_by' => $actor?->id,
            ]);

            foreach ($guests as $guest) {
                $authorization->guests()->create([
                    'client_id' => $clientId,
                    'visitor_id' => $this->resolveVisitor($clientId, $guest, $type)?->id,
                    'document_type' => $guest['document_type'],
                    'document_number' => $guest['document_number'],
                    'first_name' => $guest['first_name'],
                    'last_name' => $guest['last_name'],
                    'phone' => $guest['phone'],
                ]);
            }

            $this->audit->record($authorization, 'authorization.create', null, [
                'type' => $type->value,
                'structure_id' => $structureId,
                'guests' => count($guests),
                'valid_from' => $from->toDateTimeString(),
                'valid_until' => $until->toDateTimeString(),
                'entry_window' => $timeFrom !== null ? "{$timeFrom}-{$timeUntil}" : null,
            ]);

            return $authorization;
        });
    }

    public function cancel(User $actor, VisitorPreAuthorization $authorization): void
    {
        if (in_array($authorization->state(), [VisitorPreAuthorization::STATE_CANCELLED, VisitorPreAuthorization::STATE_EXPIRED], true)) {
            throw ValidationException::withMessages(['authorization' => 'La autorización ya no está vigente.']);
        }

        $authorization->update([
            'status' => AuthorizationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
        ]);
        $this->audit->record($authorization, 'authorization.cancel', null, ['status' => 'cancelled']);
    }

    /** Valida que el invitado pueda entrar con esta autorización (llamar antes de crear el ingreso). */
    public function assertCanEnter(AuthorizationGuest $guest, Visitor $visitor): void
    {
        if ((int) $guest->visitor_id !== (int) $visitor->id || ! $guest->canEnter()) {
            throw ValidationException::withMessages([
                'authorization_guest_id' => 'La autorización ya no está vigente para esta persona. Usa el ingreso normal.',
            ]);
        }
    }

    public function registerEntry(AuthorizationGuest $guest, AccessLog $log): void
    {
        $guest->update([
            'entries_count' => $guest->entries_count + 1,
            'first_entry_at' => $guest->first_entry_at ?? now(),
            'last_entry_at' => now(),
        ]);

        $authorization = $guest->authorization;
        if (! $authorization->multi_entry && ! $authorization->guests()->where('entries_count', 0)->exists()) {
            $authorization->update(['status' => AuthorizationStatus::Processed]);
        }

        $this->audit->record($authorization, 'authorization.use', null, [
            'guest_id' => $guest->id,
            'access_log_id' => $log->id,
        ]);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{document_type: string, document_number: ?string, first_name: string, last_name: ?string, phone: ?string}>
     */
    private function normalizeGuests(array $rows): array
    {
        $guests = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $first = $this->clean($row['first_name'] ?? null);
            $document = $this->clean($row['document_number'] ?? null);
            if ($first === null && $document === null) {
                continue;
            }
            if ($first === null) {
                throw ValidationException::withMessages(["guests.{$index}.first_name" => 'Cada persona necesita su nombre.']);
            }
            if ($document !== null) {
                $document = preg_replace('/[\s.]/', '', $document) ?? $document;
                if (isset($seen[$document])) {
                    throw ValidationException::withMessages(["guests.{$index}.document_number" => "El documento {$document} está repetido."]);
                }
                $seen[$document] = true;
            }
            $guests[] = [
                'document_type' => $this->clean($row['document_type'] ?? null) ?? 'CC',
                'document_number' => $document,
                'first_name' => $first,
                'last_name' => $this->clean($row['last_name'] ?? null),
                'phone' => $this->clean($row['phone'] ?? null),
            ];
        }

        return $guests;
    }

    /**
     * @param  array{document_type: string, document_number: ?string, first_name: string, last_name: ?string, phone: ?string}  $guest
     */
    private function resolveVisitor(int $clientId, array $guest, AuthorizationType $type): ?Visitor
    {
        if ($guest['document_number'] === null) {
            return null;
        }

        $visitor = Visitor::withTrashed()
            ->where('client_id', $clientId)
            ->where('document_type', $guest['document_type'])
            ->where('document_number', $guest['document_number'])
            ->first();
        if ($visitor !== null) {
            if ($visitor->trashed()) {
                $visitor->restore();
            }
            if ($guest['phone'] !== null && empty($visitor->phone)) {
                $visitor->update(['phone' => $guest['phone']]);
            }

            return $visitor;
        }

        return Visitor::query()->create([
            'client_id' => $clientId,
            'document_type' => $guest['document_type'],
            'document_number' => $guest['document_number'],
            'first_name' => $guest['first_name'],
            'last_name' => $guest['last_name'] ?? '',
            'phone' => $guest['phone'],
            'visitor_type' => $type->visitorType(),
        ]);
    }

    /** @param list<int>|null $allowed */
    private function zoneInstallation(int $clientId, ?array $allowed, ?int $requested): ?int
    {
        $available = Installation::query()->where('client_id', $clientId)
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($requested !== null) {
            if (! in_array($requested, $available, true)) {
                throw ValidationException::withMessages(['installation_id' => 'No puedes autorizar en esa instalación.']);
            }

            return $requested;
        }
        if (count($available) === 1) {
            return $available[0];
        }
        if ($allowed !== null || count($available) > 1) {
            throw ValidationException::withMessages(['installation_id' => 'Elige la instalación de la zona común.']);
        }

        return null;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
