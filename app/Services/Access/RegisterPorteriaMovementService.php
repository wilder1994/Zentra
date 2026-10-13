<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Domain\Structure\Data\CreateMemberData;
use App\Models\AccessLog;
use App\Models\AuthorizationGuest;
use App\Models\Location;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorPreAuthorization;
use App\Services\Structure\CreateMemberService;
use App\Services\Structure\ManageAuthorizationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterPorteriaMovementService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BlocklistGuard $blocklist,
        private readonly PorteriaDoorService $doors,
        private readonly CreateMemberService $createMember,
        private readonly AuditLogger $audit,
        private readonly ManageAuthorizationService $authorizations,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function move(User $actor, array $input, ?UploadedFile $photo = null): AccessLog
    {
        $kind = (string) ($input['kind'] ?? '');
        $id = (int) ($input['id'] ?? 0);
        $action = (string) ($input['action'] ?? '');

        if (! in_array($kind, ['member', 'visitor', 'vehicle'], true) || $id < 1) {
            throw ValidationException::withMessages(['kind' => 'Selecciona una ficha.']);
        }
        if (! in_array($action, ['enter', 'exit'], true)) {
            throw ValidationException::withMessages(['action' => 'Indica si ingresa o sale.']);
        }

        if ($action === 'exit') {
            return $this->exit($kind, $id);
        }

        return $this->enterExisting($actor, $kind, $id, $input, $photo);
    }

    /**
     * Alta de ficha (foto opcional en el maestro). No crea movimiento.
     *
     * @param  array<string, mixed>  $input
     * @return array{kind: string, id: int}
     */
    public function register(array $input, ?UploadedFile $photo = null, bool $requirePhoto = false): array
    {
        $clientId = (int) $this->tenant->clientId();
        $kind = (string) ($input['subject_kind'] ?? 'visitor');

        if ($kind === 'member') {
            $member = $this->resolveMember($clientId, $input);
            $this->applyPhoto($member, $photo, 'porteria/personas');

            return ['kind' => 'member', 'id' => (int) $member->id];
        }

        if ($kind === 'vehicle') {
            if ($requirePhoto && $photo === null) {
                $plate = strtoupper(trim((string) ($input['plate'] ?? '')));
                $existingPhoto = $plate !== ''
                    ? Vehicle::query()->where('client_id', $clientId)->where('plate', $plate)->value('photo_path')
                    : null;
                if (empty($existingPhoto)) {
                    throw ValidationException::withMessages([
                        'person_photo_data' => 'La foto del vehículo es obligatoria en el primer registro.',
                    ]);
                }
            }
            $visitor = null;
            if (empty($input['structure_id'])) {
                $visitor = $this->resolveVisitor($clientId, $input);
            }
            $vehicle = $this->resolveVehicle($clientId, $input, $visitor, null);
            $this->applyPhoto($vehicle, $photo, 'porteria/vehiculos');

            return ['kind' => 'vehicle', 'id' => (int) $vehicle->id];
        }

        if ($requirePhoto && $photo === null) {
            $doc = trim((string) ($input['document_number'] ?? ''));
            $docType = (string) ($input['document_type'] ?? 'CC');
            $existingPhoto = Visitor::withTrashed()
                ->where('client_id', $clientId)
                ->where('document_type', $docType)
                ->where('document_number', $doc)
                ->value('photo_path');
            if (empty($existingPhoto)) {
                throw ValidationException::withMessages([
                    'person_photo_data' => 'La foto del visitante es obligatoria en el primer registro.',
                ]);
            }
        }
        $visitor = $this->resolveVisitor($clientId, $input);
        $this->applyPhoto($visitor, $photo, 'porteria/personas');

        if (! empty($input['plate'])) {
            $this->resolveVehicle($clientId, $input, $visitor, null);
        }

        return ['kind' => 'visitor', 'id' => (int) $visitor->id];
    }

    /**
     * Compatibilidad: alta + ingreso (POST antiguo).
     *
     * @param  array<string, mixed>  $input
     */
    public function enter(User $actor, array $input, ?UploadedFile $personPhoto = null, ?UploadedFile $vehiclePhoto = null): AccessLog
    {
        if (! empty($input['member_id'])) {
            $this->applyPhoto(
                StructureMember::query()->where('client_id', (int) $this->tenant->clientId())->whereKey($input['member_id'])->firstOrFail(),
                $personPhoto,
                'porteria/personas',
            );

            return $this->move($actor, [
                'kind' => 'member',
                'id' => (int) $input['member_id'],
                'action' => 'enter',
            ] + $input);
        }

        if (! empty($input['visitor_id'])) {
            $this->applyPhoto(
                Visitor::query()->where('client_id', (int) $this->tenant->clientId())->whereKey($input['visitor_id'])->firstOrFail(),
                $personPhoto,
                'porteria/personas',
            );

            return $this->move($actor, [
                'kind' => 'visitor',
                'id' => (int) $input['visitor_id'],
                'action' => 'enter',
            ] + $input);
        }

        $kind = (string) ($input['subject_kind'] ?? 'visitor');
        $created = $this->register($input, $kind === 'vehicle' ? $vehiclePhoto : $personPhoto);

        return $this->move($actor, [
            'kind' => $created['kind'],
            'id' => $created['id'],
            'action' => 'enter',
        ] + $input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function enterExisting(User $actor, string $kind, int $id, array $input, ?UploadedFile $photo = null): AccessLog
    {
        $clientId = (int) $this->tenant->clientId();
        $door = $this->requireDoor();
        $column = $this->column($kind);

        $active = AccessLog::query()->where('status', 'active')->where($column, $id)->first();
        if ($active !== null) {
            throw ValidationException::withMessages(['action' => 'Ya está adentro. Usa Sale.']);
        }

        $member = null;
        $visitor = null;
        $vehicle = null;
        $census = false;

        if ($kind === 'member') {
            $member = StructureMember::query()->where('client_id', $clientId)->whereKey($id)->firstOrFail();
            $this->assertPersonNotBlocked($member->document_number);
            $census = true;
            $this->applyPhoto($member, $photo, 'porteria/personas');
        } elseif ($kind === 'visitor') {
            $visitor = Visitor::query()->where('client_id', $clientId)->whereKey($id)->firstOrFail();
            $this->assertVisitorNotBlocked($visitor);
            $this->applyPhoto($visitor, $photo, 'porteria/personas');
            $visitor->refresh();
            if (empty($visitor->photo_path)) {
                throw ValidationException::withMessages([
                    'person_photo_data' => 'La foto del visitante es obligatoria para ingresar.',
                ]);
            }
        } else {
            $vehicle = Vehicle::query()->where('client_id', $clientId)->whereKey($id)->firstOrFail();
            $this->assertVehicleNotBlocked($vehicle);
            $census = $vehicle->structure_id !== null;
            if ($vehicle->visitor_id) {
                $visitor = $vehicle->visitor;
            }
            $this->applyPhoto($vehicle, $photo, 'porteria/vehiculos');
            $vehicle->refresh();
            if (! $census && empty($vehicle->photo_path)) {
                throw ValidationException::withMessages([
                    'person_photo_data' => 'La foto del vehículo visitante es obligatoria para ingresar.',
                ]);
            }
        }

        $guestId = $kind === 'visitor' ? (int) ($input['authorization_guest_id'] ?? 0) : 0;

        $accessType = match (true) {
            $kind === 'vehicle' && $census => 'member_vehicle',
            $kind === 'vehicle' => 'visitor_vehicle',
            $kind === 'member' => 'member',
            default => 'visitor',
        };

        return DB::transaction(function () use ($actor, $clientId, $door, $input, $census, $guestId, $accessType, $visitor, $member, $vehicle): AccessLog {
            $guest = null;
            if ($guestId > 0 && $visitor !== null) {
                $guest = AuthorizationGuest::query()->where('client_id', $clientId)->whereKey($guestId)->lockForUpdate()->firstOrFail();
                $this->authorizations->assertCanEnter($guest, $visitor);
                $dest = $this->authorizationDestination($guest->authorization);
            } else {
                $dest = $this->destination($clientId, $input, $census);
            }

            $log = AccessLog::query()->create([
                'client_id' => $clientId,
                'visitor_id' => $visitor?->id,
                'structure_member_id' => $member?->id,
                'vehicle_id' => $vehicle?->id,
                'host_id' => $actor->id,
                'location_id' => $door->id,
                'destination_structure_id' => $dest['structure_id'],
                'destination_text' => $dest['text'],
                'authorized_member_id' => $dest['authorized_member_id'],
                'authorized_text' => $dest['authorized_text'],
                'authorization_guest_id' => $guest?->id,
                'authorized_by' => $actor->id,
                'access_type' => $accessType,
                'entry_time' => now(),
                'status' => 'active',
                'purpose' => $input['purpose'] ?? ($guest ? $guest->authorization->typeLabel() : null),
                'notes' => $input['notes'] ?? null,
            ]);

            $this->audit->record($log, 'access.entry', null, [
                'access_type' => $accessType,
                'location_id' => $door->id,
                'authorization_guest_id' => $guest?->id,
            ]);

            if ($guest !== null) {
                $this->authorizations->registerEntry($guest, $log);
            }

            return $log;
        });
    }

    /**
     * @return array{structure_id: ?int, text: ?string, authorized_member_id: ?int, authorized_text: ?string}
     */
    private function authorizationDestination(VisitorPreAuthorization $authorization): array
    {
        return [
            'structure_id' => $authorization->structure_id,
            'text' => $authorization->structure_id === null ? $authorization->destination_text : null,
            'authorized_member_id' => $authorization->member_id,
            'authorized_text' => $authorization->member_id === null ? $authorization->authorizerLabel() : null,
        ];
    }

    private function exit(string $kind, int $id): AccessLog
    {
        $log = AccessLog::query()
            ->where('status', 'active')
            ->where($this->column($kind), $id)
            ->latest('entry_time')
            ->first();

        if ($log === null) {
            throw ValidationException::withMessages(['action' => 'No hay ingreso activo.']);
        }

        $log->update([
            'exit_time' => now(),
            'status' => 'completed',
        ]);

        $this->audit->record($log, 'access.exit', ['status' => 'active'], ['status' => 'completed']);

        return $log->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{structure_id: ?int, text: ?string, authorized_member_id: ?int, authorized_text: ?string}
     */
    private function destination(int $clientId, array $input, bool $census): array
    {
        $structureId = (int) ($input['destination_structure_id'] ?? 0);
        $text = trim((string) ($input['destination_text'] ?? ''));
        $authorizedId = (int) ($input['authorized_member_id'] ?? 0);
        $authorizedText = trim((string) ($input['authorized_text'] ?? ''));

        if ($census) {
            return [
                'structure_id' => null,
                'text' => null,
                'authorized_member_id' => null,
                'authorized_text' => null,
            ];
        }

        if ($structureId > 0) {
            $ok = Structure::query()->where('client_id', $clientId)->whereKey($structureId)->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['destination_structure_id' => 'Nodo inválido.']);
            }
        }

        if ($authorizedId > 0) {
            $ok = StructureMember::query()->where('client_id', $clientId)->whereKey($authorizedId)->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['authorized_member_id' => 'Autorizador inválido.']);
            }
        }

        if ($structureId < 1 && $text === '') {
            throw ValidationException::withMessages([
                'destination_structure_id' => 'Indica apartamento, zona o destino (texto).',
            ]);
        }

        if ($authorizedId < 1 && $authorizedText === '') {
            throw ValidationException::withMessages([
                'authorized_text' => 'Indica quién autoriza (residente del nodo o nombre).',
            ]);
        }

        return [
            'structure_id' => $structureId > 0 ? $structureId : null,
            'text' => $text !== '' ? $text : null,
            'authorized_member_id' => $authorizedId > 0 ? $authorizedId : null,
            'authorized_text' => $authorizedId > 0 ? null : ($authorizedText !== '' ? $authorizedText : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveMember(int $clientId, array $input): StructureMember
    {
        if (! empty($input['member_id'])) {
            return StructureMember::query()
                ->where('client_id', $clientId)
                ->whereKey($input['member_id'])
                ->firstOrFail();
        }

        $structureId = (int) ($input['structure_id'] ?? 0);
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $doc = trim((string) ($input['document_number'] ?? ''));
        if ($structureId < 1 || $first === '' || $last === '' || $doc === '') {
            throw ValidationException::withMessages([
                'structure_id' => 'Para crear una persona del censo indica nodo, nombre y documento.',
            ]);
        }

        $existing = StructureMember::query()
            ->where('client_id', $clientId)
            ->where('document_number', $doc)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $typeId = (int) ($input['member_type_id'] ?? 0);
        if ($typeId < 1) {
            $typeId = (int) MemberType::query()->where('client_id', $clientId)->where('is_active', true)->orderBy('id')->value('id');
        }
        if ($typeId < 1) {
            throw ValidationException::withMessages(['member_type_id' => 'No hay tipos de persona. Créalos en el panel del cliente.']);
        }

        return $this->createMember->execute(new CreateMemberData(
            clientId: $clientId,
            structureId: $structureId,
            memberTypeId: $typeId,
            firstName: $first,
            lastName: $last,
            documentType: (string) ($input['document_type'] ?? 'CC'),
            documentNumber: $doc,
            birthDate: (string) ($input['birth_date'] ?? now()->subYears(25)->toDateString()),
        ));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveVisitor(int $clientId, array $input): Visitor
    {
        if (! empty($input['visitor_id'])) {
            return Visitor::query()->where('client_id', $clientId)->whereKey($input['visitor_id'])->firstOrFail();
        }

        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $doc = trim((string) ($input['document_number'] ?? ''));
        $docType = (string) ($input['document_type'] ?? 'CC');
        if ($first === '' || $last === '' || $doc === '') {
            throw ValidationException::withMessages([
                'first_name' => 'Para un visitante nuevo indica nombre, apellido y documento.',
            ]);
        }

        $profile = [
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $this->nullableString($input['phone'] ?? null),
            'email' => $this->nullableString($input['email'] ?? null),
            'company' => $this->nullableString($input['company'] ?? null),
            'visitor_type' => $this->visitorType($input['visitor_type'] ?? null),
            'nationality' => $this->nullableString($input['nationality'] ?? null) ?? 'Colombiana',
            'birth_date' => $input['birth_date'] ?? null,
            'notes' => $this->nullableString($input['notes'] ?? null),
        ];

        $existing = Visitor::withTrashed()
            ->where('client_id', $clientId)
            ->where('document_type', $docType)
            ->where('document_number', $doc)
            ->first();
        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update(array_filter(
                $profile,
                static fn ($value) => $value !== null && $value !== '',
            ));

            return $existing->fresh();
        }

        return Visitor::query()->create([
            'client_id' => $clientId,
            'document_type' => $docType,
            'document_number' => $doc,
            ...$profile,
        ]);
    }

    private function visitorType(mixed $value): string
    {
        $type = is_string($value) ? $value : 'persona';
        $allowed = ['persona', 'contratista', 'proveedor', 'domiciliario', 'servicio'];

        return in_array($type, $allowed, true) ? $type : 'persona';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveVehicle(int $clientId, array $input, ?Visitor $visitor, ?StructureMember $member): Vehicle
    {
        if (! empty($input['vehicle_id'])) {
            return Vehicle::query()->where('client_id', $clientId)->whereKey($input['vehicle_id'])->firstOrFail();
        }

        $plate = strtoupper(trim((string) ($input['plate'] ?? '')));
        if ($plate === '') {
            throw ValidationException::withMessages(['plate' => 'Indica la placa o elige un vehículo.']);
        }

        $existing = Vehicle::query()->where('client_id', $clientId)->where('plate', $plate)->first();
        if ($existing !== null) {
            return $existing;
        }

        $structureId = $member?->structure_id ?? ((int) ($input['structure_id'] ?? 0) ?: null);

        return Vehicle::query()->create([
            'client_id' => $clientId,
            'plate' => $plate,
            'brand' => $input['vehicle_brand'] ?? null,
            'color' => $input['vehicle_color'] ?? null,
            'type' => $input['vehicle_type'] ?? 'auto',
            'visitor_id' => $visitor?->id,
            'structure_id' => $structureId,
            'is_visitor_vehicle' => $structureId === null,
        ]);
    }

    private function applyPhoto(StructureMember|Visitor|Vehicle $model, ?UploadedFile $file, string $dir): void
    {
        if ($file === null) {
            return;
        }

        $path = $file->store($dir, 'public');
        $model->update(['photo_path' => $path]);
    }

    private function requireDoor(): Location
    {
        $door = $this->doors->operatingOrFirst(request());
        if ($door === null) {
            throw ValidationException::withMessages(['location_id' => 'No hay puerta activa para operar.']);
        }

        return $door;
    }

    private function column(string $kind): string
    {
        return match ($kind) {
            'member' => 'structure_member_id',
            'visitor' => 'visitor_id',
            default => 'vehicle_id',
        };
    }

    private function assertPersonNotBlocked(?string $documentNumber): void
    {
        $block = $this->blocklist->checkPerson(documentNumber: $documentNumber);
        if ($block !== null) {
            throw ValidationException::withMessages(['q' => 'Ingreso bloqueado: '.$block->reason]);
        }
    }

    private function assertVisitorNotBlocked(Visitor $visitor): void
    {
        $block = $this->blocklist->checkPerson(visitor: $visitor);
        if ($block !== null) {
            throw ValidationException::withMessages(['q' => 'Ingreso bloqueado: '.$block->reason]);
        }
    }

    private function assertVehicleNotBlocked(Vehicle $vehicle): void
    {
        $block = $this->blocklist->checkVehicle(vehicle: $vehicle);
        if ($block !== null) {
            throw ValidationException::withMessages(['plate' => 'Vehículo bloqueado: '.$block->reason]);
        }
    }
}
