<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;
use App\Models\SupervisorShiftTemplate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ResolveSupervisorShiftDeadlineService
{
    public const GRACE_MINUTES = 30;

    public const FALLBACK_IDLE_HOURS = 3;

    /** Relevo anticipado: se puede abrir hasta 2 h antes del inicio de la plantilla. */
    public const EARLY_START_MINUTES = 120;

    public function graceDeadline(SupervisorShift $shift, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $opened = $shift->started_at;
        if ($opened === null) {
            return null;
        }
        $openedAt = CarbonImmutable::parse($opened->toIso8601String());

        $template = $shift->shiftTemplate;
        if ($template instanceof SupervisorShiftTemplate && $template->starts_at && $template->ends_at) {
            $end = $this->scheduledEnd($openedAt, (string) $template->starts_at, (string) $template->ends_at);
            if ($end !== null) {
                return $end->addMinutes(self::GRACE_MINUTES);
            }
        }

        $lastGps = $shift->locations()->orderByDesc('recorded_at')->value('recorded_at');
        $anchor = $lastGps !== null
            ? CarbonImmutable::parse((string) $lastGps)
            : $openedAt;

        return $anchor->addHours(self::FALLBACK_IDLE_HOURS);
    }

    /**
     * Fin programado del turno que corresponde a esa hora de apertura: la franja de la plantilla
     * que la contiene, o la que empieza en las próximas 2 h (relevo anticipado).
     * null = la hora queda fuera de la plantilla (p. ej. turno Día abierto a las 20:00).
     */
    public function scheduledEnd(CarbonImmutable $openedAt, string $startsAt, string $endsAt): ?CarbonImmutable
    {
        return $this->window($openedAt, $startsAt, $endsAt)['end'] ?? null;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable}|null */
    public function window(CarbonImmutable $at, string $startsAt, string $endsAt): ?array
    {
        $local = $at->timezone((string) config('app.timezone'));
        [$startH, $startM] = $this->clock($startsAt);
        [$endH, $endM] = $this->clock($endsAt);
        $overnight = ($endH * 60 + $endM) <= ($startH * 60 + $startM);

        foreach ([-1, 0, 1] as $offset) {
            $start = $local->addDays($offset)->setTime($startH, $startM, 0);
            $end = $start->setTime($endH, $endM, 0);
            if ($overnight) {
                $end = $end->addDay();
            }
            if ($local->gte($start->subMinutes(self::EARLY_START_MINUTES)) && $local->lt($end)) {
                return ['start' => $start, 'end' => $end];
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int} */
    private function clock(string $value): array
    {
        $parts = explode(':', $value);

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }
}
