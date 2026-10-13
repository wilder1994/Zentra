<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthorizationGuest extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id',
        'authorization_id',
        'visitor_id',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'phone',
        'entries_count',
        'first_entry_at',
        'last_entry_at',
    ];

    protected function casts(): array
    {
        return [
            'entries_count' => 'integer',
            'first_entry_at' => 'datetime',
            'last_entry_at' => 'datetime',
        ];
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(VisitorPreAuthorization::class, 'authorization_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    /** Nombre corto para la página pública del QR (sin apellido completo). */
    public function publicName(): string
    {
        $initial = $this->last_name ? ' '.mb_substr($this->last_name, 0, 1).'.' : '';

        return $this->first_name.$initial;
    }

    public function canEnter(): bool
    {
        $authorization = $this->authorization;

        return $authorization !== null
            && $authorization->state() === VisitorPreAuthorization::STATE_ACTIVE
            && ($authorization->multi_entry || $this->entries_count === 0);
    }
}
