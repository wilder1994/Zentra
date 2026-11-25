<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ObservatoryCrimeType extends Model
{
    protected $fillable = [
        'security_company_id',
        'name',
        'slug',
        'color',
        'impacto',
        'probabilidad',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'impacto' => 'integer',
            'probabilidad' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function figures(): HasMany
    {
        return $this->hasMany(ObservatoryCrimeFigure::class);
    }
}
