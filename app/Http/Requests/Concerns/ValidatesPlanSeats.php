<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Enums\CompanyPackageSku;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Support\Catalog\CatalogSettings;
use Illuminate\Validation\Validator;

/**
 * Formularios de plan por cantidad: `manual_seats`, `hardware_seats` (Accesos), `supervision_seats`,
 * `indexing_seats` y `observatory_seats`. Todos cuentan instalaciones.
 */
trait ValidatesPlanSeats
{
    /** @return array<string, mixed> */
    protected function planSeatRules(): array
    {
        $max = CompanyPackageSku::MAX_SEATS;

        return [
            'manual_seats' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'hardware_seats' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'supervision_seats' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'indexing_seats' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'observatory_seats' => ['nullable', 'integer', 'min:0', 'max:'.$max],
        ];
    }

    /**
     * @param  bool  $public  Respeta disponibilidad de planes y Hardware (empresa y registro).
     */
    protected function validatePlanSeats(Validator $validator, bool $public, ?SecurityCompany $current = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $manual = (int) $this->input('manual_seats', 0);
        $hardware = (int) $this->input('hardware_seats', 0);

        if ($manual + $hardware > CompanyPackageSku::MAX_SEATS) {
            $validator->errors()->add('manual_seats', 'El cupo de Accesos supera el máximo permitido.');

            return;
        }

        $selection = $this->planSelection($current);
        if ($selection->isEmpty()) {
            $validator->errors()->add('manual_seats', 'Elige al menos un producto: Accesos, Supervisión, Indexación u Observatorio.');

            return;
        }

        if (! $public) {
            return;
        }

        $catalog = CatalogSettings::current();

        if ($hardware > 0 && ! $catalog->hardwareAvailable() && (int) ($current?->package_hardware_seats ?? 0) < 1) {
            $validator->errors()->add('hardware_seats', 'La modalidad con hardware aún no está disponible.');
        }

        $fields = [
            CommercialProduct::Access->value => 'manual_seats',
            CommercialProduct::Supervision->value => 'supervision_seats',
            CommercialProduct::Indexing->value => 'indexing_seats',
            CommercialProduct::Observatory->value => 'observatory_seats',
        ];
        $owned = $current !== null ? PlanSelection::fromCompany($current) : null;

        foreach (CommercialProduct::cases() as $product) {
            $metal = $selection->metal($product);
            if ($metal instanceof CommercialMetal && ! $catalog->isMetalAvailable($metal) && $owned?->metal($product) !== $metal) {
                $validator->errors()->add($fields[$product->value], "El plan {$metal->label()} de {$product->label()} no está disponible.");
            }
        }
    }

    public function accessSeats(): AccessSeatSplit
    {
        return new AccessSeatSplit(
            (int) $this->input('manual_seats', 0),
            (int) $this->input('hardware_seats', 0),
        );
    }

    public function supervisionSku(): ?SupervisionPackageSku
    {
        $seats = (int) $this->input('supervision_seats', 0);

        return $seats > 0 ? SupervisionPackageSku::fromSize($seats) : null;
    }

    /** Un producto que el formulario no envía conserva la cantidad actual de la empresa. */
    public function planSelection(?SecurityCompany $current = null): PlanSelection
    {
        $owned = $current !== null ? PlanSelection::fromCompany($current) : null;
        $seats = fn (string $key, int $fallback): int => $this->has($key) ? (int) $this->input($key, 0) : $fallback;

        return new PlanSelection(
            $this->accessSeats(),
            $seats('supervision_seats', $owned->supervision ?? 0),
            $seats('indexing_seats', $owned->indexing ?? 0),
            $seats('observatory_seats', $owned->observatory ?? 0),
        );
    }

    /** @return array<string, string> */
    protected function planSeatAttributes(): array
    {
        return [
            'manual_seats' => 'instalaciones de Accesos',
            'hardware_seats' => 'instalaciones con hardware',
            'supervision_seats' => 'instalaciones de Supervisión',
            'indexing_seats' => 'instalaciones de Indexación',
            'observatory_seats' => 'instalaciones de Observatorio',
        ];
    }
}
