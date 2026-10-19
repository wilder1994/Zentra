<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupervisorRecommendationPriority;
use App\Enums\SupervisorRecommendationStatus;
use App\Enums\SupervisorRiskImpact;
use App\Enums\SupervisorRiskLevel;
use App\Enums\SupervisorRiskLikelihood;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupervisorRecommendation extends Model
{
    protected $fillable = [
        'security_company_id',
        'supervisor_zone_id',
        'client_id',
        'opened_by_user_id',
        'opened_shift_id',
        'closed_by_user_id',
        'closed_shift_id',
        'status',
        'priority',
        'due_date',
        'supervisor_risk_type_id',
        'risk_type',
        'body',
        'risk',
        'likelihood',
        'impact',
        'consequence',
        'treatment',
        'risk_level',
        'photos',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupervisorRecommendationStatus::class,
            'priority' => SupervisorRecommendationPriority::class,
            'likelihood' => SupervisorRiskLikelihood::class,
            'impact' => SupervisorRiskImpact::class,
            'risk_level' => SupervisorRiskLevel::class,
            'due_date' => 'date',
            'photos' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function openedShift(): BelongsTo
    {
        return $this->belongsTo(SupervisorShift::class, 'opened_shift_id');
    }

    public function riskType(): BelongsTo
    {
        return $this->belongsTo(SupervisorRiskType::class, 'supervisor_risk_type_id');
    }

    public function fieldLogs(): HasMany
    {
        return $this->hasMany(SupervisorFieldLog::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== SupervisorRecommendationStatus::Closed
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
