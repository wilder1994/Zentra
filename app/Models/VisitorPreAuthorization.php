<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationType;
use App\Enums\VisitorCategory;
use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class VisitorPreAuthorization extends Model
{
    use BelongsToClient, SoftDeletes;

    public const STATE_ACTIVE = 'active';

    public const STATE_UPCOMING = 'upcoming';

    public const STATE_USED = 'used';

    public const STATE_EXPIRED = 'expired';

    public const STATE_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'installation_id',
        'structure_id',
        'type',
        'origin',
        'destination_text',
        'member_id',
        'authorized_text',
        'visitor_name',
        'visitor_document',
        'visitor_category',
        'valid_for_date',
        'valid_from',
        'valid_until',
        'multi_entry',
        'vehicle_plate',
        'qr_auth_token',
        'status',
        'notes',
        'created_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'visitor_category' => VisitorCategory::class,
            'status' => AuthorizationStatus::class,
            'type' => AuthorizationType::class,
            'valid_for_date' => 'date',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'cancelled_at' => 'datetime',
            'multi_entry' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(StructureMember::class, 'member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(AuthorizationGuest::class, 'authorization_id');
    }

    public function state(): string
    {
        if ($this->status === AuthorizationStatus::Cancelled) {
            return self::STATE_CANCELLED;
        }
        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return self::STATE_EXPIRED;
        }
        if ($this->status === AuthorizationStatus::Processed) {
            return self::STATE_USED;
        }
        if ($this->valid_from !== null && $this->valid_from->isFuture()) {
            return self::STATE_UPCOMING;
        }

        return self::STATE_ACTIVE;
    }

    public function stateLabel(): string
    {
        return match ($this->state()) {
            self::STATE_ACTIVE => 'Vigente',
            self::STATE_UPCOMING => 'Próxima',
            self::STATE_USED => 'Usada',
            self::STATE_EXPIRED => 'Vencida',
            default => 'Cancelada',
        };
    }

    public function typeLabel(): string
    {
        return $this->type?->label() ?? 'Visita';
    }

    public function destinationLabel(): string
    {
        return $this->structure?->name
            ?? (is_string($this->destination_text) && $this->destination_text !== '' ? $this->destination_text : '—');
    }

    public function authorizerLabel(): string
    {
        return $this->member?->full_name
            ?? (is_string($this->authorized_text) && $this->authorized_text !== '' ? $this->authorized_text : 'Administración');
    }

    public function validityLabel(): string
    {
        $from = $this->valid_from;
        $until = $this->valid_until;
        if ($from === null || $until === null) {
            return $this->valid_for_date?->format('d/m/Y') ?? '—';
        }
        if ($from->isSameDay($until)) {
            return $from->format('d/m/Y');
        }

        return $from->format('d/m').' al '.$until->format('d/m/Y');
    }

    public function stateMessage(): string
    {
        return match ($this->state()) {
            self::STATE_EXPIRED => 'Autorización vencida el '.$this->valid_until?->format('d/m/Y').' · confirma con la unidad.',
            self::STATE_USED => 'La autorización ya fue usada · confirma con la unidad.',
            self::STATE_CANCELLED => 'La autorización fue cancelada · confirma con la unidad.',
            self::STATE_UPCOMING => 'La autorización empieza el '.$this->valid_from?->format('d/m/Y').' · confirma con la unidad.',
            default => 'Vigente hasta '.$this->valid_until?->format('d/m/Y H:i').'.',
        };
    }
}
