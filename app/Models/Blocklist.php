<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Blocklist extends Model
{
    use BelongsToClient;

    protected $table = 'blocklist';

    public const TYPE_VISITOR = 'visitor';

    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_RESIDENT = 'resident';

    public const TYPE_PERSON = 'person';

    /** @var list<string> */
    public const PERSON_TYPES = [
        self::TYPE_PERSON,
        self::TYPE_VISITOR,
        self::TYPE_RESIDENT,
        Visitor::class,
        Resident::class,
    ];

    /** @var list<string> */
    public const VEHICLE_TYPES = [
        self::TYPE_VEHICLE,
        Vehicle::class,
    ];

    protected $fillable = [
        'client_id',
        'reason',
        'document_number',
        'plate',
        'display_name',
        'blockable_type',
        'blockable_id',
        'blocked_by',
        'blocked_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /** @param  Builder<self>  $query */
    public function scopeVehicles(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereIn('blockable_type', self::VEHICLE_TYPES)
                ->orWhere(function (Builder $byPlate): void {
                    $byPlate->whereNotNull('plate')->where('plate', '!=', '');
                });
        });
    }

    /** @param  Builder<self>  $query */
    public function scopePersons(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereIn('blockable_type', self::PERSON_TYPES)
                ->orWhere(function (Builder $byDoc): void {
                    $byDoc->whereNotNull('document_number')
                        ->where('document_number', '!=', '')
                        ->where(function (Builder $notVehicle): void {
                            $notVehicle->whereNull('plate')->orWhere('plate', '');
                        });
                });
        });
    }

    public function typeLabel(): string
    {
        return match ($this->normalizedType()) {
            self::TYPE_VEHICLE => 'Vehículo',
            self::TYPE_RESIDENT => 'Residente',
            self::TYPE_VISITOR => 'Visitante',
            self::TYPE_PERSON => 'Persona',
            default => class_basename((string) $this->blockable_type) ?: 'Persona',
        };
    }

    public function isVehicle(): bool
    {
        return $this->normalizedType() === self::TYPE_VEHICLE;
    }

    public function isPerson(): bool
    {
        $type = $this->normalizedType();

        return in_array($type, [self::TYPE_VISITOR, self::TYPE_RESIDENT, self::TYPE_PERSON], true);
    }

    public function normalizedType(): string
    {
        if (filled($this->plate) && blank($this->document_number) && blank($this->blockable_type)) {
            return self::TYPE_VEHICLE;
        }

        $raw = (string) $this->blockable_type;

        if ($raw === '' && filled($this->document_number)) {
            return self::TYPE_PERSON;
        }

        if (in_array($raw, self::VEHICLE_TYPES, true) || str_contains($raw, 'Vehicle')) {
            return self::TYPE_VEHICLE;
        }

        if (in_array($raw, [self::TYPE_RESIDENT, Resident::class], true) || str_contains($raw, 'Resident')) {
            return self::TYPE_RESIDENT;
        }

        if (in_array($raw, [self::TYPE_VISITOR, Visitor::class], true) || str_contains($raw, 'Visitor')) {
            return self::TYPE_VISITOR;
        }

        if ($raw === self::TYPE_PERSON || filled($this->document_number)) {
            return self::TYPE_PERSON;
        }

        return $raw !== '' ? $raw : self::TYPE_PERSON;
    }

    public function subjectLabel(): string
    {
        if (filled($this->display_name)) {
            return (string) $this->display_name;
        }

        $blocked = $this->blockable;
        if ($blocked !== null) {
            if (isset($blocked->full_name) && filled($blocked->full_name)) {
                return (string) $blocked->full_name;
            }
            if (isset($blocked->first_name)) {
                return trim(($blocked->first_name ?? '').' '.($blocked->last_name ?? ''));
            }
            if (isset($blocked->plate) && filled($blocked->plate)) {
                return (string) $blocked->plate;
            }
        }

        if (filled($this->plate)) {
            return (string) $this->plate;
        }

        if (filled($this->document_number)) {
            return (string) $this->document_number;
        }

        return '—';
    }

    public function identifierLabel(): string
    {
        if ($this->isVehicle()) {
            return filled($this->plate) ? (string) $this->plate : '—';
        }

        return filled($this->document_number) ? (string) $this->document_number : '—';
    }
}
