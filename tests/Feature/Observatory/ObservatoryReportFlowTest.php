<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Location;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReporterMemory;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\User;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ObservatoryReportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_intake_lists_only_colegios_and_creates_event(): void
    {
        [$client, $colegio, $conjunto] = $this->sites();

        $this->get(route('observatory.public.show', $client->slug))
            ->assertOk()
            ->assertSee('Reportar', false)
            ->assertSee($client->name, false)
            ->assertSee('Quién eres', false)
            ->assertSee('Alumno', false)
            ->assertSee("Foto ' + (index + 1)", false)
            ->assertSee('capture="environment"', false);

        $this->get('/o/'.$client->slug.'/instalaciones?q=Santa')
            ->assertOk()
            ->assertJsonPath('installations.0.id', $colegio->id)
            ->assertJsonMissing(['id' => $conjunto->id]);

        $this->getJson(route('observatory.public.installations', ['slug' => $client->slug, 'q' => 'Palmas']))
            ->assertOk()
            ->assertJsonPath('installations', []);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $conjunto->id,
            'kind' => 'hurto',
            'body' => 'Intentaron entrar por la portería del conjunto.',
            'is_anonymous' => '0',
            'reporter_role' => 'padre',
            'reporter_name' => 'Ana Padre',
        ])->assertSessionHasErrors('installation_id');

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '0',
            'reporter_role' => 'padre',
            'reporter_name' => 'Ana Padre',
            'reporter_phone' => '3001112233',
        ])->assertRedirect();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('link', $report->source->value);
        $this->assertSame('padre', $report->reporter_role->value);
        $this->assertFalse($report->is_anonymous);
        $this->assertSame('Ana Padre', $report->reporter_name);
        $this->assertSame((int) $colegio->id, (int) $report->installation_id);

        $event = ObservatoryEvent::query()->firstOrFail();
        $this->assertSame(ObservatoryEventStatus::Nuevo, $event->status);
        $this->assertSame((int) $event->id, (int) $report->event_id);
        $this->assertSame(1, ObservatoryReport::query()->count());
    }

    public function test_public_form_remembers_age_and_authorization_by_phone(): void
    {
        [$client, $colegio] = $this->sites();

        $this->get(route('observatory.public.show', $client->slug))
            ->assertOk()
            ->assertSee('Recordamos tu edad y autorización en este dispositivo', false)
            ->assertSee('se seguirá preguntando la misma información', false);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '0',
            'is_adult' => '1',
            'minor_treatment_accepted' => '1',
            'remember_reporter' => '1',
            'reporter_role' => 'vecino',
            'reporter_name' => 'Ana Vecina',
            'reporter_phone' => '3001112233',
        ])->assertRedirect();

        $memory = ObservatoryReporterMemory::query()->firstOrFail();
        $this->assertSame((int) $client->id, (int) $memory->client_id);
        $this->assertSame('573001112233', $memory->phone);
        $this->assertSame('vecino', $memory->role);
        $this->assertTrue($memory->is_adult);
        $this->assertNotNull($memory->authorization_accepted_at);
        $this->assertNotNull($memory->expires_at);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Otro hecho distinto en la reja del colegio.',
            'is_anonymous' => '0',
            'is_adult' => '0',
            'minor_treatment_accepted' => '1',
            'remember_reporter' => '1',
            'reporter_role' => 'alumno',
            'reporter_name' => 'Alumno',
        ])->assertRedirect();

        $this->assertSame(1, ObservatoryReporterMemory::query()->count());
    }

    public function test_same_kind_within_hour_joins_one_event(): void
    {
        [$client, $colegio] = $this->sites();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Riña en el patio del descanso de la mañana.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Otra persona confirma la misma riña del patio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $this->assertSame(1, ObservatoryEvent::query()->count());
        $this->assertSame(2, ObservatoryReport::query()->count());
        $this->assertSame(2, ObservatoryEvent::query()->firstOrFail()->reports()->count());
    }

    public function test_expired_window_other_kind_or_closed_opens_new_event(): void
    {
        [$client, $colegio] = $this->sites();

        $this->travel(-61)->minutes();
        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $this->travelBack();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Otro hurto una hora después ya no es el mismo.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $this->assertSame(2, ObservatoryEvent::query()->count());

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Riña distinta al hurto aunque sea el mismo colegio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $this->assertSame(3, ObservatoryEvent::query()->count());

        ObservatoryEvent::query()->latest('id')->firstOrFail()->update([
            'status' => ObservatoryEventStatus::Cerrado,
            'closed_at' => now(),
        ]);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'El evento cerrado no recibe más reportes.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $this->assertSame(4, ObservatoryEvent::query()->count());
    }

    public function test_report_stores_pin_or_falls_back_to_school(): void
    {
        [$client, $colegio] = $this->sites();
        $colegio->update(['latitude' => '3.4372200', 'longitude' => '-76.5225000']);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'amenaza',
            'body' => 'Amenaza detrás de las canchas del colegio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
            'latitude' => '3.4512001',
            'longitude' => '-76.5311002',
        ])->assertRedirect();

        $first = ObservatoryReport::query()->firstOrFail();
        $this->assertEqualsWithDelta(3.4512001, (float) $first->latitude, 0.0000002);
        $this->assertEqualsWithDelta(-76.5311002, (float) $first->longitude, 0.0000002);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'amenaza',
            'body' => 'Misma amenaza; quien reporta no mueve el pin.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $second = ObservatoryReport::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(3.4372200, (float) $second->latitude, 0.0000002);
        $this->assertEqualsWithDelta(-76.5225000, (float) $second->longitude, 0.0000002);
        $this->assertSame((int) $first->event_id, (int) $second->event_id);
    }

    public function test_anonymous_report_hides_identity_and_photo_is_optional(): void
    {
        Storage::fake('public');
        [$client, $colegio] = $this->sites();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'amenaza',
            'body' => 'Amenazaron a un estudiante a la salida.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
            'reporter_name' => 'No debe guardarse',
            'reporter_phone' => '3000000000',
        ])->assertRedirect();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->reporter_name);
        $this->assertNull($report->reporter_phone);
        $this->assertNull($report->photo_path);

        $this->get(route('observatory.public.thanks', [$client->slug, $report]))
            ->assertOk()
            ->assertSee($report->event?->folio(), false);
    }

    public function test_optional_photo_is_stored(): void
    {
        Storage::fake('public');
        [$client, $colegio] = $this->sites();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Hubo una riña en el descanso de la mañana.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
            'photo' => UploadedFile::fake()->image('patio.jpg'),
        ])->assertRedirect();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertNotNull($report->photo_path);
        Storage::disk('public')->assertExists($report->photo_path);
    }

    public function test_optional_photos_array_stores_up_to_three(): void
    {
        Storage::fake('public');
        [$client, $colegio] = $this->sites();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Tres fotos del muro y la reja del colegio.',
            'is_anonymous' => '1',
            'is_adult' => '1',
            'minor_treatment_accepted' => '1',
            'reporter_role' => 'vecino',
            'photos' => [
                UploadedFile::fake()->image('uno.jpg'),
                UploadedFile::fake()->image('dos.jpg'),
            ],
        ])->assertRedirect();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertCount(2, $report->photoUrls());
        foreach ($report->photoUrls() as $url) {
            $this->assertNotSame('', $url);
        }
    }

    public function test_company_attends_and_client_admin_only_views(): void
    {
        [$client, $colegio] = $this->sites();
        $event = $this->openEvent($client, $colegio);
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $session = ['tenancy.active_client_id' => $client->id];

        $this->actingAs($company)
            ->get(route('company.observatory.events.index'))
            ->assertOk()
            ->assertSee('Tablero', false)
            ->assertSee('Eventos', false)
            ->assertSee('Compartir link', false)
            ->assertSee('/o/'.$client->slug, false)
            ->assertSee('Copiar', false)
            ->assertSee('Nuevos', false)
            ->assertSee('IE Santa Librada', false)
            ->assertSee('PPTX', false)
            ->assertSee('tablero.pptx', false);

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['vista' => 'eventos']))
            ->assertOk()
            ->assertSee($event->folio(), false)
            ->assertSee('Otro', false);

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', [
                'vista' => 'eventos',
                'from' => now()->addDay()->toDateString(),
                'to' => now()->addDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertDontSee($event->folio(), false);

        $this->actingAs($company)
            ->get(route('company.observatory.events.show', $event))
            ->assertOk()
            ->assertSee('Pasar a en atención', false);

        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.observatory.events.index'))
            ->assertOk()
            ->assertSee('Nuevo reporte', false)
            ->assertSee('Compartir link', false)
            ->assertSee('/o/'.$client->slug, false)
            ->assertSee('Copiar', false);

        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.observatory.reports.create'))
            ->assertOk()
            ->assertSee($clientAdmin->name, false)
            ->assertSee('no puedes realizar el reporte', false)
            ->assertSee('administrador de la instalación', false);

        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.observatory.events.show', $event))
            ->assertOk()
            ->assertSee($event->folio(), false);

        $this->actingAs($clientAdmin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), ['status' => 'en_atencion'])
            ->assertForbidden();

        $this->assertSame(ObservatoryEventStatus::Nuevo, $event->fresh()->status);
    }

    public function test_installation_admin_closes_status_and_cannot_reopen(): void
    {
        [$client, $colegio, , $otherColegio] = $this->sites();
        $event = $this->openEvent($client, $colegio);
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $admin = $this->makeSiteAdmin($company, $client, $colegio, 'laura.obs@palmas.test', '1098000881');
        $other = $this->makeSiteAdmin($company, $client, $otherColegio, 'otro.obs@palmas.test', '1098000882');
        $support = $this->makeSiteAdmin($company, $client, $colegio, 'apoyo.obs@palmas.test', '1098000883', 'support');
        $session = ['tenancy.active_client_id' => $client->id];

        $this->actingAs($other)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), ['status' => 'en_atencion'])
            ->assertForbidden();

        $this->actingAs($support)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), ['status' => 'en_atencion'])
            ->assertForbidden();

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'cerrado',
                'note' => 'Intento de cierre sin pasar por atención.',
            ])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), ['status' => 'en_atencion'])
            ->assertSessionHasErrors('note');

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'en_atencion',
                'note' => 'Se tomó el folio y se avisó a convivencia.',
            ])
            ->assertRedirect(route('client.observatory.events.show', $event));

        $this->assertSame(ObservatoryEventStatus::EnAtencion, $event->fresh()->status);

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'en_atencion',
                'note' => 'Se habló con el coordinador y se sigue verificando.',
            ])
            ->assertRedirect();

        $this->assertSame(ObservatoryEventStatus::EnAtencion, $event->fresh()->status);
        $this->assertSame(2, $event->statusLogs()->count());
        $this->assertSame(
            'Se habló con el coordinador y se sigue verificando.',
            $event->statusLogs()->orderByDesc('id')->first()?->note,
        );

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'cerrado',
                'note' => 'Se atendió con la familia y el caso quedó resuelto.',
            ])
            ->assertRedirect();

        $closed = $event->fresh();
        $this->assertSame(ObservatoryEventStatus::Cerrado, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame((int) $admin->id, (int) $closed->closed_by_user_id);

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'en_atencion',
                'note' => 'Intento de reabrir un folio ya cerrado.',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_site_admin_moves_reports_between_folios(): void
    {
        [$client, $colegio, , $otherColegio] = $this->sites();
        $otherColegio->update(['latitude' => '3.4511000', 'longitude' => '-76.5311000']);
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $admin = $this->makeSiteAdmin($company, $client, $colegio, 'une.obs@palmas.test', '1098000885');
        $other = $this->makeSiteAdmin($company, $client, $otherColegio, 'otra.une@palmas.test', '1098000886');
        $support = $this->makeSiteAdmin($company, $client, $colegio, 'apoyo.une@palmas.test', '1098000887', 'support');
        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $session = ['tenancy.active_client_id' => $client->id];

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $keep = ObservatoryEvent::query()->latest('id')->firstOrFail();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Riña que en realidad era el mismo incidente del muro.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $source = ObservatoryEvent::query()->latest('id')->firstOrFail();
        $moving = $source->reports()->firstOrFail();
        $this->assertNotSame((int) $keep->id, (int) $source->id);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $otherColegio->id,
            'kind' => 'hurto',
            'body' => 'Hurto en otro colegio, destino posible del traslado.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $foreign = ObservatoryEvent::query()->latest('id')->firstOrFail();

        $payload = [
            'installation_id' => $colegio->id,
            'destination' => (string) $keep->id,
            'note' => 'El reporte corresponde al mismo incidente del muro.',
        ];

        $this->actingAs($clientAdmin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$source, $moving]), $payload)
            ->assertForbidden();
        $this->actingAs($support)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$source, $moving]), $payload)
            ->assertForbidden();
        $this->actingAs($other)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$source, $moving]), $payload)
            ->assertForbidden();

        $this->actingAs($admin)->withSession($session)
            ->get(route('client.observatory.events.show', $source))
            ->assertOk()
            ->assertSee('Pasar reporte', false)
            ->assertSee($moving->code(), false)
            ->assertDontSee('Unir aquí', false);

        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.observatory.events.show', $source))
            ->assertOk()
            ->assertDontSee('Pasar reporte', false);

        $this->actingAs($company)
            ->get(route('company.observatory.events.show', $source))
            ->assertOk()
            ->assertSee('Pasar reporte', false);

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$source, $moving]), [
                'installation_id' => $colegio->id,
                'destination' => (string) $keep->id,
                'note' => 'corto',
            ])
            ->assertSessionHasErrors('note');

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$source, $moving]), $payload)
            ->assertRedirect(route('client.observatory.events.show', $source));

        $this->assertNotNull(ObservatoryEvent::query()->find($source->id));
        $this->assertSame(ObservatoryEventStatus::Cerrado, $source->fresh()->status);
        $this->assertSame(0, $source->fresh()->reports()->count());
        $this->assertSame(2, $keep->fresh()->reports()->count());
        $this->assertTrue($source->fresh()->statusLogs()->where('note', 'like', '%'.$moving->code().'%')->exists());
        $this->assertTrue($keep->fresh()->statusLogs()->where('note', 'like', '%'.$moving->code().'%')->exists());

        $firstReport = $keep->fresh()->reports()->orderBy('id')->firstOrFail();
        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$keep, $firstReport]), [
                'installation_id' => $colegio->id,
                'destination' => 'new',
                'note' => 'Este reporte se atiende en un folio aparte.',
            ])
            ->assertRedirect(route('client.observatory.events.show', $keep));

        $this->assertSame(1, $keep->fresh()->reports()->count());
        $this->assertSame(ObservatoryEventStatus::Nuevo, $keep->fresh()->status);
        $split = ObservatoryEvent::query()->whereKeyNot([$keep->id, $source->id, $foreign->id])->firstOrFail();
        $this->assertSame(1, $split->reports()->count());
        $this->assertSame(ObservatoryEventStatus::Nuevo, $split->status);

        $this->actingAs($company)
            ->patch(route('company.observatory.events.status', $foreign), [
                'status' => 'en_atencion',
                'note' => 'El otro colegio ya está atendiendo su folio.',
            ])
            ->assertRedirect();
        $this->actingAs($company)
            ->patch(route('company.observatory.events.status', $foreign), [
                'status' => 'cerrado',
                'note' => 'El otro colegio cerró su folio antes del traslado.',
            ])
            ->assertRedirect();

        $last = $keep->fresh()->reports()->firstOrFail();
        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$keep, $last]), [
                'installation_id' => $otherColegio->id,
                'destination' => (string) $foreign->id,
                'note' => 'El reporte era del otro colegio, no de este.',
            ])
            ->assertRedirect(route('client.observatory.events.show', $keep));

        $moved = $last->fresh();
        $this->assertSame((int) $foreign->id, (int) $moved->event_id);
        $this->assertSame((int) $otherColegio->id, (int) $moved->installation_id);
        $this->assertSame('3.4511000', (string) $moved->latitude);
        $this->assertSame(ObservatoryEventStatus::Cerrado, $keep->fresh()->status);
        $this->assertSame(0, $keep->fresh()->reports()->count());
        $this->assertSame(ObservatoryEventStatus::Cerrado, $foreign->fresh()->status);
        $this->assertSame(2, $foreign->fresh()->reports()->count());

        $this->actingAs($company)
            ->get(route('company.observatory.events.show', $keep))
            ->assertOk()
            ->assertDontSee('Pasar reporte', false);
    }

    public function test_cannot_detach_from_closed_event(): void
    {
        [$client, $colegio] = $this->sites();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $admin = $this->makeSiteAdmin($company, $client, $colegio, 'cierra.une@palmas.test', '1098000888');
        $session = ['tenancy.active_client_id' => $client->id];

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Riña en el patio del descanso de la mañana.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Otra persona confirma la misma riña del patio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $event = ObservatoryEvent::query()->firstOrFail();
        $this->assertSame(2, $event->reports()->count());
        $report = $event->reports()->firstOrFail();

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'en_atencion',
                'note' => 'Se está atendiendo la riña del patio.',
            ])
            ->assertRedirect();
        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.observatory.events.status', $event), [
                'status' => 'cerrado',
                'note' => 'La riña se resolvió con mediación escolar.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)->withSession($session)
            ->get(route('client.observatory.events.show', $event))
            ->assertOk()
            ->assertDontSee('Pasar reporte', false)
            ->assertDontSee('Unir aquí', false);

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.events.reports.move', [$event, $report]), [
                'installation_id' => $colegio->id,
                'destination' => 'new',
                'note' => 'No debería salir de un folio cerrado.',
            ])
            ->assertSessionHasErrors('report');

        $this->assertSame(1, ObservatoryEvent::query()->count());
        $this->assertSame(2, $event->fresh()->reports()->count());
    }

    public function test_map_lists_only_scoped_colegios_with_coordinates(): void
    {
        config(['google-maps.api_key' => 'test-maps-key']);
        [$client, $colegio, $conjunto, $other] = $this->sites();
        $colegio->update(['latitude' => '3.4372200', 'longitude' => '-76.5225000']);
        $conjunto->update([
            'name' => 'IPS Salud del Ingenio',
            'latitude' => '3.4516000',
            'longitude' => '-76.5320000',
        ]);
        $other->update(['latitude' => '3.4600000', 'longitude' => '-76.5100000']);

        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($company)
            ->get(route('company.observatory.events.index'))
            ->assertOk()
            ->assertSee('test-maps-key', false)
            ->assertSee('IE Santa Librada', false)
            ->assertSee('IE Republica del Peru', false)
            ->assertSee('IPS Salud del Ingenio', false)
            ->assertSee('Agrupado', false)
            ->assertSee('Mapa de calor', false)
            ->assertSee('Filtros', false)
            ->assertSee('Todas', false)
            ->assertSee('cali-comunas.geojson', false)
            ->assertDontSee('libraries=visualization', false)
            ->assertDontSee('Comuna Cali', false);

        $admin = $this->makeSiteAdmin($company, $client, $colegio, 'mapa.obs@palmas.test', '1098000884');
        $this->actingAs($admin)->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.observatory.events.index'))
            ->assertOk()
            ->assertSee('IE Santa Librada', false)
            ->assertDontSee('IE Republica del Peru', false)
            ->assertDontSee('IPS Salud del Ingenio', false)
            ->assertSee('Comuna', false);
    }

    public function test_board_filters_colegios_by_cali_comuna(): void
    {
        config(['google-maps.api_key' => 'test-maps-key']);
        [$client, $colegio, , $other] = $this->sites();
        $colegio->update(['latitude' => '3.4372200', 'longitude' => '-76.5225000']);
        $other->update(['latitude' => '4.6097100', 'longitude' => '-74.0817500']);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Hurto en el colegio de la comuna nueve.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $other->id,
            'kind' => 'rina',
            'body' => 'Riña en un colegio fuera del perímetro urbano de Cali.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['comuna' => '09']))
            ->assertOk()
            ->assertSee('Comuna: Comuna 09', false)
            ->assertViewHas('events', fn ($events): bool => $this->sitesOf($events) === ['IE Santa Librada'])
            ->assertViewHas('map', fn (array $map): bool => collect($map['sites'])->pluck('name')->all() === ['IE Santa Librada']);

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['comuna' => 'fuera', 'vista' => 'eventos']))
            ->assertOk()
            ->assertViewHas('events', fn ($events): bool => $this->sitesOf($events) === ['IE Republica del Peru']);
    }

    /** @return list<string> */
    private function sitesOf(LengthAwarePaginator $events): array
    {
        return collect($events->items())->map(fn (ObservatoryEvent $event): ?string => $event->installation?->name)->unique()->values()->all();
    }

    public function test_cali_comunas_geojson_is_public(): void
    {
        $response = $this->get(route('geo.cali-comunas'));
        $response->assertOk();
        $file = $response->baseResponse->getFile();
        $payload = json_decode((string) file_get_contents($file->getPathname()), true);

        $this->assertSame('FeatureCollection', $payload['type'] ?? null);
        $this->assertCount(22, $payload['features'] ?? []);
    }

    public function test_empty_board_still_renders_charts(): void
    {
        [$client] = $this->sites();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->actingAs($admin)->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.observatory.events.index'))
            ->assertOk()
            ->assertSee('Tendencia endógena', false)
            ->assertSee('Delitos endógenos por comuna', false)
            ->assertSee('Instalaciones con más reportes', false)
            ->assertDontSee('Días con más reportes', false)
            ->assertSee('Fuentes', false)
            ->assertSee('Atención de folios', false)
            ->assertDontSee('docs/observatory', false)
            ->assertSee('Compartir link', false)
            ->assertSee('Catálogo', false)
            ->assertDontSee('Catálogo de riesgo endógeno', false)
            ->assertDontSee('Catálogo de riesgo exógeno', false);
    }

    public function test_company_board_lists_each_type_slug_once(): void
    {
        [$client] = $this->sites();
        $torres = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $ensure = app(EnsureObservatoryReportTypesService::class);
        $ensure->execute($client);
        $ensure->execute($torres);

        $this->assertSame(1, ObservatoryReportType::query()->where('security_company_id', $client->security_company_id)->where('slug', 'amenaza')->count());

        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $html = $this->actingAs($company)
            ->get(route('company.observatory.events.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'value="amenaza"'));
        $this->assertSame(0, preg_match_all('#</span>Amenaza#', $html));
    }

    public function test_public_report_requires_role_and_keeps_it_when_anonymous(): void
    {
        [$client, $colegio] = $this->sites();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '1',
        ])->assertSessionHasErrors('reporter_role');

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Vieron a alguien saltando el muro del colegio.',
            'is_anonymous' => '1',
            'is_adult' => '1',
            'minor_treatment_accepted' => '1',
            'reporter_role' => 'alumno',
            'reporter_name' => 'No debe guardarse',
        ])->assertRedirect();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('alumno', $report->reporter_role->value);
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->reporter_name);
    }

    public function test_site_admin_and_support_report_from_panel(): void
    {
        [$client, $colegio] = $this->sites();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $rector = $this->makeSiteAdmin($company, $client, $colegio, 'rector.rep@palmas.test', '1098000991', 'admin');
        $apoyo = $this->makeSiteAdmin($company, $client, $colegio, 'apoyo.rep@palmas.test', '1098000992', 'support');
        $session = ['tenancy.active_client_id' => $client->id];

        $this->actingAs($rector)
            ->withSession($session)
            ->get(route('client.observatory.reports.create'))
            ->assertOk()
            ->assertSee('Rector', false);

        $this->actingAs($rector)
            ->withSession($session)
            ->post(route('client.observatory.reports.store'), [
                'installation_id' => $colegio->id,
                'kind' => 'amenaza',
                'body' => 'Amenaza reportada por el rector desde el panel.',
                'is_anonymous' => '0',
            ])
            ->assertRedirect();

        $rectorReport = ObservatoryReport::query()->latest('id')->firstOrFail();
        $this->assertSame('panel', $rectorReport->source->value);
        $this->assertSame('rector', $rectorReport->reporter_role->value);
        $this->assertSame($rector->name, $rectorReport->reporter_name);
        $this->assertSame((int) $rector->id, (int) $rectorReport->reported_by_user_id);

        $this->actingAs($apoyo)
            ->withSession($session)
            ->post(route('client.observatory.reports.store'), [
                'installation_id' => $colegio->id,
                'kind' => 'amenaza',
                'body' => 'El apoyo confirma la misma amenaza del rector.',
                'is_anonymous' => '1',
            ])
            ->assertRedirect();

        $apoyoReport = ObservatoryReport::query()->latest('id')->firstOrFail();
        $this->assertSame('apoyo', $apoyoReport->reporter_role->value);
        $this->assertTrue($apoyoReport->is_anonymous);
        $this->assertNull($apoyoReport->reporter_name);
        $this->assertNull($apoyoReport->reported_by_user_id);
        $this->assertSame((int) $rectorReport->event_id, (int) $apoyoReport->event_id);

        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $this->actingAs($clientAdmin)
            ->withSession($session)
            ->get(route('client.observatory.reports.create'))
            ->assertOk()
            ->assertSee('no puedes realizar el reporte', false);

        $this->actingAs($clientAdmin)
            ->withSession($session)
            ->post(route('client.observatory.reports.store'), [
                'installation_id' => $colegio->id,
                'kind' => 'amenaza',
                'body' => 'La secretaría no debe poder reportar desde el panel.',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_reports_from_field_app(): void
    {
        [$client, $colegio] = $this->sites();
        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();

        $this->withToken($token)
            ->getJson('/api/supervision/observatory/sites?q=Santa')
            ->assertOk()
            ->assertJsonPath('sites.0.id', $colegio->id)
            ->assertJsonStructure(['google_maps' => ['api_key', 'center']]);

        Storage::fake('public');
        $photo = UploadedFile::fake()->image('novedad.jpg', 80, 80);

        $this->withToken($token)
            ->post('/api/supervision/observatory/reports', [
                'installation_id' => $colegio->id,
                'kind' => 'hurto',
                'body' => 'El supervisor vio el hurto desde la patrulla.',
                'is_anonymous' => '0',
                'latitude' => 3.4516,
                'longitude' => -76.5320,
                'photos' => [$photo],
            ])
            ->assertCreated()
            ->assertJsonPath('report.event_id', ObservatoryEvent::query()->value('id'));

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('campo', $report->source->value);
        $this->assertSame('supervisor', $report->reporter_role->value);
        $this->assertSame($user->name, $report->reporter_name);
        $this->assertSame((int) $client->id, (int) $report->client_id);
        $this->assertNotNull($report->photo_path);
        $this->assertCount(1, $report->photoUrls());
    }

    public function test_supervisor_observatory_report_requires_a_photo(): void
    {
        [, $colegio] = $this->sites();
        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();

        $this->withToken($token)
            ->postJson('/api/supervision/observatory/reports', [
                'installation_id' => $colegio->id,
                'kind' => 'hurto',
                'body' => 'El supervisor vio el hurto desde la patrulla.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_minuta_novedad_can_copy_to_observatory_only_on_colegio_door(): void
    {
        [$client, $colegio, $conjunto] = $this->sites();
        config(['access.geo.required' => false, 'access.shifts.enforced' => false]);

        $door = Location::query()->create([
            'client_id' => $client->id,
            'installation_id' => $colegio->id,
            'code' => 'COL-01',
            'name' => 'Portería Santa Librada',
            'type' => 'access_point',
            'is_active' => true,
        ]);
        $conjuntoDoor = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('installation_id', $conjunto->id)
            ->where('is_active', true)
            ->first();

        $vigilante = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $supervisor = $this->companySupervisor();
        $session = $this->porteriaSession($client, (int) $door->id);

        $this->actingAs($vigilante)
            ->withSession($session)
            ->get(route('access.guard_logs.index', ['door' => $door->id]))
            ->assertOk()
            ->assertSee('Portería Santa Librada', false)
            ->assertSee('También al Observatorio');

        $this->actingAs($vigilante)
            ->withSession($session)
            ->post(route('access.guard_logs.store'), [
                'location_id' => $door->id,
                'log_time' => now()->format('Y-m-d H:i:s'),
                'type' => 'novedad',
                'shift_type' => 'diurno',
                'description' => 'Novedad en la puerta del colegio para el observatorio.',
                'signed' => '1',
                'supervision_code' => $supervisor->supervisor_code,
                'to_observatory' => '1',
                'observatory_kind' => 'otro',
                'observatory_anonymous' => '0',
                'latitude' => 3.4516,
                'longitude' => -76.5320,
            ])
            ->assertRedirect(route('access.guard_logs.index', ['door' => $door->id]));

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('campo', $report->source->value);
        $this->assertSame('vigilante', $report->reporter_role->value);
        $this->assertSame($vigilante->name, $report->reporter_name);
        $this->assertSame((int) $colegio->id, (int) $report->installation_id);

        if ($conjuntoDoor !== null) {
            $this->actingAs($vigilante)
                ->withSession($session)
                ->post(route('access.guard_logs.store'), [
                    'location_id' => $conjuntoDoor->id,
                    'log_time' => now()->format('Y-m-d H:i:s'),
                    'type' => 'novedad',
                    'shift_type' => 'diurno',
                    'description' => 'Novedad de conjunto no debe ir al observatorio.',
                    'signed' => '1',
                    'supervision_code' => $supervisor->supervisor_code,
                    'to_observatory' => '1',
                    'observatory_kind' => 'otro',
                    'latitude' => 3.4516,
                    'longitude' => -76.5320,
                ])
                ->assertRedirect(route('access.guard_logs.index', ['door' => $conjuntoDoor->id]));

            $this->assertSame(1, ObservatoryReport::query()->count());
        }
    }

    public function test_guard_cannot_open_observatory(): void
    {
        $this->sites();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)
            ->get(route('company.observatory.events.index'))
            ->assertForbidden();

        $this->actingAs($guard)
            ->withSession(['tenancy.active_client_id' => Client::query()->where('slug', 'palmas-del-ingenio')->value('id')])
            ->get(route('client.observatory.events.index'))
            ->assertForbidden();

        $this->actingAs($guard)
            ->get(route('company.observatory.board.export'))
            ->assertForbidden();
    }

    public function test_company_and_client_admin_download_board_pptx(): void
    {
        [$client, $colegio] = $this->sites();
        $this->openEvent($client, $colegio);
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $session = ['tenancy.active_client_id' => $client->id];
        $from = now()->toDateString();

        $companyFile = $this->actingAs($company)
            ->get(route('company.observatory.board.export', [
                'from' => $from,
                'to' => $from,
                'client_id' => $client->id,
            ]));
        $companyFile->assertOk();
        $this->assertStringContainsString(
            'Observatorio_',
            (string) $companyFile->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            $from,
            (string) $companyFile->headers->get('content-disposition'),
        );

        $clientFile = $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.observatory.board.export', [
                'from' => $from,
                'to' => $from,
            ]));
        $clientFile->assertOk();
        $this->assertStringContainsString(
            'Observatorio_',
            (string) $clientFile->headers->get('content-disposition'),
        );
    }

    /** @return array{0: Client, 1: Installation, 2: Installation, 3: Installation} */
    private function sites(): array
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $conjunto = Installation::query()->where('client_id', $client->id)->firstOrFail();
        $conjunto->update(['installation_profile_id' => $this->installationProfileId('conjunto')]);

        $colegio = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Santa Librada',
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => '176001000101',
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        $other = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Republica del Peru',
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => '176001000102',
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        return [$client, $colegio, $conjunto, $other];
    }

    private function openEvent(Client $client, Installation $colegio): ObservatoryEvent
    {
        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'otro',
            'body' => 'Situación reportada para seguimiento del observatorio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        return ObservatoryEvent::query()->firstOrFail();
    }

    private function makeSiteAdmin(User $companyAdmin, Client $client, Installation $site, string $email, string $document, string $permission = 'admin'): User
    {
        $suffix = substr($document, -4);

        $this->actingAs($companyAdmin)->post(route('company.users.store'), [
            'role' => 'client-installation-admin',
            'origin' => 'external',
            'name' => 'Admin '.$suffix,
            'document_number' => $document,
            'job_title' => $permission === 'support' ? 'Auxiliar' : 'Rector',
            'email' => $email,
            'username' => ($permission === 'support' ? 'apoyo.obs.' : 'admin.obs.').$suffix,
            'password' => 'Cliente123!',
            'password_confirmation' => 'Cliente123!',
            'client_ids' => [$client->id],
            'installation_ids' => [$site->id],
            'installation_permission' => $permission,
            'is_active' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', $email)->firstOrFail();
        $user->update(['must_change_password' => false]);

        return $user;
    }
}
