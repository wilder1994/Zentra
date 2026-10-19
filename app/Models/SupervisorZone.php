<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupervisorZone extends Model
{
    protected $fillable = [
        'security_company_id',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function securityCompany(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class);
    }

    /** Clientes cuya zona principal es esta. */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'supervisor_zone_id');
    }

    /** Clientes de otras zonas que esta zona también ve. */
    public function sharedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'supervisor_zone_shared_clients')->withTimestamps();
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
