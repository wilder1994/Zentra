<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommonZone extends Model
{
    use BelongsToClient, SoftDeletes;

    public const MODE_EXCLUSIVE = 'exclusive';

    public const MODE_SHARED = 'shared';

    public const TYPES = [
        'salon' => 'Salón social',
        'bbq' => 'Zona BBQ',
        'piscina' => 'Piscina',
        'gimnasio' => 'Gimnasio',
        'cancha' => 'Cancha deportiva',
        'parque' => 'Zona verde / parque',
        'biblioteca' => 'Sala de estudio',
        'otro' => 'Otra zona',
    ];

    public const WEEKDAYS = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

    protected $fillable = [
        'client_id', 'installation_id', 'name', 'description', 'rules', 'type', 'booking_mode', 'capacity',
        'requires_approval', 'open_time', 'close_time', 'allow_full_day', 'weekdays',
        'min_minutes', 'max_minutes', 'buffer_minutes', 'max_advance_days', 'max_per_unit_month',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'requires_approval' => 'boolean',
            'allow_full_day' => 'boolean',
            'weekdays' => 'array',
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'max_per_unit_month' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CommonZoneBooking::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Zona común';
    }

    public function isShared(): bool
    {
        return $this->booking_mode === self::MODE_SHARED;
    }

    public function openTime(): string
    {
        return substr((string) ($this->open_time ?: '06:00'), 0, 5);
    }

    public function closeTime(): string
    {
        return substr((string) ($this->close_time ?: '22:00'), 0, 5);
    }

    /** @return list<int> ISO 1 (lunes) … 7 (domingo) */
    public function allowedWeekdays(): array
    {
        $days = array_map('intval', (array) ($this->weekdays ?? []));

        return $days === [] ? array_keys(self::WEEKDAYS) : $days;
    }

    public function weekdaysLabel(): string
    {
        $days = $this->allowedWeekdays();
        if (count($days) === 7) {
            return 'Todos los días';
        }

        return implode(' ', array_map(fn (int $d) => self::WEEKDAYS[$d], $days));
    }

    public function scheduleLabel(): string
    {
        return $this->openTime().' – '.$this->closeTime();
    }
}
