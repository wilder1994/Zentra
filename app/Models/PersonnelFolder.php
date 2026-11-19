<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PersonnelFolder extends Model
{
    public const STANDARD = 'standard';

    public const COURSE = 'course';

    public const OTHER = 'other';

    public const PARAFISCAL = 'parafiscal';

    protected $fillable = [
        'security_company_id',
        'slug',
        'name',
        'behavior',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function types(): HasMany
    {
        return $this->hasMany(PersonnelFolderType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function isIndexed(): bool
    {
        return $this->behavior !== self::PARAFISCAL;
    }
}
