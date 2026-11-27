<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReportType;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

final class ObservatoryCompanyManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_filters_events_by_site_status_and_folio(): void
    {
        [$client, $santa, $peru] = $this->sites();
        $company = $this->company();

        $this->report($client, $santa, 'Amenaza en la entrada principal del colegio.');
        $this->report($client, $peru, 'Hurto de celular en el patio del colegio.');
        $event = ObservatoryEvent::query()->where('installation_id', $santa->id)->firstOrFail();

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['vista' => 'eventos', 'installation_id' => $santa->id]))
            ->assertOk()
            ->assertSee('Instalación: IE Santa Librada', false)
            ->assertViewHas('events', fn ($events): bool => $this->sitesOf($events) === ['IE Santa Librada']);

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['vista' => 'eventos', 'q' => $event->folio()]))
            ->assertOk()
            ->assertViewHas('events', fn ($events): bool => $this->sitesOf($events) === ['IE Santa Librada']);

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['vista' => 'eventos', 'status' => ObservatoryEventStatus::Cerrado->value]))
            ->assertOk()
            ->assertViewHas('events', fn ($events): bool => $this->sitesOf($events) === []);
    }

    public function test_company_admin_attends_event(): void
    {
        [$client, $santa] = $this->sites();
        $company = $this->company();
        $this->report($client, $santa, 'Amenaza en la entrada principal del colegio.');
        $event = ObservatoryEvent::query()->where('installation_id', $santa->id)->firstOrFail();

        $this->actingAs($company)
            ->get(route('company.observatory.events.show', $event))
            ->assertOk()
            ->assertSee('Pasar a en atención', false);

        $this->actingAs($company)
            ->patch(route('company.observatory.events.status', $event), [
                'status' => ObservatoryEventStatus::EnAtencion->value,
                'note' => 'La empresa revisa el caso con el rector.',
            ])
            ->assertRedirect(route('company.observatory.events.show', $event));

        $this->assertSame(ObservatoryEventStatus::EnAtencion, $event->fresh()->status);
    }

    public function test_board_pptx_follows_scope_and_filters(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response('', 500)]);
        [$client, $santa, $peru] = $this->sites();
        $santa->update(['latitude' => '3.4372200', 'longitude' => '-76.5225000']);
        $company = $this->company();
        $this->report($client, $santa, 'Amenaza en la entrada principal del colegio.');
        $this->report($client, $peru, 'Amenaza en el patio trasero del colegio.');

        $this->actingAs($company)
            ->get(route('company.observatory.board.export'))
            ->assertOk()
            ->assertDownload();

        $file = $this->actingAs($company)
            ->get(route('company.observatory.board.export', ['installation_id' => $santa->id, 'type' => 'amenaza']))
            ->assertOk()
            ->assertDownload('Informe_Observatorio_ie-santa-librada_'.now()->startOfYear()->toDateString().'_'.now()->toDateString().'.pptx');

        $slides = $this->slideTexts($file->baseResponse->getFile()->getPathname());
        $joined = implode(' ', $slides);
        $this->assertGreaterThanOrEqual(9, count($slides));
        $this->assertStringContainsString('Eventos y riesgos', $slides[0]);
        $this->assertStringContainsString('Instalación', $slides[0]);
        $this->assertStringContainsString('Por mes', $slides[0]);
        $this->assertStringContainsString('Endógeno: Amenaza', $slides[0]);
        $this->assertStringContainsString('Tablero', $slides[1]);
        $this->assertStringContainsString('Mapa', $slides[2]);
        $this->assertStringContainsString('EV-', $slides[2]);
        $this->assertStringContainsString('Atención, fuentes y orígenes', $joined);
        $this->assertStringContainsString('Tendencia · Por mes', $joined);
        $this->assertStringContainsString('Delitos y reportes por instalación', $joined);
        $this->assertStringContainsString('Riesgo por instalación', $joined);
        $this->assertStringContainsString('entrada principal', $joined);
        $this->assertStringNotContainsString('Amenaza · ALTO', $joined);
        $this->assertStringNotContainsString('IE Republica del Peru', $joined);
        $this->assertStringContainsString('Gracias', $slides[array_key_last($slides)]);
    }

    /** @return list<string> Texto de cada diapositiva en orden. */
    private function slideTexts(string $path): array
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $texts = [];
        for ($i = 1; ($xml = $zip->getFromName("ppt/slides/slide{$i}.xml")) !== false; $i++) {
            preg_match_all('/<a:t[^>]*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/a:t>/s', $xml, $matches);
            $texts[] = html_entity_decode(implode(' ', $matches[1]));
        }
        $zip->close();

        return $texts;
    }

    public function test_company_manages_and_copies_types(): void
    {
        [$client] = $this->sites();
        $company = $this->company();
        $target = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        app(EnsureObservatoryReportTypesService::class)->execute($target);

        $this->actingAs($company)
            ->post(route('company.observatory.types.store', $client), [
                'name' => 'Porte de arma',
                'impacto' => 8,
                'urgencia' => 8,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $type = ObservatoryReportType::query()->where('security_company_id', $client->security_company_id)->where('name', 'Porte de arma')->firstOrFail();

        $this->actingAs($company)
            ->put(route('company.observatory.types.update', $type), [
                'name' => 'Porte de armas',
                'impacto' => 6,
                'urgencia' => 6,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $this->assertSame('Porte de armas', $type->fresh()->name);
        $this->assertSame(1, ObservatoryReportType::query()->where('security_company_id', $target->security_company_id)->where('slug', 'amenaza')->count());
        $this->assertTrue(
            ObservatoryReportType::query()->where('security_company_id', $target->security_company_id)->where('name', 'Porte de armas')->exists(),
        );

        $this->actingAs($company)
            ->delete(route('company.observatory.types.destroy', $type))
            ->assertRedirect();
        $this->assertDatabaseMissing('observatory_report_types', ['id' => $type->id]);
    }

    public function test_company_manager_paints_a_chart_and_the_client_cannot(): void
    {
        $this->seedWithPilot();
        $company = $this->company();

        $this->actingAs($company)
            ->putJson(route('company.observatory.charts.paint'), [
                'chart' => 'sources',
                'colors' => ['whatsapp' => '#112233'],
            ])
            ->assertOk();

        $stored = SecurityCompany::query()->findOrFail($company->security_company_id);
        $this->assertSame('#112233', $stored->observatory_chart_colors['sources']['whatsapp'] ?? null);

        $client = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $this->actingAs($client)
            ->putJson(route('company.observatory.charts.paint'), [
                'chart' => 'sources',
                'colors' => ['whatsapp' => '#abcdef'],
            ])
            ->assertForbidden();
    }

    /** @return list<string> */
    private function sitesOf(LengthAwarePaginator $events): array
    {
        return collect($events->items())->map(fn (ObservatoryEvent $event): ?string => $event->installation?->name)->unique()->values()->all();
    }

    private function company(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    private function report(Client $client, Installation $site, string $body): void
    {
        app(EnsureObservatoryReportTypesService::class)->execute($client);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $site->id,
            'kind' => 'amenaza',
            'body' => $body,
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
    }

    /** @return array{0: Client, 1: Installation, 2: Installation} */
    private function sites(): array
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $make = fn (string $name, string $dane): Installation => Installation::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => $dane,
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        return [$client, $make('IE Santa Librada', '176001000301'), $make('IE Republica del Peru', '176001000302')];
    }
}
