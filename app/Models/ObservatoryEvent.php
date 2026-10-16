<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObservatoryEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ObservatoryEvent extends Model
{
    protected $fillable = [
        'client_id',
        'installation_id',
        'status',
        'title',
        'opened_at',
        'closed_at',
        'closed_by_user_id',
        'archived_at',
        'archived_by_service_line',
    ];

    protected function casts(): array
    {
        return [
            'status' => ObservatoryEventStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'archived_by_service_line' => 'boolean',
        ];
    }

    public function folio(): string
    {
        return 'EV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return $this->status instanceof ObservatoryEventStatus
            ? $this->status->label()
            : '—';
    }

    public function canMoveTo(ObservatoryEventStatus $status): bool
    {
        return $this->status instanceof ObservatoryEventStatus
            && $this->status->canTransitionTo($status);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ObservatoryReport::class, 'event_id')->orderByDesc('id');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(ObservatoryReport::class, 'event_id')->latestOfMany();
    }

    public function kindLabel(): string
    {
        $report = $this->relationLoaded('latestReport')
            ? $this->latestReport
            : ($this->relationLoaded('reports') ? $this->reports->first() : $this->latestReport()->first());

        return $report instanceof ObservatoryReport ? $report->kindLabel() : '—';
    }

    public function canAddNote(): bool
    {
        return $this->status === ObservatoryEventStatus::EnAtencion;
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ObservatoryEventStatusLog::class, 'event_id')->orderByDesc('id');
    }
}
