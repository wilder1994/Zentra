<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\CompanyPackageSku;
use App\Enums\LegalCorpusType;
use App\Models\CommercialSignupIntent;
use App\Models\LegalCorpusVersion;
use App\Models\User;
use App\Support\Legal\CorpusAcceptanceRules;
use App\Support\Privacy\MinorPersonalData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MinorDataPolicyCorpusTest extends TestCase
{
    use RefreshDatabase;

    public function test_normoteca_and_contract_include_minors_policy(): void
    {
        $this->seedWithPilot();

        $this->assertContains(
            LegalCorpusType::MinorsDataPolicy->value,
            CorpusAcceptanceRules::requiredTypeValues(CompanyPackageSku::from('pack_1_manual')),
        );

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.documents.normativa'))
            ->assertOk()
            ->assertSee(LegalCorpusType::MinorsDataPolicy->label(), false)
            ->assertSee(MinorPersonalData::notice(), false);
    }

    public function test_signup_legal_requires_minors_policy(): void
    {
        $this->seed();

        $this->get(route('signup.create', [
            'sku' => 'pack_1_manual',
            'cycle' => 'monthly',
        ]))->assertRedirect();

        $intent = CommercialSignupIntent::query()->firstOrFail();

        $this->post(route('signup.data.store', $intent), [
            'party_type' => 'legal_entity',
            'legal_name' => 'Proteccion Menores S.A.S.',
            'trade_name' => 'Proteccion Menores',
            'tax_id' => '901777666-3',
            'admin_name' => 'Admin Menores',
            'email' => 'admin@proteccion-menores.test',
            'password' => 'Empresa123!',
            'password_confirmation' => 'Empresa123!',
        ])->assertRedirect(route('signup.legal', $intent));

        $this->get(route('signup.legal', $intent))
            ->assertOk()
            ->assertSee(LegalCorpusType::MinorsDataPolicy->label(), false);

        $this->from(route('signup.legal', $intent))
            ->post(route('signup.legal.store', $intent), [
                'representative_name' => 'Ana López',
                'representative_role' => 'Gerente',
                'representative_document_type' => 'CC',
                'representative_document_number' => '52111000',
                'accept_docs' => [
                    LegalCorpusType::Contract->value => '1',
                    LegalCorpusType::Terms->value => '1',
                    LegalCorpusType::PrivacyPolicy->value => '1',
                    LegalCorpusType::ProcedureLifecycle->value => '1',
                ],
            ])
            ->assertSessionHasErrors('accept_docs.'.LegalCorpusType::MinorsDataPolicy->value);
    }

    public function test_client_and_user_create_show_published_notice(): void
    {
        $this->seedWithPilot();

        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $notice = MinorPersonalData::notice();

        $this->actingAs($companyAdmin)
            ->get(route('company.clients.create'))
            ->assertOk()
            ->assertSee('Protección de datos de menores', false)
            ->assertSee($notice, false);

        $this->actingAs($companyAdmin)
            ->get(route('company.users.create'))
            ->assertOk()
            ->assertSee($notice, false);
    }

    public function test_publishing_updates_operational_notice(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $current = LegalCorpusVersion::currentGlobal(LegalCorpusType::MinorsDataPolicy);
        $this->assertNotNull($current);

        $updated = 'Aviso publicado de menores para prueba de Normoteca.';

        $this->actingAs($admin)
            ->put(route('admin.documents.normativa.publish', $current), [
                'title' => 'Protección de datos de menores',
                'content' => $updated,
                'effective_from' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame($updated, MinorPersonalData::notice());

        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($companyAdmin)
            ->get(route('company.clients.create'))
            ->assertOk()
            ->assertSee($updated, false);
    }
}
