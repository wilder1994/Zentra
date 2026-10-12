<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CompanyPackageSku;
use App\Enums\PartyType;
use App\Enums\SignupIntentStatus;
use App\Enums\SupervisionPackageSku;
use Illuminate\Database\Eloquent\Model;

class CommercialSignupIntent extends Model
{
    protected $fillable = [
        'token',
        'status',
        'package_sku',
        'package_manual_seats',
        'package_hardware_seats',
        'supervision_package_sku',
        'indexing_seats',
        'observatory_seats',
        'billing_cycle',
        'amount',
        'currency',
        'party_type',
        'legal_name',
        'trade_name',
        'tax_id',
        'admin_name',
        'email',
        'phone',
        'address',
        'city',
        'department',
        'latitude',
        'longitude',
        'password',
        'representative_name',
        'representative_role',
        'representative_document_type',
        'representative_document_number',
        'corpus_snapshot',
        'content_hash',
        'ip_address',
        'user_agent',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SignupIntentStatus::class,
            'package_sku' => CompanyPackageSku::class,
            'package_manual_seats' => 'integer',
            'package_hardware_seats' => 'integer',
            'supervision_package_sku' => SupervisionPackageSku::class,
            'indexing_seats' => 'integer',
            'observatory_seats' => 'integer',
            'billing_cycle' => BillingCycle::class,
            'party_type' => PartyType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'amount' => 'decimal:2',
            'corpus_snapshot' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isCheckoutReady(): bool
    {
        return $this->status === SignupIntentStatus::AwaitingPayment
            && ! $this->isExpired()
            && $this->password !== null
            && $this->corpus_snapshot !== null
            && $this->content_hash !== null;
    }

    public function accessSeats(): AccessSeatSplit
    {
        $manual = $this->package_manual_seats;
        $hardware = $this->package_hardware_seats;

        return $manual === null && $hardware === null && $this->package_sku !== null
            ? AccessSeatSplit::fromSku($this->package_sku)
            : new AccessSeatSplit((int) $manual, (int) $hardware);
    }

    public function planSelection(): PlanSelection
    {
        return new PlanSelection(
            $this->accessSeats(),
            (int) ($this->supervision_package_sku?->size() ?? 0),
            (int) ($this->indexing_seats ?? 0),
            (int) ($this->observatory_seats ?? 0),
        );
    }

    public function packageLabel(): string
    {
        return $this->planSelection()->label();
    }
}
