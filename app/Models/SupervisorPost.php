<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Support\Supervision\ColombiaHolidays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SupervisorPost extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = [
        'client_id',
        'installation_id',
        'name',
        'modality',
        'service_days',
        'serves_holidays',
        'is_active',
        'max_employees',
        'max_employees_note',
        'archived_at',
        'archived_with_parent',
        'latitude',
        'longitude',
    ];

    public const ALL_DAYS = 127;

    public const WEEKDAYS = 31;

    /** @var array<int, string> ISO 1 = lunes */
    public const DAY_LABELS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    protected function casts(): array
    {
        return [
            'modality' => 'integer',
            'service_days' => 'integer',
            'serves_holidays' => 'boolean',
            'is_active' => 'boolean',
            'max_employees' => 'integer',
            'archived_at' => 'datetime',
            'archived_with_parent' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function modalityLabel(): string
    {
        $hours = (int) ($this->modality ?? 0);
        if ($hours < 1) {
            return '—';
        }

        $companyId = (int) ($this->client?->security_company_id ?? 0);
        if ($companyId > 0) {
            $row = SupervisorPostModality::query()
                ->where('security_company_id', $companyId)
                ->where('hours', $hours)
                ->first();
            if ($row !== null) {
                return $row->label();
            }
        }

        return $hours.' h';
    }

    /** @param list<int> $isoDays */
    public static function daysMask(array $isoDays): int
    {
        $mask = 0;
        foreach ($isoDays as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $mask |= 1 << ($day - 1);
            }
        }

        return $mask;
    }

    /** @return list<int> */
    public function serviceDayList(): array
    {
        $mask = (int) ($this->service_days ?? self::ALL_DAYS);

        return array_values(array_filter(range(1, 7), fn (int $d) => ($mask & (1 << ($d - 1))) !== 0));
    }

    public function servesOnDay(int $isoDay): bool
    {
        return (((int) ($this->service_days ?? self::ALL_DAYS)) & (1 << ($isoDay - 1))) !== 0;
    }

    /** Si el puesto espera revista en ese día operativo. */
    public function expectsReviewOn(CarbonInterface $date): bool
    {
        if (! $this->servesOnDay((int) $date->isoWeekday())) {
            return false;
        }

        return $this->serves_holidays !== false || ! ColombiaHolidays::isHoliday($date);
    }

    public function serviceDaysLabel(): string
    {
        $mask = (int) ($this->service_days ?? self::ALL_DAYS);
        $days = match ($mask) {
            self::ALL_DAYS => 'Lun a Dom',
            self::WEEKDAYS => 'Lun a Vie',
            63 => 'Lun a Sáb',
            default => implode(', ', array_map(fn (int $d) => self::DAY_LABELS[$d], $this->serviceDayList())) ?: 'Sin días',
        };

        return $days.' · '.($this->serves_holidays === false ? 'sin festivos' : 'con festivos');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'supervisor_post_employee')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SupervisorShiftReview::class);
    }
}
