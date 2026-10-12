<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Enums\SupervisionPackageSku;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanySupervisionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supervision_seats' => ['nullable', 'integer', 'min:0', 'max:'.SupervisionPackageSku::MAX_SEATS],
        ];
    }

    public function supervisionSku(): ?SupervisionPackageSku
    {
        $seats = (int) $this->input('supervision_seats', 0);

        return $seats > 0 ? SupervisionPackageSku::fromSize($seats) : null;
    }
}
