<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftLocation;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorTrainingTopic;
use App\Models\SupervisorUniformItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupervisionSheetModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_sheet_has_modal_pdf_and_app_modes(): void
    {
        [$token, $review] = $this->reviewWithUniform();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $url = route('company.supervision.sheets.show', ['kind' => 'review', 'id' => $review->id]);

        $embed = $this->actingAs($admin)->get($url.'?embed=1')->assertOk();
        $embed->assertSee('Foto del vigilante')
            ->assertSee('Novedad: Sí')
            ->assertSee('Observación del supervisor: Botas rotas')
            ->assertSee('Presentación personal · Con novedad')
            ->assertSee('Con novedad')
            ->assertSee('Firma del vigilante')
            ->assertDontSee('Descargar PDF');

        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Descargar PDF')->assertSee('format=pdf', false);

        $pdf = $this->actingAs($admin)->get($url.'?format=pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->get('/api/supervision/sheets/review/'.$review->id)
            ->assertOk()
            ->assertSee('Foto del vigilante')
            ->assertDontSee('Descargar PDF')
            ->assertDontSee('window.print()', false);
    }

    public function test_shift_sheet_shows_selfies_once_odometer_and_saved_route_map(): void
    {
        Storage::fake('local');
        config(['google-maps.server_api_key' => 'test-key']);
        Http::fake(['maps.googleapis.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

        [, $review] = $this->reviewWithUniform();
        $shift = SupervisorShift::query()->findOrFail($review->supervisor_shift_id);
        foreach ([[3.4516, -76.5320], [3.4530, -76.5300], [3.4550, -76.5280]] as $i => [$lat, $lng]) {
            SupervisorShiftLocation::query()->create([
                'supervisor_shift_id' => $shift->id,
                'recorded_at' => now()->subMinutes(30 - $i * 5),
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => 8,
            ]);
        }

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $html = $this->actingAs($admin)
            ->get(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $shift->id, 'embed' => 1]))
            ->assertOk()
            ->assertSee('Pendiente cierre')
            ->assertSee('Odómetro de inicio')
            ->assertSee('Recorrido del turno')
            ->assertSee('data:image/png;base64,'.base64_encode('PNGDATA'), false)
            ->getContent();

        $this->assertSame(1, substr_count((string) $html, '>Selfie de inicio<'));
        Storage::disk('local')->assertExists('supervision/route-maps/shift-'.$shift->id.'-live.png');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'maptype=hybrid') && str_contains($request->url(), 'enc%3A'));
    }

    public function test_used_topic_is_deactivated_and_field_filter_lists_app_topics(): void
    {
        $this->seedWithPilot();
        $companyId = $this->pilotCompanyId();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $panel = SupervisorTrainingTopic::query()->create([
            'security_company_id' => $companyId, 'name' => 'Uso del extintor', 'normalized_name' => SupervisorTrainingTopic::normalize('Uso del extintor'),
            'is_active' => true, 'sort_order' => 10, 'created_from' => SupervisorTrainingTopic::FROM_PANEL,
        ]);
        SupervisorTrainingTopic::query()->create([
            'security_company_id' => $companyId, 'name' => 'Primeros auxilios', 'normalized_name' => SupervisorTrainingTopic::normalize('Primeros auxilios'),
            'is_active' => true, 'sort_order' => 20, 'created_from' => SupervisorTrainingTopic::FROM_APP,
        ]);

        $this->actingAs($admin)->get(route('company.supervision-training-topics.index', ['filter' => 'app']))
            ->assertOk()
            ->assertSee('Primeros auxilios')
            ->assertDontSee('Uso del extintor</span>', false);

        $this->actingAs($admin)
            ->from(route('company.supervision-training-topics.index', ['filter' => 'app']))
            ->put(route('company.supervision-training-topics.update', $panel), ['name' => 'Uso del extintor', 'is_active' => 0])
            ->assertRedirect(route('company.supervision-training-topics.index', ['filter' => 'app']));
        $this->assertFalse($panel->fresh()->is_active);

        $this->actingAs($admin)->get(route('company.supervision-training-topics.index', ['filter' => 'inactive']))
            ->assertOk()
            ->assertSee('Uso del extintor');
    }

    /** @return array{0: string, 1: SupervisorShiftReview} */
    private function reviewWithUniform(): array
    {
        $this->seedWithPilot();
        $companyId = $this->pilotCompanyId();
        $items = [
            SupervisorUniformItem::query()->create(['security_company_id' => $companyId, 'name' => 'Gorra', 'is_active' => true, 'sort_order' => 10]),
            SupervisorUniformItem::query()->create(['security_company_id' => $companyId, 'name' => 'Botas', 'is_active' => true, 'sort_order' => 20]),
        ];
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'app_build' => 23,
            'logs' => json_encode([['module' => 'uniform', 'payload' => ['items' => [
                ['item_id' => $items[0]->id, 'status' => 'no'],
                ['item_id' => $items[1]->id, 'status' => 'yes'],
            ], 'notes' => 'Botas rotas']]]),
            'log_photos' => [0 => ['uniform_1' => UploadedFile::fake()->image('u.jpg')]],
        ]))->assertCreated();

        return [$token, SupervisorShiftReview::query()->latest('id')->firstOrFail()];
    }
}
