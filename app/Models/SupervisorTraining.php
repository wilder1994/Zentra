<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupervisorTrainingKind;
use App\Enums\SupervisorTrainingModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Capacitación dada a un vigilante en una revista. Un tema por vigilante y mes (`period` = Y-m). */
final class SupervisorTraining extends Model
{
    protected $fillable = [
        'security_company_id',
        'supervisor_field_log_id',
        'supervisor_shift_review_id',
        'supervisor_shift_id',
        'employee_id',
        'supervisor_training_topic_id',
        'user_id',
        'client_id',
        'supervisor_zone_id',
        'kind',
        'modality',
        'started_at',
        'ended_at',
        'minutes',
        'period',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SupervisorTrainingKind::class,
            'modality' => SupervisorTrainingModality::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SupervisorTrainingTopic::class, 'supervisor_training_topic_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(SupervisorFieldLog::class, 'supervisor_field_log_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(SupervisorShiftReview::class, 'supervisor_shift_review_id');
    }
}
