<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use BelongsToClient, HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'installation_id',
        'code',
        'name',
        'address',
        'phone',
        'latitude',
        'longitude',
        'geo_radius_m',
        'type',
        'is_active',
        'archived_at',
        'archived_by_service_line',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
            'archived_by_service_line' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geo_radius_m' => 'integer',
        ];
    }

    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(AccessLog::class);
    }

    public function preAuthorizations()
    {
        return $this->hasMany(PreAuthorization::class);
    }

    public function guardLogs()
    {
        return $this->hasMany(GuardLog::class);
    }

    public function correspondence()
    {
        return $this->hasMany(Correspondence::class);
    }
}
