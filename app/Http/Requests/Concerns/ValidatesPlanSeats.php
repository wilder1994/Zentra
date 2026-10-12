<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\CommercialMetal;
use App\Enums\CompanyPackageSku;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Support\Catalog\CatalogSettings;
use Illuminate\Validation\Validator;

/**
 * Formularios de plan por cantidad: `manual_seats`, `hardware_seats` (Accesos) y `supervision_seats`.
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
        $supervision = (int) $this->input('supervision_seats', 0);

        if ($manual + $hardware < 1 && $supervision < 1) {
            $validator->errors()->add('manual_seats', 'Indica instalaciones de Accesos o clientes de Supervisión.');

            return;
        }

        if ($manual + $hardware > CompanyPackageSku::MAX_SEATS) {
            $validator->errors()->add('manual_seats', 'El cupo de Accesos supera el máximo permitido.');

            return;
        }

        if (! $public) {
            return;
        }

        $catalog = CatalogSettings::current();

        if ($hardware > 0 && ! $catalog->hardwareAvailable() && (int) ($current?->package_hardware_seats ?? 0) < 1) {
            $validator->errors()->add('hardware_seats', 'La modalidad con hardware aún no está disponible.');
        }

        $accessMetal = CommercialMetal::forSeats($manual + $hardware);
        if ($accessMetal !== null && ! $catalog->isMetalAvailable($accessMetal) && $current?->accessMetal() !== $accessMetal) {
            $validator->errors()->add('manual_seats', "El plan {$accessMetal->label()} no está disponible.");
        }

        $supMetal = CommercialMetal::forSeats($supervision);
        if ($supMetal !== null && ! $catalog->isMetalAvailable($supMetal) && $current?->supervisionMetal() !== $supMetal) {
            $validator->errors()->add('supervision_seats', "El plan {$supMetal->label()} de Supervisión no está disponible.");
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

    /** @return array<string, string> */
    protected function planSeatAttributes(): array
    {
        return [
            'manual_seats' => 'instalaciones de Accesos',
            'hardware_seats' => 'instalaciones con hardware',
            'supervision_seats' => 'clientes de Supervisión',
        ];
    }
}
