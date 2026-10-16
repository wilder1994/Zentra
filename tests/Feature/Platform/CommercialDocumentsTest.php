<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommercialDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_commercial_hub(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.commercial.index'))
            ->assertOk()
            ->assertSee('Comercial')
            ->assertSee('Propuesta comercial')
            ->assertSee('Cotización')
            ->assertSee('Presentación comercial');
    }

    public function test_super_admin_can_open_brochure_and_quote_forms(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.commercial.brochure.create'))
            ->assertOk()
            ->assertSee('Propuesta comercial')
            ->assertSee('Dirigido a');

        $this->actingAs($admin)
            ->get(route('admin.commercial.quotes.create'))
            ->assertOk()
            ->assertSee('Cotización comercial')
            ->assertSee('Total comercial');

        $this->actingAs($admin)
            ->get(route('admin.commercial.deck.create'))
            ->assertOk()
            ->assertSee('Presentación comercial')
            ->assertSee('Descargar PowerPoint');
    }

    public function test_super_admin_downloads_brochure_pdf(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.commercial.brochure.pdf'), [
            'date' => now()->toDateString(),
            'city' => 'Cali',
            'directed_to' => 'SJ Seguridad Ltda.',
            'subject' => 'Presentación comercial — Zentra',
            'introduction' => 'Introducción de prueba para la propuesta comercial.',
            'highlight' => 'Lo que destaca en esta propuesta.',
            'conclusions' => 'Conclusiones y siguientes pasos.',
            'presenter_name' => 'Wilder Rivera',
            'presenter_role' => 'Ejecutivo comercial',
            'presenter_phone' => '3000000000',
            'presenter_email' => 'admin@control-acceso.test',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_super_admin_downloads_quote_pdf_with_commercial_amount(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.commercial.quotes.pdf'), [
            'date' => now()->toDateString(),
            'city' => 'Cali',
            'directed_to' => 'Cliente Demo',
            'subject' => 'Cotización comercial — Zentra',
            'introduction' => 'Introducción de la cotización.',
            'highlight' => 'Plan personalizado con precio comercial.',
            'conclusions' => 'Quedamos atentos.',
            'presenter_name' => 'Wilder Rivera',
            'presenter_role' => 'Ejecutivo comercial',
            'presenter_phone' => '',
            'presenter_email' => 'admin@control-acceso.test',
            'billing_cycle' => 'monthly',
            'manual_seats' => 5,
            'hardware_seats' => 0,
            'supervision_seats' => 0,
            'indexing_seats' => 0,
            'observatory_seats' => 0,
            'commercial_amount' => 450000,
            'commercial_note' => 'Precio de alianza',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_super_admin_downloads_deck_pptx(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.commercial.deck.pptx'), [
            'date' => now()->toDateString(),
            'city' => 'Cali',
            'directed_to' => 'Cliente Demo SAS',
            'subject' => 'Presentación comercial — Zentra',
            'context' => 'Contexto del reto operativo.',
            'solution' => 'Solución Zentra unificada.',
            'why' => 'Por qué elegir Zentra.',
            'next_steps' => '1) Demo. 2) Cotización. 3) Activación.',
            'closing' => 'Gracias por su tiempo.',
            'presenter_name' => 'Wilder Rivera',
            'presenter_role' => 'Ejecutivo comercial',
            'presenter_phone' => '3000000000',
            'presenter_email' => 'admin@control-acceso.test',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'presentationml.presentation',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_company_admin_cannot_open_commercial(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($user)->get(route('admin.commercial.index'))->assertForbidden();
    }
}
