<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Correspondence extends Model
{
    use BelongsToClient, HasFactory, SoftDeletes;

    public const TYPES = [
        'documento' => 'Documento',
        'sobre' => 'Sobre',
        'caja' => 'Caja',
        'otro' => 'Otro',
    ];

    protected $table = 'correspondence';

    protected $fillable = [
        'client_id', 'visitor_id', 'host_id', 'location_id', 'housing_unit_id', 'resident_id',
        'structure_id', 'structure_member_id', 'recipient_text',
        'carrier', 'courier_guide', 'package_type', 'received_at', 'received_by',
        'delivered_at', 'delivered_by', 'delivered_to_member_id', 'delivered_to_name', 'delivered_to_document',
        'status', 'photo_path', 'delivery_photo_path', 'notes',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function housingUnit()
    {
        return $this->belongsTo(HousingUnit::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    public function structureMember()
    {
        return $this->belongsTo(StructureMember::class);
    }

    public function deliveredToMember()
    {
        return $this->belongsTo(StructureMember::class, 'delivered_to_member_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function deliverer()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->package_type] ?? ucfirst((string) $this->package_type);
    }

    public function unitLabel(): string
    {
        return $this->structure?->name
            ?? $this->housingUnit?->full_label
            ?? '—';
    }

    public function recipientLabel(): string
    {
        return $this->structureMember?->full_name
            ?? $this->resident?->full_name
            ?? $this->host?->name
            ?? (is_string($this->recipient_text) && $this->recipient_text !== '' ? $this->recipient_text : null)
            ?? 'Toda la unidad';
    }

    public function deliveredToLabel(): string
    {
        $name = $this->deliveredToMember?->full_name ?? $this->delivered_to_name;
        if (! is_string($name) || $name === '') {
            return '—';
        }

        return $this->delivered_to_document ? $name.' · '.$this->delivered_to_document : $name;
    }

    public function photoUrl(): ?string
    {
        return is_string($this->photo_path) && $this->photo_path !== '' ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function deliveryPhotoUrl(): ?string
    {
        return is_string($this->delivery_photo_path) && $this->delivery_photo_path !== '' ? Storage::disk('public')->url($this->delivery_photo_path) : null;
    }
}
