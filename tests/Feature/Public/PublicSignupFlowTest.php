<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\CompanyPackageSku;
use App\Enums\SupervisionPackageSku;
use App\Models\CommercialSignupIntent;
use App\Models\PricingSettings;
use App\Models\SecurityCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicSignupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_shows_commercial_card(): void
    {
        $this->seed();

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Ver planes y contratar');
        $response->assertSee('Licencia SaaS');
        $response->assertSee('Accesos, supervisión y observatorio');
        $response->assertSee('Accesos');
        $response->assertSee('Supervisión');
        $response->assertSee('Observatorio');
        $response->assertSee('WCodex');
    }

    public function test_public_signup_creates_user_only_on_approved_payment(): void
    {
        $this->seed();

        $create = $this->get(route('signup.create', [
            'sku' => 'pack_1_manual',
            'cycle' => 'monthly',
        ]));
        $create->assertRedirect();

        $intent = CommercialSignupIntent::query()->firstOrFail();

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Vigilancia Andina S.A.S.',
            'trade_name' => 'Vigilancia Andina',
            'tax_id' => '901999888-1',
            'admin_name' => 'Admin Andina',
            'email' => 'admin@vigilancia-andina.test',
            'phone' => '+57 300 111 0000',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ])->assertRedirect(route('signup.legal', $intent));

        $this->post(route('signup.legal.store', $intent), [
            'representative_name' => 'María López',
            'representative_role' => 'Gerente',
            'representative_document_type' => 'CC',
            'representative_document_number' => '52123456',
            ...$this->acceptAllCorpusDocs(CompanyPackageSku::from('pack_1_manual')),
        ])->assertRedirect(route('signup.summary', $intent));

        $this->post(route('signup.pay', $intent))
            ->assertRedirect(route('signup.checkout.show', $intent));

        $this->assertDatabaseMissing('users', ['email' => 'admin@vigilancia-andina.test']);

        $reject = $this->post(route('signup.checkout.reject', $intent));
        $reject->assertRedirect(route('planes.index'));
        $this->assertDatabaseMissing('users', ['email' => 'admin@vigilancia-andina.test']);
    }

    public function test_approved_public_signup_activates_account(): void
    {
        $this->seed();

        $this->get(route('signup.create', [
            'sku' => 'pack_1_manual',
            'cycle' => 'monthly',
        ]));

        $intent = CommercialSignupIntent::query()->firstOrFail();

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Norte Vigilancia S.A.S.',
            'trade_name' => 'Norte Vigilancia',
            'tax_id' => '901888777-2',
            'admin_name' => 'Admin Norte',
            'email' => 'admin@norte-vigilancia.test',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ]);

        $this->post(route('signup.legal.store', $intent), [
            'representative_name' => 'Carlos Pérez',
            'representative_role' => 'Rep. Legal',
            'representative_document_type' => 'CC',
            'representative_document_number' => '80123456',
            ...$this->acceptAllCorpusDocs(CompanyPackageSku::from('pack_1_manual')),
        ]);

        $this->post(route('signup.pay', $intent));

        $approve = $this->post(route('signup.checkout.approve', $intent));
        $approve->assertRedirect(route('company.dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'admin@norte-vigilancia.test']);
        $this->assertDatabaseHas('security_companies', ['tax_id' => '901888777-2']);
        $this->assertDatabaseHas('subscription_acceptances', ['representative_name' => 'Carlos Pérez']);
        $this->assertDatabaseHas('platform_documents', ['type' => 'invoice', 'is_demo' => true]);

        $user = User::query()->where('email', 'admin@norte-vigilancia.test')->first();
        $this->assertAuthenticatedAs($user);
    }

    public function test_public_signup_allows_supervision_with_single_access(): void
    {
        $this->seed();

        $this->get(route('signup.create', [
            'access' => 1,
            'supervision' => 5,
            'cycle' => 'monthly',
        ]))->assertRedirect();

        $intent = CommercialSignupIntent::query()->firstOrFail();
        $this->assertSame(1, (int) $intent->package_manual_seats);
        $this->assertSame(SupervisionPackageSku::fromSize(5), $intent->supervision_package_sku);
    }

    public function test_public_signup_supervision_only(): void
    {
        $this->seed();

        $this->get(route('signup.create', ['supervision' => 7, 'cycle' => 'monthly']))
            ->assertRedirect();

        $intent = CommercialSignupIntent::query()->firstOrFail();
        $this->assertSame(SupervisionPackageSku::fromSize(7), $intent->supervision_package_sku);
        $this->assertSame(0, (int) $intent->package_manual_seats + (int) $intent->package_hardware_seats);
    }

    public function test_public_signup_rejects_unavailable_hardware_and_plan(): void
    {
        $this->seed();

        $this->get(route('signup.create', ['access' => 2, 'hardware' => 2, 'cycle' => 'monthly']))
            ->assertRedirect(route('planes.index'))
            ->assertSessionHas('warning');

        $settings = PricingSettings::current();
        $settings->update(['catalog' => array_merge($settings->catalog ?? [], ['availability' => ['oro' => false, 'hardware' => false]])]);

        $this->get(route('signup.create', ['access' => 20, 'cycle' => 'monthly']))
            ->assertRedirect(route('planes.index'))
            ->assertSessionHas('warning');

        $this->assertSame(0, CommercialSignupIntent::query()->count());
    }

    public function test_public_signup_with_supervision_assigns_offer_package(): void
    {
        $this->seed();

        PricingSettings::query()->latest('id')->firstOrFail()->update([
            'unit_price_supervision' => 50_000,
        ]);

        $this->get(route('signup.create', [
            'sku' => 'pack_5_manual',
            'sup' => 'sup_10',
            'cycle' => 'monthly',
            'manual' => 5,
            'hardware' => 0,
        ]));

        $intent = CommercialSignupIntent::query()->firstOrFail();
        $this->assertSame(SupervisionPackageSku::fromSize(10), $intent->supervision_package_sku);
        $this->assertGreaterThan(80_000, (float) $intent->amount);

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Andes Vigilancia S.A.S.',
            'trade_name' => 'Andes Vigilancia',
            'tax_id' => '901777666-3',
            'admin_name' => 'Admin Andes',
            'email' => 'admin@andes-pro.test',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ]);

        $this->post(route('signup.legal.store', $intent), [
            'representative_name' => 'Ana Ruiz',
            'representative_role' => 'Gerente',
            'representative_document_type' => 'CC',
            'representative_document_number' => '52111000',
            ...$this->acceptAllCorpusDocs(CompanyPackageSku::from('pack_5_manual')),
        ]);

        $this->post(route('signup.pay', $intent));
        $this->post(route('signup.checkout.approve', $intent))
            ->assertRedirect(route('company.dashboard'));

        $company = SecurityCompany::query()->where('tax_id', '901777666-3')->firstOrFail();
        $this->assertSame(SupervisionPackageSku::fromSize(10), $company->supervision_package_sku);
        $this->assertSame(10, (int) $company->max_supervision_clients);
    }

    public function test_public_signup_can_pick_supervision_other_than_offer(): void
    {
        $this->seed();

        $this->get(route('signup.create', [
            'sku' => 'pack_5_manual',
            'sup' => 'sup_5',
            'cycle' => 'monthly',
            'manual' => 5,
            'hardware' => 0,
        ]));

        $intent = CommercialSignupIntent::query()->firstOrFail();
        $this->assertSame(SupervisionPackageSku::fromSize(5), $intent->supervision_package_sku);
        $this->assertNotSame(SupervisionPackageSku::fromSize(10), $intent->supervision_package_sku);

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Catalogo Vigilancia S.A.S.',
            'trade_name' => 'Catalogo Vigilancia',
            'tax_id' => '901555444-5',
            'admin_name' => 'Admin Catalogo',
            'email' => 'admin@catalogo-vigilancia.test',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ]);

        $this->post(route('signup.legal.store', $intent), [
            'representative_name' => 'Eva Díaz',
            'representative_role' => 'Gerente',
            'representative_document_type' => 'CC',
            'representative_document_number' => '52222000',
            ...$this->acceptAllCorpusDocs(CompanyPackageSku::from('pack_5_manual')),
        ]);

        $this->post(route('signup.pay', $intent));
        $this->post(route('signup.checkout.approve', $intent))
            ->assertRedirect(route('company.dashboard'));

        $company = SecurityCompany::query()->where('tax_id', '901555444-5')->firstOrFail();
        $this->assertSame(SupervisionPackageSku::fromSize(5), $company->supervision_package_sku);
        $this->assertSame(5, (int) $company->max_supervision_clients);
    }

    public function test_public_signup_mixed_seats_when_hardware_available(): void
    {
        $this->seed();
        $settings = PricingSettings::current();
        $settings->update(['catalog' => array_merge($settings->catalog ?? [], ['availability' => ['hardware' => true]])]);

        $this->get(route('signup.create', [
            'access' => 30,
            'hardware' => 20,
            'supervision' => 100,
            'cycle' => 'monthly',
        ]));

        $intent = CommercialSignupIntent::query()->firstOrFail();
        $this->assertSame(30, (int) $intent->package_manual_seats);
        $this->assertSame(20, (int) $intent->package_hardware_seats);
        $this->assertSame(SupervisionPackageSku::fromSize(100), $intent->supervision_package_sku);

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Mixto Vigilancia S.A.S.',
            'trade_name' => 'Mixto Vigilancia',
            'tax_id' => '901666555-4',
            'admin_name' => 'Admin Mixto',
            'email' => 'admin@mixto-vigilancia.test',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ]);

        $this->post(route('signup.legal.store', $intent), [
            'representative_name' => 'Luis Mora',
            'representative_role' => 'Gerente',
            'representative_document_type' => 'CC',
            'representative_document_number' => '10990011',
            ...$this->acceptAllCorpusDocs(CompanyPackageSku::from('pack_50_manual')),
        ]);

        $this->post(route('signup.pay', $intent));
        $this->post(route('signup.checkout.approve', $intent))
            ->assertRedirect(route('company.dashboard'));

        $company = SecurityCompany::query()->where('tax_id', '901666555-4')->firstOrFail();
        $this->assertSame(30, (int) $company->package_manual_seats);
        $this->assertSame(20, (int) $company->package_hardware_seats);
        $this->assertSame(100, (int) $company->max_supervision_clients);
    }
}
