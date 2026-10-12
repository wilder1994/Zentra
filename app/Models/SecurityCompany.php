<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\ArchiveReason;
use App\Enums\BillingCycle;
use App\Enums\ClientLifecycle;
use App\Enums\CommercialMetal;
use App\Enums\CompanyPackageSku;
use App\Enums\PackageModality;
use App\Enums\PartyType;
use App\Enums\SubscriptionStatus;
use App\Enums\SupervisionPackageSku;
use App\Support\Tenancy\CompanyPackage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecurityCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'tax_id',
        'party_type',
        'email',
        'phone',
        'address',
        'city',
        'department',
        'latitude',
        'longitude',
        'logo_path',
        'field_sheet_intro',
        'is_active',
        'package_size',
        'package_manual_seats',
        'package_hardware_seats',
        'package_modality',
        'package_sku',
        'supervision_package_sku',
        'supervision_unlimited',
        'package_price_monthly',
        'max_clients',
        'has_indexing',
        'has_observatory',
        'max_supervision_clients',
        'supervision_package_size',
        'billing_cycle',
        'billing_day',
        'unit_price_snapshot',
        'supervision_unit_price_snapshot',
        'volume_discount_pct',
        'annual_discount_pct',
        'package_price_annual',
        'supervision_package_price_monthly',
        'supervision_package_price_annual',
        'package_starts_at',
        'package_ends_at',
        'grace_ends_at',
        'suspended_at',
        'archived_at',
        'archive_reason',
        'commercial_anonymized_at',
        'subscription_status',
        'cancel_at_period_end',
        'cancelled_at',
        'cancellation_reason',
        'scheduled_package_sku',
        'scheduled_manual_seats',
        'scheduled_hardware_seats',
        'scheduled_supervision_package_sku',
        'scheduled_billing_cycle',
        'scheduled_change_at',
        'scheduled_change_payment_id',
        'price_locked',
        'price_locked_until',
        'price_lock_note',
        'pending_reprice_at',
        'pending_reprice_reason',
        'pending_reprice_note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'package_size' => 'integer',
            'package_manual_seats' => 'integer',
            'package_hardware_seats' => 'integer',
            'package_modality' => PackageModality::class,
            'party_type' => PartyType::class,
            'package_sku' => CompanyPackageSku::class,
            'supervision_package_sku' => SupervisionPackageSku::class,
            'supervision_unlimited' => 'boolean',
            'package_price_monthly' => 'decimal:2',
            'max_clients' => 'integer',
            'has_indexing' => 'boolean',
            'has_observatory' => 'boolean',
            'max_supervision_clients' => 'integer',
            'supervision_package_size' => 'integer',
            'billing_cycle' => BillingCycle::class,
            'billing_day' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
            'supervision_unit_price_snapshot' => 'decimal:2',
            'volume_discount_pct' => 'decimal:4',
            'annual_discount_pct' => 'decimal:4',
            'package_price_annual' => 'decimal:2',
            'supervision_package_price_monthly' => 'decimal:2',
            'supervision_package_price_annual' => 'decimal:2',
            'package_starts_at' => 'datetime',
            'package_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'archive_reason' => ArchiveReason::class,
            'commercial_anonymized_at' => 'datetime',
            'subscription_status' => SubscriptionStatus::class,
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'scheduled_change_at' => 'datetime',
            'price_locked' => 'boolean',
            'price_locked_until' => 'date',
            'pending_reprice_at' => 'datetime',
        ];
    }

    /** Precio pactado vigente: queda fuera de cambios de catálogo e incrementos. */
    public function isPriceLocked(?CarbonImmutable $now = null): bool
    {
        if (! $this->price_locked) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return $this->price_locked_until === null
            || $this->price_locked_until->endOfDay()->greaterThanOrEqualTo($now);
    }

    public function hasPendingReprice(): bool
    {
        return $this->pending_reprice_at !== null && ! $this->isPriceLocked();
    }

    public function isRepriceDue(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->hasPendingReprice() && $this->pending_reprice_at->lessThanOrEqualTo($now);
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(CompanyPriceChange::class);
    }

    public function isUpToDate(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        if ($this->package_ends_at === null) {
            return false;
        }

        return $this->package_ends_at->greaterThan($now);
    }

    public function hasPendingCancellation(): bool
    {
        return (bool) $this->cancel_at_period_end && $this->cancelled_at !== null;
    }

    /** Cancelación programada y el periodo aún no vence: se puede deshacer sin pago. */
    public function canUndoCancellation(?CarbonImmutable $now = null): bool
    {
        return $this->hasPendingCancellation() && $this->isUpToDate($now);
    }

    /** Tras cancelar (o con flags de baja) y periodo vencido: reactivar exige pago. */
    public function needsPaidReactivation(?CarbonImmutable $now = null): bool
    {
        return $this->hasPendingCancellation() && ! $this->isUpToDate($now);
    }

    public function hasScheduledPackageChange(): bool
    {
        return $this->scheduled_change_at !== null
            && ($this->scheduled_package_sku !== null || $this->scheduled_supervision_package_sku !== null);
    }

    public function accessSeats(): AccessSeatSplit
    {
        $sku = CompanyPackage::skuOf($this);
        $manual = $this->package_manual_seats;
        $hardware = $this->package_hardware_seats;

        if ($manual === null && $hardware === null) {
            return $sku !== null ? AccessSeatSplit::fromSku($sku) : AccessSeatSplit::none();
        }

        return new AccessSeatSplit((int) ($manual ?? 0), (int) ($hardware ?? 0));
    }

    public function packageLabel(): string
    {
        $seats = $this->accessSeats();
        if (! $seats->isEmpty()) {
            return $seats->label();
        }

        return $this->package_sku !== null ? 'Sin Accesos' : 'Sin paquete asignado';
    }

    public function accessMetal(): ?CommercialMetal
    {
        return $this->accessSeats()->metal();
    }

    public function supervisionMetal(): ?CommercialMetal
    {
        return $this->supervision_package_sku?->metal();
    }

    public function hasAccessPackage(): bool
    {
        return ! $this->accessSeats()->isEmpty();
    }

    public function sameAccessAs(AccessSeatSplit $seats, BillingCycle $cycle): bool
    {
        return $this->package_sku === $seats->sku()
            && $this->billing_cycle === $cycle
            && (int) $this->package_manual_seats === $seats->manual
            && (int) $this->package_hardware_seats === $seats->hardware;
    }

    public function hasUnlimitedSupervision(): bool
    {
        return (bool) $this->supervision_unlimited;
    }

    public function hasSupervisionPackage(): bool
    {
        return $this->supervision_package_sku !== null
            || $this->hasUnlimitedSupervision();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasCompanyAdmin(): bool
    {
        return $this->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'company-admin'))
            ->exists();
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(CompanyJobTitle::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function subscriptionAcceptances(): HasMany
    {
        return $this->hasMany(SubscriptionAcceptance::class);
    }

    public function commercialPayments(): HasMany
    {
        return $this->hasMany(CommercialPayment::class);
    }

    public function platformDocuments(): HasMany
    {
        return $this->hasMany(PlatformDocument::class);
    }

    public function lifecycleEvidenceEvents(): HasMany
    {
        return $this->hasMany(LifecycleEvidenceEvent::class);
    }

    public function latestAcceptance(): ?SubscriptionAcceptance
    {
        return $this->subscriptionAcceptances()->latest('accepted_at')->first();
    }

    public function hasCompletedAcceptance(): bool
    {
        return $this->subscriptionAcceptances()->exists();
    }

    public function displayName(): string
    {
        return (string) ($this->trade_name ?: $this->legal_name ?: 'Empresa');
    }

    public function logoFileResponse(): ?BinaryFileResponse
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }

        $absolute = Storage::disk('local')->path($this->logo_path);
        $mime = Storage::disk('local')->mimeType($this->logo_path) ?: 'image/png';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function activeClients(): HasMany
    {
        return $this->clients()->where('is_active', true);
    }

    public function allowsFeature(string $feature): bool
    {
        return CompanyPackage::allows($this, $feature);
    }

    public function clientsRemaining(): int
    {
        $max = (int) ($this->max_clients ?: 0);

        return max(0, $max - $this->accessSeatsCount());
    }

    public function installationSeatsCount(?int $exceptInstallationId = null): int
    {
        return Installation::query()
            ->withoutGlobalScopes()
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $this->id))
            ->where('is_active', true)
            ->when($exceptInstallationId !== null, fn ($q) => $q->whereKeyNot($exceptInstallationId))
            ->count();
    }

    public function installationSeatsRemaining(): int
    {
        $max = (int) ($this->max_clients ?: 0);

        return max(0, $max - $this->installationSeatsCount());
    }

    public function supervisionSeatsRemaining(): int
    {
        if (! $this->hasSupervisionPackage()) {
            return 0;
        }

        if ($this->hasUnlimitedSupervision()) {
            return 1_000_000;
        }

        $max = (int) ($this->max_supervision_clients ?: 0);

        return max(0, $max - $this->supervisionSeatsCount());
    }

    public function operationalClientsCount(): int
    {
        return $this->accessSeatsCount();
    }

    public function accessSeatsCount(?int $exceptClientId = null): int
    {
        return $this->clients()
            ->where('lifecycle', ClientLifecycle::Active)
            ->where('has_access', true)
            ->when($exceptClientId !== null, fn ($q) => $q->whereKeyNot($exceptClientId))
            ->count();
    }

    public function supervisionSeatsCount(?int $exceptClientId = null): int
    {
        return $this->clients()
            ->where('lifecycle', ClientLifecycle::Active)
            ->where('has_supervision', true)
            ->when($exceptClientId !== null, fn ($q) => $q->whereKeyNot($exceptClientId))
            ->count();
    }

    public function contractedAmount(): float
    {
        $access = $this->billing_cycle === BillingCycle::Annual
            ? (float) ($this->package_price_annual ?? 0)
            : (float) ($this->package_price_monthly ?? 0);

        $supervision = $this->billing_cycle === BillingCycle::Annual
            ? (float) ($this->supervision_package_price_annual ?? 0)
            : (float) ($this->supervision_package_price_monthly ?? 0);

        return $access + $supervision;
    }

    public function billingPeriodLabel(): string
    {
        return $this->billing_cycle?->label() ?? 'Mensual';
    }
}
