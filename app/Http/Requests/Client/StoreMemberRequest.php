<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\StructureMember;
use App\Support\Legal\CorpusAcceptanceRules;
use App\Support\Privacy\MinorPersonalData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');
        if ($member instanceof StructureMember) {
            return $this->user()?->can('update', $member) ?? false;
        }

        return $this->user()?->can('create', StructureMember::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $clientId = app(TenantContext::class)->clientId();
        $allowedIds = app(TenantContext::class)->installationIds();
        $structureRule = Rule::exists('structures', 'id')->where('client_id', $clientId);
        if ($allowedIds !== null) {
            $structureRule->whereIn('installation_id', $allowedIds);
        }

        $current = $this->route('member');
        $currentTypeId = $current instanceof StructureMember ? (int) $current->member_type_id : null;

        $typeRule = Rule::exists('member_types', 'id')->where(function ($query) use ($clientId, $currentTypeId): void {
            $query->where('client_id', $clientId)
                ->where(function ($inner) use ($currentTypeId): void {
                    $inner->where('is_active', true);
                    if ($currentTypeId !== null) {
                        $inner->orWhere('id', $currentTypeId);
                    }
                });
        });

        $documentUnique = Rule::unique('structure_members', 'document_number')->where('client_id', $clientId);
        if ($current instanceof StructureMember) {
            $documentUnique->ignore($current->id);
        }

        return [
            'structure_id' => ['required', 'integer', $structureRule],
            'member_type_id' => ['required', 'integer', $typeRule],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'document_type' => CorpusAcceptanceRules::documentTypeRule(),
            'document_number' => ['required', 'string', 'max:30', $documentUnique],
            'birth_date' => ['required', 'date', 'before:today'],
            'minor_treatment_accepted' => Rule::when(
                $this->requiresMinorAcceptance(),
                ['required', 'accepted'],
                ['nullable'],
            ),
            'phone_primary' => ['nullable', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'has_app_access' => ['boolean'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento',
            'minor_treatment_accepted' => 'autorización del representante legal',
        ];
    }

    public function isMinor(): bool
    {
        return MinorPersonalData::isMinor($this->input('birth_date'));
    }

    public function minorAcceptedAt(): ?string
    {
        $current = $this->route('member');
        if ($current instanceof StructureMember && $current->minor_treatment_accepted_at && $this->isMinor()) {
            return $current->minor_treatment_accepted_at->toDateTimeString();
        }

        if ($this->isMinor() && $this->boolean('minor_treatment_accepted')) {
            return now()->toDateTimeString();
        }

        return null;
    }

    private function requiresMinorAcceptance(): bool
    {
        if (! $this->isMinor()) {
            return false;
        }

        $current = $this->route('member');

        return ! ($current instanceof StructureMember && $current->minor_treatment_accepted_at);
    }
}
