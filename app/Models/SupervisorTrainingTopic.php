<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Tema de capacitación. Único por empresa sin importar tildes, mayúsculas ni espacios. */
final class SupervisorTrainingTopic extends Model
{
    public const FROM_PANEL = 'panel';

    public const FROM_APP = 'app';

    protected $fillable = [
        'security_company_id',
        'name',
        'normalized_name',
        'is_active',
        'sort_order',
        'created_by_user_id',
        'created_from',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function normalize(string $name): string
    {
        return Str::of(Str::ascii($name))->lower()->squish()->value();
    }

    public static function clean(string $name): string
    {
        return Str::of($name)->squish()->limit(160, '')->value();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(SupervisorTraining::class, 'supervisor_training_topic_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
