<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Enums\BookingStatus;
use App\Models\CommonZone;
use App\Models\CommonZoneBooking;
use App\Models\Installation;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Models\VisitorPreAuthorization;
use App\Services\Structure\ManageAuthorizationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ZoneBookingService
{
    private const MAX_SPAN_DAYS = 31;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
        private readonly ManageAuthorizationService $authorizations,
    ) {}

    /** @param array<string, mixed> $data */
    public function saveZone(User $actor, ?CommonZone $zone, array $data): CommonZone
    {
        $clientId = (int) $this->tenant->clientId();
        $installationId = (int) $data['installation_id'];
        $allowed = $this->tenant->installationIds();
        $ok = Installation::query()->where('client_id', $clientId)->whereKey($installationId)->exists();
        if (! $ok || ($allowed !== null && ! in_array($installationId, $allowed, true))) {
            throw ValidationException::withMessages(['installation_id' => 'No puedes crear zonas en esa instalación.']);
        }
        if ($zone !== null) {
            $this->assertZoneScope($zone);
        }

        $weekdays = array_values(array_unique(array_map('intval', (array) ($data['weekdays'] ?? []))));
        sort($weekdays);
        $attributes = [
            'client_id' => $clientId,
            'installation_id' => $installationId,
            'name' => trim((string) $data['name']),
            'type' => $data['type'],
            'booking_mode' => $data['booking_mode'],
            'capacity' => (int) $data['capacity'],
            'open_time' => $data['open_time'],
            'close_time' => $data['close_time'],
            'allow_full_day' => (bool) ($data['allow_full_day'] ?? false),
            'weekdays' => count($weekdays) === 7 ? null : $weekdays,
            'min_minutes' => (int) round(((float) ($data['min_hours'] ?? 1)) * 60),
            'max_minutes' => filled($data['max_hours'] ?? null) ? (int) round(((float) $data['max_hours']) * 60) : null,
            'buffer_minutes' => (int) ($data['buffer_minutes'] ?? 0),
            'max_advance_days' => (int) ($data['max_advance_days'] ?? 90),
            'max_per_unit_month' => filled($data['max_per_unit_month'] ?? null) ? (int) $data['max_per_unit_month'] : null,
            'rules' => filled($data['rules'] ?? null) ? trim((string) $data['rules']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($zone === null) {
            $zone = CommonZone::query()->create($attributes);
            $this->audit->record($zone, 'zone.create', null, ['name' => $zone->name, 'installation_id' => $installationId]);
        } else {
            $zone->update($attributes);
            $this->audit->record($zone, 'zone.update', null, ['name' => $zone->name, 'is_active' => $zone->is_active]);
        }

        return $zone;
    }

    /**
     * Reserva registrada por el admin: queda confirmada.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, CommonZone $zone, array $data): CommonZoneBooking
    {
        $this->assertZoneScope($zone);
        $slot = $this->slot($zone, $data);
        $this->assertRules($zone, $slot, null);

        return DB::transaction(function () use ($actor, $zone, $slot): CommonZoneBooking {
            $this->assertAvailable($zone, $slot, null);
            $booking = CommonZoneBooking::query()->create([
                ...$this->attributes($zone, $slot),
                'user_id' => $actor->id,
                'status' => BookingStatus::Confirmed,
                'origin' => 'admin',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
            $this->attachEvent($actor, $booking);
            $this->audit->record($booking, 'zone.booking.create', null, $this->auditPayload($booking));

            return $booking;
        });
    }

    /**
     * Solicitud (residente, app): queda por aprobar y no ocupa la zona.
     *
     * @param  array<string, mixed>  $data
     */
    public function request(?User $actor, CommonZone $zone, array $data, string $origin = 'app'): CommonZoneBooking
    {
        $this->assertZoneScope($zone);
        $slot = $this->slot($zone, $data);
        $this->assertRules($zone, $slot, null);

        $booking = CommonZoneBooking::query()->create([
            ...$this->attributes($zone, $slot),
            'user_id' => $actor?->id,
            'status' => BookingStatus::Pending,
            'origin' => $origin,
        ]);
        $this->audit->record($booking, 'zone.booking.request', null, $this->auditPayload($booking));

        return $booking;
    }

    /**
     * Aprobar tal cual o con cambios (fecha, horario, unidad, personas…).
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function approve(User $actor, CommonZoneBooking $booking, ?array $changes = null): CommonZoneBooking
    {
        $this->assertBookingScope($booking);
        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages(['booking' => 'Solo se aprueban solicitudes por aprobar.']);
        }

        $zone = $booking->zone;
        $slot = $changes !== null ? $this->slot($zone, $changes) : $this->slotOf($booking);
        $this->assertRules($zone, $slot, $booking->id);

        return DB::transaction(function () use ($actor, $booking, $zone, $slot, $changes): CommonZoneBooking {
            $this->assertAvailable($zone, $slot, $booking->id);
            $booking->update([
                ...$this->attributes($zone, $slot),
                'status' => BookingStatus::Confirmed,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $changes !== null ? $this->clean($changes['review_note'] ?? null) : null,
            ]);
            $this->attachEvent($actor, $booking);
            $this->audit->record($booking, $changes !== null ? 'zone.booking.approve_modified' : 'zone.booking.approve', null, $this->auditPayload($booking));

            return $booking;
        });
    }

    public function reject(User $actor, CommonZoneBooking $booking, ?string $note): void
    {
        $this->assertBookingScope($booking);
        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages(['booking' => 'Solo se rechazan solicitudes por aprobar.']);
        }

        $booking->update([
            'status' => BookingStatus::Rejected,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $this->clean($note),
        ]);
        $this->audit->record($booking, 'zone.booking.reject', null, ['note' => $booking->review_note]);
    }

    public function cancel(User $actor, CommonZoneBooking $booking, ?string $note = null): void
    {
        $this->assertBookingScope($booking);
        if (! in_array($booking->status->value, BookingStatus::open(), true)) {
            throw ValidationException::withMessages(['booking' => 'La reserva ya no se puede cancelar.']);
        }

        DB::transaction(function () use ($actor, $booking, $note): void {
            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'review_note' => $this->clean($note) ?? $booking->review_note,
            ]);
            $authorization = $booking->authorization;
            if ($authorization !== null && in_array($authorization->state(), [VisitorPreAuthorization::STATE_ACTIVE, VisitorPreAuthorization::STATE_UPCOMING], true)) {
                $this->authorizations->cancel($actor, $authorization);
            }
            $this->audit->record($booking, 'zone.booking.cancel', null, ['note' => $this->clean($note)]);
        });
    }

    public function checkIn(User $actor, CommonZoneBooking $booking): void
    {
        $this->assertBookingScope($booking);
        if ($booking->status !== BookingStatus::Confirmed || ! $booking->covers(today())) {
            throw ValidationException::withMessages(['booking' => 'Solo se da uso a reservas confirmadas para hoy.']);
        }

        $booking->update(['status' => BookingStatus::CheckedIn, 'checked_in_at' => now()]);
        $this->audit->record($booking, 'zone.checkin', null, ['by' => $actor->id]);
    }

    public function complete(User $actor, CommonZoneBooking $booking): void
    {
        $this->assertBookingScope($booking);
        if ($booking->status !== BookingStatus::CheckedIn) {
            throw ValidationException::withMessages(['booking' => 'Solo se finaliza una reserva en uso.']);
        }

        $booking->update(['status' => BookingStatus::Completed]);
        $this->audit->record($booking, 'zone.complete', null, ['by' => $actor->id]);
    }

    public function assertZoneScope(CommonZone $zone): void
    {
        $allowed = $this->tenant->installationIds();
        abort_if(
            (int) $zone->client_id !== (int) $this->tenant->clientId()
            || ($allowed !== null && ! in_array((int) $zone->installation_id, $allowed, true)),
            404,
        );
    }

    public function assertBookingScope(CommonZoneBooking $booking): void
    {
        $allowed = $this->tenant->installationIds();
        abort_if(
            (int) $booking->client_id !== (int) $this->tenant->clientId()
            || ($allowed !== null && ! in_array((int) $booking->installation_id, $allowed, true)),
            404,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{from: Carbon, until: Carbon, full_day: bool, start: string, end: string, people: int, structure_id: int, member_id: ?int, guests: list<array<string, ?string>>, notes: ?string}
     */
    private function slot(CommonZone $zone, array $data): array
    {
        $from = Carbon::parse((string) $data['date'])->startOfDay();
        $until = filled($data['date_until'] ?? null) ? Carbon::parse((string) $data['date_until'])->startOfDay() : $from->copy();
        $fullDay = filter_var($data['full_day'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'from' => $from,
            'until' => $until,
            'full_day' => $fullDay,
            'start' => $fullDay ? $zone->openTime() : substr((string) ($data['start_time'] ?? ''), 0, 5),
            'end' => $fullDay ? $zone->closeTime() : substr((string) ($data['end_time'] ?? ''), 0, 5),
            'people' => max(1, (int) ($data['people_count'] ?? 1)),
            'structure_id' => (int) ($data['structure_id'] ?? 0),
            'member_id' => (int) ($data['structure_member_id'] ?? 0) ?: null,
            'guests' => $this->normalizeGuests($data['guests'] ?? []),
            'notes' => $this->clean($data['notes'] ?? null),
        ];
    }

    /** @return array{from: Carbon, until: Carbon, full_day: bool, start: string, end: string, people: int, structure_id: int, member_id: ?int, guests: list<array<string, ?string>>, notes: ?string} */
    private function slotOf(CommonZoneBooking $booking): array
    {
        return [
            'from' => $booking->date->copy()->startOfDay(),
            'until' => $booking->lastDate()->copy()->startOfDay(),
            'full_day' => (bool) $booking->full_day,
            'start' => $booking->startTime(),
            'end' => $booking->endTime(),
            'people' => (int) $booking->people_count,
            'structure_id' => (int) $booking->structure_id,
            'member_id' => $booking->structure_member_id !== null ? (int) $booking->structure_member_id : null,
            'guests' => array_values($booking->guests ?? []),
            'notes' => $booking->notes,
        ];
    }

    /** @param array<string, mixed> $slot */
    private function attributes(CommonZone $zone, array $slot): array
    {
        return [
            'client_id' => (int) $zone->client_id,
            'installation_id' => $zone->installation_id,
            'common_zone_id' => $zone->id,
            'structure_id' => $slot['structure_id'],
            'structure_member_id' => $slot['member_id'],
            'date' => $slot['from']->toDateString(),
            'date_until' => $slot['until']->isSameDay($slot['from']) ? null : $slot['until']->toDateString(),
            'full_day' => $slot['full_day'],
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'people_count' => $slot['people'],
            'guests' => $slot['guests'] === [] ? null : $slot['guests'],
            'notes' => $slot['notes'],
        ];
    }

    /** @param array<string, mixed> $slot */
    private function assertRules(CommonZone $zone, array $slot, ?int $ignoreId): void
    {
        if (! $zone->is_active) {
            throw ValidationException::withMessages(['common_zone_id' => 'La zona no está activa.']);
        }

        $structure = Structure::query()->where('client_id', $zone->client_id)->whereKey($slot['structure_id'])->first();
        if ($structure === null || (int) $structure->installation_id !== (int) $zone->installation_id) {
            throw ValidationException::withMessages(['structure_id' => 'Elige una unidad de la misma instalación de la zona.']);
        }
        if ($slot['member_id'] !== null
            && ! StructureMember::query()->whereKey($slot['member_id'])->where('structure_id', $structure->id)->exists()) {
            throw ValidationException::withMessages(['structure_member_id' => 'Quien solicita debe pertenecer a la unidad.']);
        }

        [$from, $until] = [$slot['from'], $slot['until']];
        if ($from->lt(today())) {
            throw ValidationException::withMessages(['date' => 'No se puede reservar en días pasados.']);
        }
        if ($until->lt($from)) {
            throw ValidationException::withMessages(['date_until' => 'El día final no puede ser anterior al inicial.']);
        }
        if ((int) $from->diffInDays($until) + 1 > self::MAX_SPAN_DAYS) {
            throw ValidationException::withMessages(['date_until' => 'Una reserva puede cubrir máximo '.self::MAX_SPAN_DAYS.' días.']);
        }
        if ($until->gt(today()->addDays($zone->max_advance_days))) {
            throw ValidationException::withMessages(['date' => "Esta zona se reserva con máximo {$zone->max_advance_days} días de anticipación."]);
        }

        $allowed = $zone->allowedWeekdays();
        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $allowed, true)) {
                throw ValidationException::withMessages(['date' => "La zona no se reserva el {$day->translatedFormat('l j \\d\\e F')}. Días: {$zone->weekdaysLabel()}."]);
            }
        }

        if ($slot['full_day']) {
            if (! $zone->allow_full_day) {
                throw ValidationException::withMessages(['full_day' => 'Esta zona no se reserva por día completo; indica el horario.']);
            }
        } else {
            if (! preg_match('/^\d{2}:\d{2}$/', $slot['start']) || ! preg_match('/^\d{2}:\d{2}$/', $slot['end'])) {
                throw ValidationException::withMessages(['start_time' => 'Indica la hora de inicio y la hora final.']);
            }
            if ($slot['end'] <= $slot['start']) {
                throw ValidationException::withMessages(['end_time' => 'La hora final debe ser posterior a la inicial.']);
            }
            if ($slot['start'] < $zone->openTime() || $slot['end'] > $zone->closeTime()) {
                throw ValidationException::withMessages(['start_time' => "El horario de la zona es {$zone->scheduleLabel()}."]);
            }
            $minutes = $this->minutes($slot['end']) - $this->minutes($slot['start']);
            if ($minutes < $zone->min_minutes) {
                throw ValidationException::withMessages(['end_time' => 'La reserva mínima es de '.$this->hours($zone->min_minutes).'.']);
            }
            if ($zone->max_minutes !== null && $minutes > $zone->max_minutes) {
                throw ValidationException::withMessages(['end_time' => 'La reserva máxima es de '.$this->hours($zone->max_minutes).'.']);
            }
        }

        if ($zone->isShared() && $slot['people'] > $zone->capacity) {
            throw ValidationException::withMessages(['people_count' => "La capacidad de la zona es de {$zone->capacity} personas."]);
        }

        if ($zone->max_per_unit_month !== null) {
            $used = CommonZoneBooking::query()
                ->where('common_zone_id', $zone->id)
                ->where('structure_id', $structure->id)
                ->whereIn('status', [...BookingStatus::open(), BookingStatus::CheckedIn->value, BookingStatus::Completed->value])
                ->whereYear('date', $from->year)->whereMonth('date', $from->month)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->count();
            if ($used >= $zone->max_per_unit_month) {
                throw ValidationException::withMessages(['structure_id' => "{$structure->name} ya usó sus {$zone->max_per_unit_month} reservas de este mes en {$zone->name}."]);
            }
        }
    }

    /** @param array<string, mixed> $slot */
    private function assertAvailable(CommonZone $zone, array $slot, ?int $ignoreId): void
    {
        [$from, $until] = [$slot['from'], $slot['until']];
        $start = $this->minutes($slot['start']) - $zone->buffer_minutes;
        $end = $this->minutes($slot['end']) + $zone->buffer_minutes;

        $others = CommonZoneBooking::query()
            ->with('structure:id,name')
            ->where('common_zone_id', $zone->id)
            ->whereIn('status', BookingStatus::holding())
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereDate('date', '<=', $until->toDateString())
            ->where(fn ($q) => $q->whereDate('date_until', '>=', $from->toDateString())
                ->orWhere(fn ($q) => $q->whereNull('date_until')->whereDate('date', '>=', $from->toDateString())))
            ->lockForUpdate()
            ->get();

        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            $overlapping = $others->filter(fn (CommonZoneBooking $b) => $b->covers($day)
                && $this->minutes($b->startTime()) < $end
                && $this->minutes($b->endTime()) > $start);

            if ($overlapping->isEmpty()) {
                continue;
            }
            $label = $day->translatedFormat('j M');
            if (! $zone->isShared()) {
                $hit = $overlapping->first();
                throw ValidationException::withMessages(['date' => "El {$label} la zona ya está reservada por {$hit->unitLabel()} ({$hit->hoursLabel()})."
                    .($zone->buffer_minutes > 0 ? " Se dejan {$zone->buffer_minutes} min de limpieza entre reservas." : '')]);
            }
            $taken = (int) $overlapping->sum('people_count');
            if ($taken + $slot['people'] > $zone->capacity) {
                $free = max(0, $zone->capacity - $taken);
                throw ValidationException::withMessages(['people_count' => "El {$label} en ese horario solo quedan {$free} cupos de {$zone->capacity}."]);
            }
        }
    }

    private function attachEvent(User $actor, CommonZoneBooking $booking): void
    {
        if ($booking->authorization_id !== null || empty($booking->guests)) {
            return;
        }

        $booking->loadMissing(['zone', 'structure', 'member']);
        $authorization = $this->authorizations->create($actor, [
            'type' => 'event',
            'destination_text' => mb_substr($booking->zone->name.' · reserva '.$booking->unitLabel(), 0, 150),
            'installation_id' => $booking->installation_id,
            'member_id' => $booking->structure_member_id,
            'authorized_text' => $booking->structure_member_id === null ? mb_substr('Reserva '.$booking->unitLabel(), 0, 150) : null,
            'valid_from' => $booking->date->toDateString(),
            'valid_until' => $booking->lastDate()->toDateString(),
            'entry_time_from' => $booking->full_day ? null : $booking->startTime(),
            'entry_time_until' => $booking->full_day ? null : $booking->endTime(),
            'multi_entry' => true,
            'notes' => $booking->notes,
            'origin' => 'booking',
            'guests' => $booking->guests,
        ]);

        $booking->update(['authorization_id' => $authorization->id]);
    }

    /** @return list<array{document_type: string, document_number: string, first_name: string, last_name: string, phone: ?string}> */
    private function normalizeGuests(mixed $rows): array
    {
        $guests = [];
        $seen = [];
        foreach ((array) $rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $document = preg_replace('/[\s.]/', '', (string) ($row['document_number'] ?? '')) ?? '';
            $first = $this->clean($row['first_name'] ?? null);
            $last = $this->clean($row['last_name'] ?? null);
            if ($document === '' && $first === null) {
                continue;
            }
            if ($document === '' || $first === null || $last === null) {
                throw ValidationException::withMessages(["guests.{$index}" => 'Cada invitado necesita documento, nombres y apellidos.']);
            }
            if (isset($seen[$document])) {
                throw ValidationException::withMessages(["guests.{$index}" => "El documento {$document} está repetido."]);
            }
            $seen[$document] = true;
            $guests[] = [
                'document_type' => $this->clean($row['document_type'] ?? null) ?? 'CC',
                'document_number' => $document,
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $this->clean($row['phone'] ?? null),
            ];
        }

        return $guests;
    }

    private function auditPayload(CommonZoneBooking $booking): array
    {
        return [
            'zone_id' => $booking->common_zone_id,
            'structure_id' => $booking->structure_id,
            'date' => $booking->date->toDateString(),
            'date_until' => $booking->date_until?->toDateString(),
            'hours' => $booking->hoursLabel(),
            'people' => $booking->people_count,
            'guests' => $booking->guestsCount(),
            'status' => $booking->status->value,
        ];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)) + [0, 0]);

        return $h * 60 + $m;
    }

    private function hours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? ($h === 1 ? '1 hora' : "{$h} horas") : sprintf('%d h %02d min', $h, $m);
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
