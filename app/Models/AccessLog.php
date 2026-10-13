<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AccessLog extends Model
{
    use BelongsToClient, HasFactory;

    protected $fillable = [
        'client_id', 'visitor_id', 'structure_member_id', 'user_id', 'resident_id', 'housing_unit_id', 'vehicle_id', 'host_id', 'location_id',
        'destination_structure_id', 'destination_text', 'authorized_member_id', 'authorized_text',
        'authorized_by', 'access_type', 'entry_time', 'exit_time', 'status',
        'purpose', 'company_visited', 'screening_temp', 'qr_code', 'notes',
        'photo_path', 'vehicle_photo_path',
        'has_custody', 'custody_description', 'custody_receiver_name', 'custody_received_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_time' => 'datetime',
            'exit_time' => 'datetime',
            'screening_temp' => 'decimal:1',
            'has_custody' => 'boolean',
            'custody_received_at' => 'datetime',
        ];
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function structureMember()
    {
        return $this->belongsTo(StructureMember::class, 'structure_member_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function housingUnit()
    {
        return $this->belongsTo(HousingUnit::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function destinationStructure()
    {
        return $this->belongsTo(Structure::class, 'destination_structure_id');
    }

    public function authorizedMember()
    {
        return $this->belongsTo(StructureMember::class, 'authorized_member_id');
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function subjectName(): string
    {
        return $this->structureMember?->full_name
            ?? $this->visitor?->full_name
            ?? $this->resident?->full_name
            ?? $this->user?->name
            ?? '—';
    }

    public function movementLabel(): string
    {
        return match ($this->access_type) {
            'visitor_vehicle' => 'Visitante · vehículo',
            'resident_vehicle', 'member_vehicle' => 'Censo · vehículo',
            'resident', 'member' => 'Censo · persona',
            default => 'Visitante · persona',
        };
    }

    public function destinationLabel(): string
    {
        return $this->destinationStructure?->name
            ?? (is_string($this->destination_text) && $this->destination_text !== '' ? $this->destination_text : '—');
    }

    public function authorizerLabel(): string
    {
        return $this->authorizedMember?->full_name
            ?? (is_string($this->authorized_text) && $this->authorized_text !== '' ? $this->authorized_text : '—');
    }

    public function isVehicleMovement(): bool
    {
        return in_array($this->access_type, ['visitor_vehicle', 'member_vehicle', 'resident_vehicle'], true);
    }

    public function fichaPhotoUrl(): ?string
    {
        $path = $this->isVehicleMovement()
            ? ($this->vehicle?->photo_path ?: $this->vehicle_photo_path)
            : ($this->structureMember?->photo_path ?: $this->visitor?->photo_path ?: $this->photo_path);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
