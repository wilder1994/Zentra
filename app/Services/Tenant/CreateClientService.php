<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Tenant\Data\CreateClientData;
use App\Enums\ClientLifecycle;
use App\Enums\ClientPlanTier;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Services\Client\ManageClientMemberTypeService;
use Illuminate\Support\Str;

final class CreateClientService
{
    public function execute(CreateClientData $data): Client
    {
        $company = SecurityCompany::query()->findOrFail($data->securityCompanyId);
        if ($data->isActive) {
            app(AssertInstallationSeats::class)->execute($company, AssertInstallationSeats::ACTION_CREATE_CLIENT);
            app(AssertClientServiceSeats::class)->execute($company, [
                'access' => $data->hasAccess,
                'supervision' => $data->hasSupervision,
                'observatory' => $data->hasObservatory,
            ]);
        }

        $slug = $this->uniqueSlug($data->securityCompanyId, $data->name);
        $loginSuffix = $this->uniqueLoginSuffix($data->securityCompanyId, $slug);

        $client = Client::query()->create([
            'security_company_id' => $data->securityCompanyId,
            'name' => $data->name,
            'party_type' => $data->partyType,
            'legal_name' => $data->legalName ?: $data->name,
            'document_type' => $data->documentType,
            'tax_id' => $data->taxId,
            'email' => $data->email,
            'phone' => $data->phone,
            'representative_name' => $data->representativeName,
            'representative_email' => $data->representativeEmail,
            'structure_type_id' => $data->structureTypeId,
            'slug' => $slug,
            'login_suffix' => $loginSuffix,
            'address' => $data->address,
            'city' => $data->city,
            'department' => $data->department,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'plan_tier' => ClientPlanTier::Economic,
            'max_structures' => ClientPlanTier::Economic->maxStructures(),
            'access_url' => null,
            'is_active' => $data->isActive,
            'has_access' => $data->hasAccess,
            'has_supervision' => $data->hasSupervision,
            'has_observatory' => $data->hasObservatory,
            'archived_at' => $data->isActive ? null : now(),
            'show_personnel_folders' => $data->hasAccess && $data->showPersonnelFolders,
            'service_started_at' => $data->serviceStartedAt ?? now()->toDateString(),
            'lifecycle' => ClientLifecycle::Active,
        ]);

        app(ManageClientMemberTypeService::class)->ensureDefaults((int) $client->id);

        return $client;
    }

    private function uniqueSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'cliente';
        $slug = $base;
        $i = 2;

        while (Client::query()
            ->where('security_company_id', $companyId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueLoginSuffix(int $companyId, string $slug): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]+/i', '', $slug) ?: 'cliente');
        $suffix = $base;
        $i = 2;

        while (Client::query()
            ->where('security_company_id', $companyId)
            ->where('login_suffix', $suffix)
            ->exists()) {
            $suffix = $base.$i;
            $i++;
        }

        return $suffix;
    }
}
