<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPriceChange extends Model
{
    protected $fillable = [
        'security_company_id',
        'reason',
        'billing_cycle',
        'previous_amount',
        'new_amount',
        'previous_plan',
        'new_plan',
        'note',
        'effective_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'effective_at' => 'datetime',
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

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'signup' => 'Alta',
            'plan_change' => 'Cambio de plan',
            'quota_expansion' => 'Ampliación de cupo',
            'catalog' => 'Cambio de catálogo',
            'annual_increase' => 'Incremento anual',
            'plans_migration' => 'Migración a planes',
            'admin' => 'Ajuste de plataforma',
            default => ucfirst(str_replace('_', ' ', (string) $this->reason)),
        };
    }
}
