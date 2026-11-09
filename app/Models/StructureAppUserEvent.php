<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StructureAppUserEvent extends Model
{
    use BelongsToClient;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const ARCHIVED = 'archived';

    public const RESTORED = 'restored';

    public const CREDENTIALS_SENT = 'credentials_sent';

    public const LOGIN = 'login';

    protected $fillable = [
        'client_id',
        'structure_app_user_id',
        'actor_user_id',
        'event',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(StructureAppUser::class, 'structure_app_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function label(): string
    {
        return match ($this->event) {
            self::CREATED => 'Cuenta creada',
            self::UPDATED => 'Cuenta actualizada',
            self::ARCHIVED => 'Cuenta archivada',
            self::RESTORED => 'Cuenta reactivada',
            self::CREDENTIALS_SENT => 'Credenciales enviadas',
            self::LOGIN => 'Inicio de sesión',
            default => $this->event,
        };
    }
}
