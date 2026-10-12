<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientUserInstallationAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'installation_id',
        'installation_permission',
    ];

    public function isSupport(): bool
    {
        return $this->installation_permission === 'support';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
