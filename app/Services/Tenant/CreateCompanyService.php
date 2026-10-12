<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Geo\GeoAddressData;
use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\BillingCycle;
use App\Enums\PartyType;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyPriceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCompanyService
{
    public function __construct(
        private readonly AssignCompanyPackageService $assignCompanyPackageService,
        private readonly AssignCompanySupervisionPackageService $assignCompanySupervisionPackageService,
        private readonly CompanyPriceHistory $priceHistory,
    ) {}

    /**
     * @param  array{
     *     legal_name: string,
     *     trade_name: string,
     *     tax_id: string,
     *     party_type: string|PartyType,
     *     email: string,
     *     phone: string,
     *     manual_seats?: int|string|null,
     *     hardware_seats?: int|string|null,
     *     supervision_seats?: int|string|null,
     *     billing_cycle: string,
     * }  $attributes
     */
    public function execute(array $attributes, GeoAddressData $geo): SecurityCompany
    {
        if (SecurityCompany::query()->where('tax_id', $attributes['tax_id'])->exists()) {
            throw ValidationException::withMessages([
                'tax_id' => 'Ya existe una empresa con este identificador fiscal.',
            ]);
        }

        $seats = new AccessSeatSplit(
            (int) ($attributes['manual_seats'] ?? 0),
            (int) ($attributes['hardware_seats'] ?? 0),
        );
        $supervisionSeats = (int) ($attributes['supervision_seats'] ?? 0);

        if ($seats->isEmpty() && $supervisionSeats < 1) {
            throw ValidationException::withMessages([
                'manual_seats' => 'Indica instalaciones de Accesos o clientes de Supervisión.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $geo, $seats, $supervisionSeats): SecurityCompany {
            $partyType = $attributes['party_type'] instanceof PartyType
                ? $attributes['party_type']
                : PartyType::from((string) $attributes['party_type']);

            $company = SecurityCompany::query()->create([
                'legal_name' => $attributes['legal_name'],
                'trade_name' => $attributes['trade_name'] ?: $attributes['legal_name'],
                'tax_id' => $attributes['tax_id'],
                'party_type' => $partyType,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                ...$geo->toModelAttributes(),
                'is_active' => true,
            ]);

            $before = $this->priceHistory->snapshot($company);
            $cycle = BillingCycle::from((string) $attributes['billing_cycle']);
            $this->assignCompanyPackageService->execute($company, $seats->sku(), $cycle, null, $seats);

            if ($supervisionSeats > 0) {
                $this->assignCompanySupervisionPackageService->execute(
                    $company->fresh(),
                    SupervisionPackageSku::fromSize($supervisionSeats),
                );
            }

            $this->priceHistory->record($company, $before, 'signup');

            return $company->fresh();
        });
    }
}
