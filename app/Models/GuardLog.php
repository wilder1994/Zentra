<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuardLog extends Model
{
    use BelongsToClient, HasFactory, SoftDeletes;

    /** Asuntos que el vigilante escribe en la línea del libro. */
    public const SUBJECTS = [
        'general' => 'General',
        'novedad' => 'Novedad',
        'incidente' => 'Incidente',
        'turno' => 'Cambio de turno',
    ];

    public const LABELS = self::SUBJECTS + [
        'revista' => 'Revista supervisor',
        'correccion' => 'Corrección',
    ];

    protected $fillable = ['client_id', 'user_id', 'location_id', 'log_time', 'type', 'shift_type', 'description', 'latitude', 'longitude', 'signed_at', 'is_panic', 'resolved_at', 'supervision_code_id', 'supervisor_name', 'corrects_guard_log_id'];

    protected function casts(): array
    {
        return [
            'log_time' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'signed_at' => 'datetime',
            'is_panic' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function supervisionCode()
    {
        return $this->belongsTo(SupervisionCode::class);
    }

    public function corrects()
    {
        return $this->belongsTo(self::class, 'corrects_guard_log_id');
    }

    public function corrections()
    {
        return $this->hasMany(self::class, 'corrects_guard_log_id');
    }

    public function subjectLabel(): string
    {
        return $this->is_panic ? 'Pánico' : (self::LABELS[$this->type] ?? ucfirst((string) $this->type));
    }
}
