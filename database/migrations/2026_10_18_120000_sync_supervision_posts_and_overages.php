<?php

use App\Enums\CompanyPackageSku;
use App\Enums\PackageModality;
use App\Enums\SupervisionPackageSku;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;
use App\Services\Pricing\CompanyAgreedAmount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supervisión pasa a contarse por puesto: alinea cupos con puestos activos
 * (y Accesos/Observatorio/Indexación si están por debajo del uso), sin subir el valor pactado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $agreed = app(CompanyAgreedAmount::class);

        SecurityCompany::query()->whereNull('archived_at')->orderBy('id')->each(function (SecurityCompany $company) use ($agreed): void {
            $amount = $company->contractedAmount();
            $posts = SupervisorPost::query()
                ->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('has_supervision', true)->where('is_active', true))
                ->count();

            $accessUsed = Installation::query()
                ->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('has_access', true)->where('is_active', true)->where('lifecycle', 'active'))
                ->count();

            $obsUsed = Installation::query()
                ->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('has_observatory', true)->where('is_active', true)->where('lifecycle', 'active'))
                ->count();

            $employees = Employee::query()->where('security_company_id', $company->id)->where('is_active', true)->count();

            $dirty = false;
            $payload = [];

            if ($company->hasSupervisionPackage() && ! $company->hasUnlimitedSupervision() && $posts > (int) $company->max_supervision_clients) {
                $sku = SupervisionPackageSku::fromSize($posts);
                $payload['supervision_package_sku'] = $sku->value;
                $payload['supervision_package_size'] = $posts;
                $payload['max_supervision_clients'] = $posts;
                $dirty = true;
            }

            if ($company->hasAccessPackage() && $accessUsed > (int) $company->max_clients) {
                $hardware = (int) $company->package_hardware_seats;
                $manual = max(0, $accessUsed - $hardware);
                $sku = CompanyPackageSku::fromParts($manual + $hardware, PackageModality::Manual);
                $payload['package_sku'] = $sku->value;
                $payload['package_size'] = $manual + $hardware;
                $payload['package_manual_seats'] = $manual;
                $payload['package_hardware_seats'] = $hardware;
                $payload['max_clients'] = $manual + $hardware;
                $dirty = true;
            }

            if ((int) $company->observatory_seats > 0 && $obsUsed > (int) $company->observatory_seats) {
                $payload['observatory_seats'] = $obsUsed;
                $dirty = true;
            }

            if ((int) $company->indexing_seats > 0 && $employees > (int) $company->indexing_seats) {
                $payload['indexing_seats'] = $employees;
                $dirty = true;
            }

            if (! $dirty) {
                return;
            }

            DB::transaction(function () use ($company, $payload, $agreed, $amount): void {
                $company->forceFill($payload)->save();
                $agreed->apply($company->fresh(), $amount, null, 'Ajuste cupos a uso real (Supervisión por puesto)');
            });
        });
    }

    public function down(): void
    {
        // Irreversible a propósito: el valor pactado y los cupos quedan como estaban tras el ajuste.
    }
};
