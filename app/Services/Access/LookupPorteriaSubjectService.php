<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\AccessLog;
use App\Models\AuthorizationGuest;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorPreAuthorization;
use App\Support\Privacy\MinorPersonalData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class LookupPorteriaSubjectService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BlocklistGuard $blocklist,
    ) {}

    /**
     * @return array{q: string, hits: list<array<string, mixed>>}
     */
    public function search(string $q): array
    {
        $q = trim($q);
        $clientId = (int) $this->tenant->clientId();
        $user = auth()->user();

        if ($q === '' || $clientId < 1) {
            return ['q' => $q, 'hits' => []];
        }

        $token = $this->extractToken($q);
        if ($token !== null) {
            $byToken = $this->authorizationHits($token);
            if ($byToken !== null) {
                return ['q' => $q, 'hits' => $byToken['hits'], 'authorization' => $byToken['summary']];
            }
        }

        $hits = [];

        foreach ($this->members($clientId, $q, $user) as $row) {
            $hits[] = $row;
        }
        foreach ($this->visitors($clientId, $q) as $row) {
            $hits[] = $row;
        }
        foreach ($this->vehicles($clientId, $q) as $row) {
            $hits[] = $row;
        }

        return ['q' => $q, 'hits' => $hits];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function hostsForNode(int $structureId): array
    {
        $clientId = (int) $this->tenant->clientId();

        return StructureMember::query()
            ->where('client_id', $clientId)
            ->where('structure_id', $structureId)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->limit(40)
            ->get()
            ->map(fn (StructureMember $m): array => ['id' => $m->id, 'name' => $m->full_name])
            ->all();
    }

    /** @return Collection<int, Structure> */
    public function nodes(): Collection
    {
        $clientId = (int) $this->tenant->clientId();

        return Structure::query()
            ->with(['installation', 'structureType'])
            ->where('client_id', $clientId)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, MemberType> */
    public function memberTypes(): Collection
    {
        return MemberType::query()
            ->where('client_id', (int) $this->tenant->clientId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function members(int $clientId, string $q, mixed $user): array
    {
        return StructureMember::query()
            ->with(['structure.installation'])
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->where(function ($query) use ($q): void {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get()
            ->map(function (StructureMember $member) use ($user): array {
                $block = $this->blocklist->checkPerson(documentNumber: $member->document_number);
                $inside = $this->activeLog('structure_member_id', (int) $member->id);

                return $this->card(
                    kind: 'member',
                    id: (int) $member->id,
                    title: $member->full_name,
                    document: MinorPersonalData::documentForDisplay($user, $member->birth_date, $member->document_type, $member->document_number),
                    census: true,
                    photo: $member->photo_path,
                    node: $member->structure?->name,
                    blocked: $block !== null,
                    blockReason: $block?->reason,
                    inside: $inside,
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visitors(int $clientId, string $q): array
    {
        return Visitor::query()
            ->where('client_id', $clientId)
            ->where(function ($query) use ($q): void {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get()
            ->map(fn (Visitor $visitor): array => $this->visitorCard($visitor, $this->bestGuestFor((int) $visitor->id)))
            ->all();
    }

    /** @return array<string, mixed> */
    private function visitorCard(Visitor $visitor, ?AuthorizationGuest $guest): array
    {
        $block = $this->blocklist->checkPerson(visitor: $visitor);

        return [
            ...$this->card(
                kind: 'visitor',
                id: (int) $visitor->id,
                title: $visitor->full_name,
                document: $visitor->displayedDocument(),
                census: false,
                photo: $visitor->photo_path,
                blocked: $block !== null,
                blockReason: $block?->reason,
                inside: $this->activeLog('visitor_id', (int) $visitor->id),
                last: $this->lastVisit('visitor_id', (int) $visitor->id),
            ),
            'auth' => $guest !== null ? $this->authPayload($guest) : null,
        ];
    }

    /** Texto escaneado del QR (URL del pase) o token pegado a mano; los documentos numéricos no cuentan. */
    private function extractToken(string $q): ?string
    {
        if (preg_match('~/q/([A-Za-z0-9]{16,64})~', $q, $m) === 1) {
            return $m[1];
        }

        return preg_match('/^[A-Za-z0-9]{16,64}$/', $q) === 1 && ! ctype_digit($q) ? $q : null;
    }

    /**
     * @return array{hits: list<array<string, mixed>>, summary: array<string, mixed>}|null
     */
    private function authorizationHits(string $token): ?array
    {
        $authorization = VisitorPreAuthorization::query()
            ->with(['structure', 'member', 'guests.visitor'])
            ->where('qr_auth_token', $token)
            ->first();
        if ($authorization === null) {
            return null;
        }

        $hits = [];
        $unregistered = [];
        foreach ($authorization->guests as $guest) {
            $guest->setRelation('authorization', $authorization);
            if ($guest->visitor !== null) {
                $hits[] = $this->visitorCard($guest->visitor, $guest);
            } else {
                $unregistered[] = $guest->fullName();
            }
        }

        return [
            'hits' => $hits,
            'summary' => [
                'id' => $authorization->id,
                'type' => $authorization->typeLabel(),
                'destination' => $authorization->destinationLabel(),
                'state' => $authorization->state(),
                'state_label' => $authorization->stateLabel(),
                'message' => $authorization->stateMessage(),
                'guests' => $authorization->guests->count(),
                'unregistered' => $unregistered,
            ],
        ];
    }

    /** Autorización más relevante del visitante: primero la que permite entrar, luego la más reciente (máx. 30 días). */
    private function bestGuestFor(int $visitorId): ?AuthorizationGuest
    {
        return AuthorizationGuest::query()
            ->with(['authorization.structure', 'authorization.member'])
            ->where('visitor_id', $visitorId)
            ->whereHas('authorization', fn ($q) => $q->where('valid_until', '>=', now()->subDays(30)))
            ->latest('id')
            ->limit(10)
            ->get()
            ->sortByDesc(fn (AuthorizationGuest $g) => ($g->canEnter() ? 1 : 0).'-'.$g->authorization->valid_until->format('YmdHis'))
            ->first();
    }

    /** @return array<string, mixed> */
    private function authPayload(AuthorizationGuest $guest): array
    {
        $authorization = $guest->authorization;
        $canEnter = $guest->canEnter();
        $alreadyUsed = ! $canEnter && $authorization->state() === VisitorPreAuthorization::STATE_ACTIVE;

        return [
            'id' => $authorization->id,
            'guest_id' => $guest->id,
            'type' => $authorization->typeLabel(),
            'destination' => $authorization->destinationLabel(),
            'destination_structure_id' => $authorization->structure_id,
            'destination_text' => $authorization->structure_id === null ? $authorization->destination_text : null,
            'authorized_member_id' => $authorization->member_id,
            'authorized_name' => $authorization->authorizerLabel(),
            'authorized_text' => $authorization->member_id === null ? $authorization->authorizerLabel() : null,
            'validity' => $authorization->validityLabel(),
            'state' => $alreadyUsed ? VisitorPreAuthorization::STATE_USED : $authorization->state(),
            'state_label' => $alreadyUsed ? 'Usada' : $authorization->stateLabel(),
            'message' => $alreadyUsed
                ? 'Ya ingresó con esta autorización (una sola entrada) · confirma con la unidad.'
                : $authorization->stateMessage(),
            'can_enter' => $canEnter,
            'multi_entry' => (bool) $authorization->multi_entry,
            'notes' => $authorization->notes,
            'plate' => $authorization->vehicle_plate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vehicles(int $clientId, string $q): array
    {
        $plate = strtoupper($q);

        return Vehicle::query()
            ->with(['structure', 'visitor'])
            ->where('client_id', $clientId)
            ->where('plate', 'like', "%{$plate}%")
            ->limit(8)
            ->get()
            ->map(function (Vehicle $vehicle): array {
                $block = $this->blocklist->checkVehicle(vehicle: $vehicle);
                $census = $vehicle->structure_id !== null;
                $inside = $this->activeLog('vehicle_id', (int) $vehicle->id);
                $last = $census ? null : $this->lastVisit('vehicle_id', (int) $vehicle->id);

                return $this->card(
                    kind: 'vehicle',
                    id: (int) $vehicle->id,
                    title: $vehicle->plate,
                    document: trim($vehicle->brand.' '.$vehicle->color),
                    census: $census,
                    photo: $vehicle->photo_path,
                    plate: $vehicle->plate,
                    node: $census ? $vehicle->structure?->name : $vehicle->visitor?->full_name,
                    visitorId: $vehicle->visitor_id ? (int) $vehicle->visitor_id : null,
                    blocked: $block !== null,
                    blockReason: $block?->reason,
                    inside: $inside,
                    last: $last,
                );
            })
            ->all();
    }

    private function activeLog(string $column, int $id): ?AccessLog
    {
        return AccessLog::query()
            ->where('status', 'active')
            ->where($column, $id)
            ->latest('entry_time')
            ->first();
    }

    /**
     * @return array{destination_structure_id: ?int, destination_name: ?string, destination_text: ?string, authorized_member_id: ?int, authorized_name: ?string, authorized_text: ?string}|null
     */
    private function lastVisit(string $column, int $id): ?array
    {
        $log = AccessLog::query()
            ->with(['destinationStructure', 'authorizedMember'])
            ->where($column, $id)
            ->latest('id')
            ->first();

        if ($log === null) {
            return null;
        }

        return [
            'destination_structure_id' => $log->destination_structure_id,
            'destination_name' => $log->destinationStructure?->name,
            'destination_text' => $log->destination_text,
            'authorized_member_id' => $log->authorized_member_id,
            'authorized_name' => $log->authorizedMember?->full_name,
            'authorized_text' => $log->authorized_text,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $last
     * @return array<string, mixed>
     */
    private function card(
        string $kind,
        int $id,
        string $title,
        string $document,
        bool $census,
        ?string $photo,
        ?AccessLog $inside,
        bool $blocked,
        ?string $blockReason,
        ?string $node = null,
        ?string $plate = null,
        ?int $visitorId = null,
        ?array $last = null,
    ): array {
        return [
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'document' => $document,
            'census' => $census,
            'photo_url' => $this->photoUrl($photo),
            'node' => $node,
            'plate' => $plate,
            'visitor_id' => $visitorId,
            'blocked' => $blocked,
            'block_reason' => $blockReason,
            'inside' => $inside !== null,
            'active_log_id' => $inside?->id,
            'last' => $last,
        ];
    }

    private function photoUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
