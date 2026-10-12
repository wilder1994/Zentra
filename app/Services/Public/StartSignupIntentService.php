<?php

declare(strict_types=1);

namespace App\Services\Public;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\SignupIntentStatus;
use App\Enums\SupervisionPackageSku;
use App\Models\CommercialSignupIntent;
use App\Services\Pricing\PriceCalculator;
use App\Support\Catalog\CatalogSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StartSignupIntentService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
    ) {}

    public function execute(
        AccessSeatSplit $seats,
        BillingCycle $cycle,
        ?SupervisionPackageSku $supervisionSku = null,
    ): CommercialSignupIntent {
        if ($seats->isEmpty() && $supervisionSku === null) {
            throw new InvalidArgumentException('Elige instalaciones de Accesos o clientes de Supervisión.');
        }

        $this->assertAvailable($seats, $supervisionSku);

        $quote = $this->priceCalculator->quoteAccess($seats, $cycle);
        $amount = $quote->amountDue();

        if ($supervisionSku !== null) {
            $amount += $this->priceCalculator->quoteSupervisionSku($supervisionSku, $cycle)->amountDue();
        }

        $now = CarbonImmutable::now();

        return CommercialSignupIntent::query()->create([
            'token' => (string) Str::uuid(),
            'status' => SignupIntentStatus::Draft,
            'package_sku' => $seats->sku(),
            'package_manual_seats' => $seats->manual,
            'package_hardware_seats' => $seats->hardware,
            'supervision_package_sku' => $supervisionSku,
            'billing_cycle' => $cycle,
            'amount' => round($amount, 2),
            'currency' => $quote->currency,
            'expires_at' => $now->addHours((int) config('billing.signup_intent_ttl_hours', 24)),
        ]);
    }

    private function assertAvailable(AccessSeatSplit $seats, ?SupervisionPackageSku $supervisionSku): void
    {
        $catalog = CatalogSettings::current();

        if ($seats->hardware > 0 && ! $catalog->hardwareAvailable()) {
            throw new InvalidArgumentException('La modalidad con hardware aún no está disponible.');
        }

        foreach ([$seats->metal(), $supervisionSku?->metal()] as $metal) {
            if ($metal instanceof CommercialMetal && ! $catalog->isMetalAvailable($metal)) {
                throw new InvalidArgumentException("El plan {$metal->label()} no está disponible.");
            }
        }
    }
}
