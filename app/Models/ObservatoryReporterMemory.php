<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ObservatoryReporterMemory extends Model
{
    protected $fillable = [
        'client_id',
        'phone',
        'contact_name',
        'role',
        'is_adult',
        'remember',
        'authorization_accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_adult' => 'boolean',
            'remember' => 'boolean',
            'authorization_accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
