<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StructureAppUserDevice extends Model
{
    protected $fillable = [
        'structure_app_user_id',
        'platform',
        'push_token',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(StructureAppUser::class, 'structure_app_user_id');
    }
}
