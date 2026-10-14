<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionReviewSettings extends Model
{
    public const DEFAULT_RADIUS_M = 80;

    public const DEFAULT_MAX_RADIUS_M = 300;

    protected $fillable = [
        'default_radius_m',
        'max_radius_m',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_radius_m' => 'integer',
            'max_radius_m' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return self::query()->latest('id')->first()
            ?? self::query()->create([
                'default_radius_m' => self::DEFAULT_RADIUS_M,
                'max_radius_m' => self::DEFAULT_MAX_RADIUS_M,
            ]);
    }
}
