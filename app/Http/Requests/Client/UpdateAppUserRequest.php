<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\StructureAppUser;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAppUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.app_users.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_channel' => ['required', 'in:'.StructureAppUser::CHANNEL_ANDROID.','.StructureAppUser::CHANNEL_IOS_WEB],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
