<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotaRequestKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuotaRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'security_company_id',
        'requested_by',
        'kind',
        'installation_id',
        'supervisor_post_id',
        'quantity',
        'message',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => QuotaRequestKind::class,
            'quantity' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class)->withoutGlobalScopes();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SupervisorPost::class, 'supervisor_post_id')->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Aprobada',
            self::REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }

    public function targetLabel(): string
    {
        return match ($this->kind) {
            QuotaRequestKind::Posts => $this->installation?->name ? 'Instalación «'.$this->installation->name.'»' : 'Todas las instalaciones',
            QuotaRequestKind::Assignments => $this->post?->name ? 'Puesto «'.$this->post->name.'»' : 'Todos los puestos',
            default => 'Empresa',
        };
    }
}
