<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/** API key de integración: token Sanctum de un usuario de la empresa con permisos por endpoint. */
final class ApiIntegrationKey extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_USER_INACTIVE = 'user_inactive';

    protected $fillable = [
        'security_company_id',
        'user_id',
        'personal_access_token_id',
        'name',
        'abilities',
        'token_hint',
        'expires_at',
        'regenerated_at',
        'revoked_at',
        'revoked_by_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'regenerated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null, $this->personal_access_token_id === null, $this->token === null => self::STATUS_REVOKED,
            $this->expires_at !== null && $this->expires_at->isPast() => self::STATUS_EXPIRED,
            ! (bool) $this->user?->is_active => self::STATUS_USER_INACTIVE,
            default => self::STATUS_ACTIVE,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::STATUS_ACTIVE => 'Activa',
            self::STATUS_EXPIRED => 'Vencida',
            self::STATUS_USER_INACTIVE => 'Usuario inactivo',
            default => 'Revocada',
        };
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
