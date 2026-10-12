<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Models\Client;
use App\Models\SupervisorPostModality;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientSupervisorPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($client->has_access || $client->has_supervision)
            && ($this->user()?->can('update', $client) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $client = $this->route('client');
        $companyId = $client instanceof Client ? (int) $client->security_company_id : 0;
        if ($companyId > 0) {
            app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);
        }

        $post = $this->route('post');
        $currentHours = $post?->modality !== null ? (int) $post->modality : null;

        $allowedHours = SupervisorPostModality::query()
            ->where('security_company_id', $companyId)
            ->where(function ($q) use ($currentHours) {
                $q->where('is_active', true);
                if ($currentHours !== null) {
                    $q->orWhere('hours', $currentHours);
                }
            })
            ->pluck('hours')
            ->map(fn ($h) => (int) $h)
            ->all();

        return [
            'installation_id' => ['required', 'integer', 'exists:installations,id'],
            'name' => ['required', 'string', 'max:120'],
            'modality' => ['required', 'integer', Rule::in($allowedHours !== [] ? $allowedHours : [12])],
            'service_days' => ['sometimes', 'array', 'min:1'],
            'service_days.*' => ['integer', 'between:1,7'],
            'serves_holidays' => ['sometimes', 'boolean'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'vista' => ['nullable', 'in:sitio,puertas,accesos,supervision'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'installation_id' => 'instalación',
            'name' => 'nombre',
            'modality' => 'modalidad',
            'service_days' => 'días de servicio',
            'serves_holidays' => 'servicio en festivos',
            'employee_ids' => 'vigilantes',
            'observations' => 'observaciones',
        ];
    }
}
