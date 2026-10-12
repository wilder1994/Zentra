<?php

declare(strict_types=1);

namespace App\Services\Public;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Enums\SignupIntentStatus;
use App\Models\CommercialSignupIntent;
use App\Services\Pricing\PlanQuoter;
use App\Support\Catalog\CatalogSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StartSignupIntentService
{
    public function __construct(
        private readonly PlanQuoter $planQuoter,
    ) {}

    public function execute(PlanSelection $selection, BillingCycle $cycle): CommercialSignupIntent
    {
        if ($selection->isEmpty()) {
            throw new InvalidArgumentException('Elige al menos un producto: Accesos, Supervisión, Indexación u Observatorio.');
        }

        $this->assertAvailable($selection);

        $quote = $this->planQuoter->quote($selection, $cycle);
        $now = CarbonImmutable::now();

        return CommercialSignupIntent::query()->create([
            'token' => (string) Str::uuid(),
            'status' => SignupIntentStatus::Draft,
            'package_sku' => $selection->access->sku(),
            'package_manual_seats' => $selection->access->manual,
            'package_hardware_seats' => $selection->access->hardware,
            'supervision_package_sku' => $selection->supervisionSku(),
            'indexing_seats' => $selection->indexing,
            'observatory_seats' => $selection->observatory,
            'billing_cycle' => $cycle,
            'amount' => $quote['amount'],
            'currency' => $quote['currency'],
            'expires_at' => $now->addHours((int) config('billing.signup_intent_ttl_hours', 24)),
        ]);
    }

    private function assertAvailable(PlanSelection $selection): void
    {
        $catalog = CatalogSettings::current();

        if ($selection->access->hardware > 0 && ! $catalog->hardwareAvailable()) {
            throw new InvalidArgumentException('La modalidad con hardware aún no está disponible.');
        }

        foreach (CommercialProduct::cases() as $product) {
            $metal = $selection->metal($product);
            if ($metal instanceof CommercialMetal && ! $catalog->isMetalAvailable($metal)) {
                throw new InvalidArgumentException("El plan {$metal->label()} de {$product->label()} no está disponible.");
            }
        }
    }
}
