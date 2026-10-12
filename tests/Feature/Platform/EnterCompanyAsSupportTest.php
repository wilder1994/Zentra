<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\SecurityCompany;
use App\Models\User;
use App\Support\Platform\SupportCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EnterCompanyAsSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_enter_company_panel_and_exit(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $enter = $this->actingAs($admin)->post(route('admin.companies.enter', $company));
        $enter->assertRedirect(route('company.dashboard'));
        $this->assertSame((int) $company->id, SupportCompanyContext::companyId());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.enter_company',
            'user_id' => $admin->id,
        ]);

        $dashboard = $this->actingAs($admin)->get(route('company.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Entraste como');
        $dashboard->assertSee($company->displayName());

        $this->actingAs($admin)->get(route('company.settings.edit'))->assertOk();

        $exit = $this->actingAs($admin)->post(route('admin.support.exit'));
        $exit->assertRedirect(route('admin.companies.show', $company));
        $this->assertNull(SupportCompanyContext::companyId());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.exit_company',
            'user_id' => $admin->id,
        ]);
    }

    public function test_super_admin_without_support_context_is_redirected_from_company_supervision(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('company.supervision.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('warning', SupportCompanyContext::EXPIRED_FLASH);
    }

    public function test_login_does_not_restore_company_intended_url_for_super_admin(): void
    {
        $this->seedWithPilot();

        $this->get(route('company.supervision.index'));

        $this->post(route('login'), [
            'email' => 'admin@control-acceso.test',
            'password' => 'Admin123!',
        ])->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('warning', SupportCompanyContext::EXPIRED_FLASH);

        $this->assertAuthenticated();
    }

    public function test_admin_dashboard_offers_resume_support_from_last_company_cookie(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $response = $this->actingAs($admin)->call(
            'GET',
            route('admin.dashboard'),
            cookies: [SupportCompanyContext::LAST_COMPANY_COOKIE => (string) $company->id],
        );

        $response->assertOk()
            ->assertSee('Entrar de nuevo', false)
            ->assertSee('Última empresa en soporte', false);
    }

    public function test_company_show_renders_expediente_without_ver_en_resumen(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.companies.show', $company));

        $response->assertOk();
        $response->assertSee('Entrar como empresa');
        $response->assertSee('Movimientos');
        $response->assertDontSee('Cartera de clientes');
        $response->assertDontSee('Ver en resumen');
    }

    public function test_manual_payment_from_company_show_redirects_back(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)->post(
            route('admin.documents.expedientes.acceptance', $company),
            [
                'representative_name' => 'Carlos Representante',
                'representative_role' => 'Gerente General',
                'representative_document_type' => 'CC',
                'representative_document_number' => '1234567890',
                ...$this->acceptAllCorpusDocs($company->package_sku),
            ],
        );

        Storage::fake('local');
        $company->refresh();
        $intent = $company->isUpToDate() ? 'anticipate' : 'renew';

        $payment = $this->actingAs($admin)->post(
            route('admin.companies.payment.manual', $company),
            [
                'reference' => 'SHOW-PAY-001',
                'intent' => $intent,
                'proof' => UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf'),
            ],
        );

        $payment->assertRedirect(route('admin.companies.historial', $company));
        $payment->assertSessionHas('success');
        $this->assertDatabaseHas('commercial_payments', [
            'security_company_id' => $company->id,
            'reference' => 'SHOW-PAY-001',
        ]);
    }

    public function test_can_cancel_membership_with_reason(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $response = $this->actingAs($admin)->post(
            route('admin.companies.membership.cancel', $company),
            ['reason' => 'Cliente solicitó baja por cambio de proveedor'],
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $company->refresh();
        $this->assertTrue($company->hasPendingCancellation());
        $this->assertSame('Cliente solicitó baja por cambio de proveedor', $company->cancellation_reason);
    }

    public function test_can_undo_membership_cancellation_without_payment(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)->post(
            route('admin.companies.membership.cancel', $company),
            ['reason' => 'Cliente solicitó baja por cambio de proveedor'],
        );

        $company->refresh();
        $this->assertTrue($company->canUndoCancellation());

        $response = $this->actingAs($admin)->post(
            route('admin.companies.membership.undo-cancel', $company),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $response->assertSessionHas('success');
        $company->refresh();
        $this->assertFalse($company->hasPendingCancellation());
        $this->assertNull($company->cancelled_at);
    }

    public function test_historial_tab_is_reachable(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.companies.historial', $company));

        $response->assertOk();
        $response->assertSee('Historial de pagos');
        $response->assertSee('Facturas');
        $response->assertSee('Línea de tiempo');
    }
}
