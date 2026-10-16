<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GuardShift extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id',
        'user_id',
        'location_id',
        'shift_template_id',
        'started_at',
        'ended_at',
        'start_notes',
        'end_notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(SupervisorShiftTemplate::class, 'shift_template_id');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
