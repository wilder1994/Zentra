<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupervisorShiftReview extends Model
{
    protected $fillable = [
        'supervisor_shift_id',
        'supervisor_zone_id',
        'client_id',
        'supervisor_post_id',
        'employee_id',
        'guard_log_id',
        'notes',
        'sheet_intro',
        'has_novelty',
        'guard_photo_path',
        'latitude',
        'longitude',
        'recorded_at',
        'client_event_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
            'has_novelty' => 'boolean',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SupervisorShift::class, 'supervisor_shift_id');
    }

    /** Zona del cliente al registrarse (a ella cuenta la revista). */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SupervisorZone::class, 'supervisor_zone_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function guardLog(): BelongsTo
    {
        return $this->belongsTo(GuardLog::class);
    }

    public function supervisorPost(): BelongsTo
    {
        return $this->belongsTo(SupervisorPost::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fieldLogs(): HasMany
    {
        return $this->hasMany(SupervisorFieldLog::class);
    }
}
