<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Models\Client;
use App\Support\Client\ClientPanelModules;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClientModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return $this->user()?->can('update', $client) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');
        $rules = [];
        foreach (array_keys(ClientPanelModules::availableLabelsFor($client)) as $key) {
            $rules['modules.'.$key] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /** @return array<string, bool> */
    public function modules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');
        $available = array_keys(ClientPanelModules::availableLabelsFor($client));
        $normalized = ClientPanelModules::defaultOptional();

        foreach ($normalized as $key => $default) {
            if (! in_array($key, $available, true)) {
                $normalized[$key] = false;

                continue;
            }

            $normalized[$key] = $this->boolean('modules.'.$key);
        }

        if (! $client->hasDoors()) {
            $normalized[ClientPanelModules::DOORS] = false;
        }

        return $normalized;
    }
};
