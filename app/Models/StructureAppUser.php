<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class StructureAppUser extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    public const CHANNEL_ANDROID = 'android';

    public const CHANNEL_IOS_WEB = 'ios_web';

    protected $fillable = [
        'client_id',
        'member_id',
        'username',
        'email',
        'password',
        'is_active',
        'delivery_channel',
        'last_login_at',
        'credentials_sent_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'credentials_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(StructureMember::class, 'member_id');
    }

    /** @return HasMany<StructureAppUserEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(StructureAppUserEvent::class, 'structure_app_user_id')->latest('id');
    }

    public function getLoginEmailAttribute(): string
    {
        $client = $this->client;

        return $client
            ? "{$this->username}@{$client->login_suffix}"
            : $this->username;
    }

    public function channelLabel(): string
    {
        return match ($this->delivery_channel) {
            self::CHANNEL_IOS_WEB => 'iPhone (web)',
            default => 'Android (APK)',
        };
    }

    public function isIosWeb(): bool
    {
        return $this->delivery_channel === self::CHANNEL_IOS_WEB;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
}
