<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\SupervisionPackageSku;
use App\Models\CommercialPayment;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Platform\ScheduleCompanyPackageChangeService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_shows_membership_and_history(): void
    {
        $this->seedWithPilot();

        $companyUser = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $response = $this->actingAs($companyUser)->get(route('company.billing.index'));

        $response->assertOk();
        $response->assertSee('Membresía');
        $response->assertSee('Historial de pagos');
        $response->assertSee('Línea de tiempo');
        $response->assertSee('Facturas');
        $response->assertSee('Reactivar membresía');
    }

    public function test_company_can_cancel_and_undo_membership(): void
    {
        $this->seedWithPilot();

        $companyUser = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $cancel = $this->actingAs($companyUser)->post(
            route('company.billing.membership.cancel'),
            ['reason' => 'Ya no necesitamos el servicio este mes'],
        );

        $cancel->assertRedirect(route('company.billing.index'));
        $company->refresh();
        $this->assertTrue($company->hasPendingCancellation());

        $undo = $this->actingAs($companyUser)->post(
            route('company.billing.membership.undo-cancel'),
        );

        $undo->assertRedirect(route('company.billing.index'));
        $undo->assertSessionHas('success');
        $company->refresh();
        $this->assertFalse($company->hasPendingCancellation());
    }

    public function test_company_can_checkout_supervision_from_billing(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $companyUser = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($companyUser)
            ->get(route('company.billing.index'))
            ->assertOk()
            ->assertSee('Supervisión');

        $this->actingAs($admin)->post(
            route('admin.documents.expedientes.acceptance', $company),
            [
                'representative_name' => 'Rep Legal',
                'representative_role' => 'Gerente',
                'representative_document_type' => 'CC',
                'representative_document_number' => '1098765432',
                ...$this->acceptAllCorpusDocs($company->package_sku),
            ],
        )->assertRedirect();

        $checkout = $this->actingAs($companyUser)->post(route('company.billing.supervision.update'), [
            'supervision_package_sku' => 'sup_5',
        ]);
        $checkout->assertRedirect();
        $this->assertSame('sup_10', $company->fresh()->supervision_package_sku?->value);

        $payment = CommercialPayment::query()
            ->where('security_company_id', $company->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->actingAs($companyUser)
            ->post(route('billing.checkout.approve', $payment))
            ->assertRedirect(route('company.billing.index'));

        $company->refresh();
        $this->assertSame('sup_10', $company->supervision_package_sku?->value);
        $this->assertSame('sup_5', $company->scheduled_supervision_package_sku);

        Carbon::setTestNow($company->scheduled_change_at);
        app(ScheduleCompanyPackageChangeService::class)->applyDueChanges();
        Carbon::setTestNow();

        $company->refresh();
        $this->assertSame('sup_5', $company->supervision_package_sku?->value);
        $this->assertSame(5, (int) $company->max_supervision_clients);
    }

    public function test_company_schedules_supervision_removal_at_period_end(): void
    {
        $this->seedWithPilot();

        $companyUser = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)
            ->execute($company, SupervisionPackageSku::Sit1);

        $this->actingAs($companyUser)
            ->post(route('company.billing.supervision.update'), [
                'supervision_package_sku' => '',
            ])
            ->assertRedirect(route('company.billing.index'));

        $company->refresh();
        $this->assertSame(SupervisionPackageSku::Sit1, $company->supervision_package_sku);
        $this->assertSame('none', $company->scheduled_supervision_package_sku);
    }
}
