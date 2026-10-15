<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstallationGeoRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InstallationGeoRequest extends Model
{
    protected $fillable = [
        'security_company_id',
        'client_id',
        'installation_id',
        'requested_by',
        'supervisor_shift_id',
        'previous_latitude',
        'previous_longitude',
        'latitude',
        'longitude',
        'accuracy_m',
        'distance_m',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => InstallationGeoRequestStatus::class,
            'previous_latitude' => 'decimal:7',
            'previous_longitude' => 'decimal:7',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'integer',
            'distance_m' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InstallationGeoRequestStatus::Pending->value);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class)->withoutGlobalScopes();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasPrevious(): bool
    {
        return $this->previous_latitude !== null && $this->previous_longitude !== null;
    }
}
