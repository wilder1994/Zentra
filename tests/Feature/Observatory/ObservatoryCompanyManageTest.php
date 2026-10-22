<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\InstallationKind;
use App\Enums\ObservatoryEventStatus;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReportType;
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
        $this->assertCount(6, $slides);
        $this->assertStringContainsString('Eventos y riesgos', $slides[0]);
        $this->assertStringContainsString('Instalación', $slides[0]);
        $this->assertStringContainsString('Tipo: Amenaza', $slides[0]);
        $this->assertStringContainsString('Mapa de calor', $slides[2]);
        $this->assertStringContainsString('EV-', $slides[2]);
        $this->assertStringContainsString('Amenaza · nivel 3', $slides[3]);
        $this->assertStringNotContainsString('IE Republica del Peru', implode(' ', $slides));
        $this->assertStringContainsString('Gracias', $slides[5]);
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
                'level' => 3,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $type = ObservatoryReportType::query()->where('client_id', $client->id)->where('name', 'Porte de arma')->firstOrFail();

        $this->actingAs($company)
            ->put(route('company.observatory.types.update', $type), [
                'name' => 'Porte de armas',
                'level' => 2,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $this->assertSame('Porte de armas', $type->fresh()->name);

        $this->actingAs($company)
            ->post(route('company.observatory.types.copy', $client), ['client_ids' => [$target->id]])
            ->assertRedirect();
        $this->assertDatabaseHas('observatory_report_types', ['client_id' => $target->id, 'name' => 'Porte de armas']);
        $this->assertSame(1, ObservatoryReportType::query()->where('client_id', $target->id)->where('slug', 'amenaza')->count());

        $this->actingAs($company)
            ->delete(route('company.observatory.types.destroy', $type))
            ->assertRedirect();
        $this->assertDatabaseMissing('observatory_report_types', ['id' => $type->id]);
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
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => $dane,
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        return [$client, $make('IE Santa Librada', '176001000301'), $make('IE Republica del Peru', '176001000302')];
    }
}
