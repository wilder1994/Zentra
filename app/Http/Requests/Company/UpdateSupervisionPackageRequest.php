<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\CommercialMetal;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Support\Catalog\CatalogSettings;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSupervisionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.billing.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supervision_seats' => ['nullable', 'integer', 'min:0', 'max:'.SupervisionPackageSku::MAX_SEATS],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $metal = CommercialMetal::forSeats((int) $this->input('supervision_seats', 0));
            if ($metal === null || CatalogSettings::current()->isMetalAvailable($metal)) {
                return;
            }

            $companyId = app(ActingCompanyResolver::class)->id($this->user());
            $company = $companyId !== null ? SecurityCompany::query()->find($companyId) : null;
            if ($company?->supervisionMetal() !== $metal) {
                $validator->errors()->add('supervision_seats', "El plan {$metal->label()} de Supervisión no está disponible.");
            }
        }];
    }

    public function supervisionSku(): ?SupervisionPackageSku
    {
        $seats = (int) $this->input('supervision_seats', 0);

        return $seats > 0 ? SupervisionPackageSku::fromSize($seats) : null;
    }
}
