<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceChangeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeviceChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'security_company_id',
        'device_id',
        'device_label',
        'previous_device_id',
        'previous_device_label',
        'latitude',
        'longitude',
        'status',
        'expires_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceChangeStatus::class,
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where('status', DeviceChangeStatus::Pendiente->value)
            ->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === DeviceChangeStatus::Pendiente && $this->expires_at->isFuture();
    }
}
