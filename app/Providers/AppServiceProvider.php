<?php

namespace App\Providers;

use App\Models\Blocklist;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\MemberType;
use App\Models\ObservatoryEvent;
use App\Models\Resident;
use App\Models\SecurityCompany;
use App\Models\StructureType;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorPostModality;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Policies\CompanyCollaboratorTypePolicy;
use App\Policies\CompanyJobTitlePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\MemberTypePolicy;
use App\Policies\ObservatoryEventPolicy;
use App\Policies\SecurityCompanyPolicy;
use App\Policies\StructureTypePolicy;
use App\Policies\SupervisorCompanyCatalogPolicy;
use App\Policies\UserPolicy;
use App\Support\Company\OperateAsRole;
use App\Support\Tenancy\TenantContext;
use App\View\Composers\AccessLayoutComposer;
use App\View\Composers\CompanyLayoutComposer;
use App\View\Composers\OperateReturnLayoutComposer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->scoped(OperateAsRole::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            Blocklist::TYPE_VISITOR => Visitor::class,
            Blocklist::TYPE_VEHICLE => Vehicle::class,
            Blocklist::TYPE_RESIDENT => Resident::class,
        ]);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SecurityCompany::class, SecurityCompanyPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(CompanyJobTitle::class, CompanyJobTitlePolicy::class);
        Gate::policy(CompanyCollaboratorType::class, CompanyCollaboratorTypePolicy::class);
        Gate::policy(StructureType::class, StructureTypePolicy::class);
        Gate::policy(MemberType::class, MemberTypePolicy::class);
        Gate::policy(ObservatoryEvent::class, ObservatoryEventPolicy::class);
        Gate::policy(SupervisorZone::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorShiftTemplate::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorChecklistItem::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorDocumentType::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorControlBookType::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorWeaponType::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorWeaponBrand::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorRiskType::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorAlarmType::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorPostModality::class, SupervisorCompanyCatalogPolicy::class);
        Gate::policy(SupervisorSupportType::class, SupervisorCompanyCatalogPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            $simulated = app(OperateAsRole::class)->allows($ability);
            if ($simulated !== null) {
                return $simulated;
            }

            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        View::composer('layouts.company', CompanyLayoutComposer::class);
        View::composer('layouts.access', AccessLayoutComposer::class);
        View::composer(['layouts.access', 'layouts.client'], OperateReturnLayoutComposer::class);
    }
}
