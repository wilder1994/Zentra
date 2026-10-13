<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CommonZoneBooking extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id', 'installation_id', 'common_zone_id', 'user_id', 'housing_unit_id',
        'structure_id', 'structure_member_id', 'date', 'date_until', 'full_day',
        'start_time', 'end_time', 'people_count', 'guests', 'status', 'origin',
        'reviewed_by', 'reviewed_at', 'review_note', 'authorization_id',
        'qr_code', 'checked_in_at', 'cancelled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'date_until' => 'date',
            'full_day' => 'boolean',
            'people_count' => 'integer',
            'guests' => 'array',
            'status' => BookingStatus::class,
            'reviewed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(CommonZone::class, 'common_zone_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(StructureMember::class, 'structure_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(VisitorPreAuthorization::class, 'authorization_id');
    }

    public function lastDate(): Carbon
    {
        return $this->date_until ?? $this->date;
    }

    public function covers(Carbon $day): bool
    {
        return $day->betweenIncluded($this->date->copy()->startOfDay(), $this->lastDate()->copy()->endOfDay());
    }

    public function startTime(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function endTime(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function hoursLabel(): string
    {
        return $this->full_day ? 'Todo el día' : $this->startTime().' – '.$this->endTime();
    }

    public function datesLabel(): string
    {
        $from = $this->date->translatedFormat('j M Y');
        if ($this->date_until === null || $this->date_until->isSameDay($this->date)) {
            return $from;
        }

        return $this->date->translatedFormat('j M').' – '.$this->date_until->translatedFormat('j M Y');
    }

    public function unitLabel(): string
    {
        return $this->structure?->name ?? 'Sin unidad';
    }

    public function requesterLabel(): string
    {
        return $this->member !== null
            ? trim($this->member->first_name.' '.$this->member->last_name)
            : ($this->user?->name ?? 'Administración');
    }

    public function guestsCount(): int
    {
        return count($this->guests ?? []);
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }
}
