<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionReviewRadius extends Model
{
    protected $table = 'supervision_review_radii';

    protected $fillable = [
        'security_company_id',
        'client_id',
        'installation_id',
        'radius_m',
        'reason',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'security_company_id' => 'integer',
            'client_id' => 'integer',
            'installation_id' => 'integer',
            'radius_m' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScopes()->withTrashed();
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class)->withoutGlobalScopes()->withTrashed();
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function level(): string
    {
        return match (true) {
            $this->installation_id !== null => 'installation',
            $this->client_id !== null => 'client',
            default => 'company',
        };
    }

    public function levelLabel(): string
    {
        return match ($this->level()) {
            'installation' => 'Instalación',
            'client' => 'Cliente',
            default => 'Empresa',
        };
    }
}
