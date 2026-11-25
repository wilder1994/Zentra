<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ObservatoryCrimeFigure extends Model
{
    protected $fillable = [
        'security_company_id',
        'observatory_crime_type_id',
        'comuna_code',
        'year',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ObservatoryCrimeType::class, 'observatory_crime_type_id');
    }
}
