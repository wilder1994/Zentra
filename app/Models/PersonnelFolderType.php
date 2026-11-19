<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PersonnelFolderType extends Model
{
    protected $fillable = [
        'personnel_folder_id',
        'security_company_id',
        'slug',
        'name',
        'requirement',
        'is_repeatable',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(PersonnelFolder::class, 'personnel_folder_id');
    }
}
