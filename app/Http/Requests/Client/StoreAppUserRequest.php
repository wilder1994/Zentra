<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreAppUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.app_users.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $clientId = app(TenantContext::class)->clientId();
        $allowedIds = app(TenantContext::class)->installationIds();

        $memberRule = Rule::exists('structure_members', 'id')->where(function ($query) use ($clientId, $allowedIds): void {
            $query->where('client_id', $clientId)->where('is_active', true);
            if ($allowedIds !== null) {
                $query->whereIn('structure_id', function ($sub) use ($allowedIds): void {
                    $sub->select('id')->from('structures')->whereIn('installation_id', $allowedIds);
                });
            }
        });

        return [
            'member_id' => ['required', 'integer', $memberRule],
            'delivery_channel' => ['required', 'in:'.StructureAppUser::CHANNEL_ANDROID.','.StructureAppUser::CHANNEL_IOS_WEB],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = (int) $this->input('member_id');
            if ($id < 1) {
                return;
            }

            $member = StructureMember::query()->find($id);
            if ($member?->isMinor()) {
                $validator->errors()->add('member_id', 'No se crea usuario app para menores de edad.');
            }
        });
    }
}
