<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zona añadida a un turno abierto desde En vivo. Dura solo ese turno. */
final class SupervisorShiftZone extends Model
{
    protected $fillable = [
        'supervisor_shift_id',
        'supervisor_zone_id',
        'added_by_user_id',
        'added_at',
        'removed_by_user_id',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SupervisorShift::class, 'supervisor_shift_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(SupervisorZone::class, 'supervisor_zone_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }
}
