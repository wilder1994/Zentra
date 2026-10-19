<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Mail\SupervisionNoveltyMail;
use App\Models\Client;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\User\EnsureClientScopeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SupervisionZonesAndNoveltiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_principal_and_shared_zones(): void
    {
        [$admin, $north, $south, $palmas, $torres] = $this->zonesSetup();

        $this->actingAs($admin)->postJson(route('company.supervision-zones.assign'), [
            'changes' => [
                ['client_id' => $palmas->id, 'zone_id' => $north->id, 'shared' => [$south->id, $north->id]],
                ['client_id' => $torres->id, 'zone_id' => $south->id, 'shared' => []],
            ],
        ])->assertOk()->assertJsonPath('updated', 2);

        $this->assertSame($north->id, (int) $palmas->fresh()->supervisor_zone_id);
        $this->assertSame([$south->id], $palmas->fresh()->sharedSupervisorZones->pluck('id')->map(fn ($id) => (int) $id)->all());

        $this->actingAs($admin)->postJson(route('company.supervision-zones.assign'), [
            'changes' => [['client_id' => $torres->id, 'zone_id' => null, 'shared' => [$north->id]]],
        ])->assertUnprocessable();

        $this->actingAs($admin)->get(route('company.supervision-zones.index'))
            ->assertOk()
            ->assertSee('Asignar clientes a zonas');
    }

    public function test_supervisor_only_sees_and_reviews_clients_of_active_zones(): void
    {
        [$admin, $north, $south, $palmas, $torres] = $this->zonesSetup();
        $palmas->update(['supervisor_zone_id' => $south->id]);
        $torres->update(['supervisor_zone_id' => $north->id]);

        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload(['zone_id' => $north->id]))
            ->assertCreated();

        $sites = collect($this->withToken($token)->getJson('/api/supervision/sites')->json('sites'))->pluck('id')->all();
        $this->assertSame([$torres->id], $sites);

        $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($palmas))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');

        $shift = SupervisorShift::query()->where('status', 'open')->firstOrFail();
        $this->actingAs($admin)
            ->putJson(route('company.supervision.shift-zones.update', $shift), ['zones' => [$south->id]])
            ->assertOk()
            ->assertJsonPath('extra.0.name', 'Sur');
        $this->app['auth']->forgetGuards();

        $saved = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($palmas));
        $saved->assertCreated();
        $this->assertSame($south->id, (int) SupervisorShiftReview::query()->findOrFail($saved->json('review.id'))->supervisor_zone_id);
    }

    public function test_company_without_zoned_clients_keeps_seeing_all(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();

        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $sites = collect($this->withToken($token)->getJson('/api/supervision/sites')->json('sites'))->pluck('id')->all();
        $this->assertContains($palmas->id, $sites);
    }

    public function test_responsible_gets_one_mail_per_review_with_novelty_and_supports_follow_checkbox(): void
    {
        Mail::fake();
        $this->seedWithPilot();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $this->internalClientAdmin($palmas, 'responsable@sj-seguridad.test');

        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();

        $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($palmas))->assertCreated();
        Mail::assertNothingQueued();

        $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($palmas, [
            'has_novelty' => 1,
            'notes' => 'Vigilante sin dotación completa',
            'logs' => json_encode([
                ['module' => 'inventory', 'payload' => ['items' => [['type' => 'Radio', 'status' => 'bad', 'notes' => 'Sin batería']]]],
                ['module' => 'folders', 'payload' => ['status' => 'missing', 'missing_items' => 'Minuta']],
            ]),
        ]))->assertCreated();

        Mail::assertQueued(SupervisionNoveltyMail::class, 1);
        Mail::assertQueued(SupervisionNoveltyMail::class, function (SupervisionNoveltyMail $mail): bool {
            return $mail->hasTo('responsable@sj-seguridad.test')
                && count($mail->sections) === 2
                && $mail->generalNote === 'Vigilante sin dotación completa'
                && $mail->sheetUrl !== null;
        });

        $support = SupervisorSupportType::query()->create([
            'security_company_id' => $palmas->security_company_id,
            'name' => 'Refuerzo',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $base = ['module' => 'supports', 'client_id' => $palmas->id, 'latitude' => 3.4516, 'longitude' => -76.5320];

        $this->withToken($token)->postJson('/api/supervision/logs', $base + [
            'payload' => ['support_type_id' => $support->id, 'reason' => 'Apoyo de rutina', 'novelty' => 'no'],
        ])->assertCreated()->assertJsonPath('log.outcome', 'ok');
        Mail::assertQueued(SupervisionNoveltyMail::class, 1);

        $this->withToken($token)->postJson('/api/supervision/logs', $base + [
            'payload' => ['support_type_id' => $support->id, 'reason' => 'Intento de hurto', 'novelty' => 'yes'],
        ])->assertCreated()->assertJsonPath('log.outcome', 'attention');
        Mail::assertQueued(SupervisionNoveltyMail::class, 2);

        $log = SupervisorFieldLog::query()->where('module', 'supports')->latest('id')->firstOrFail();
        $mail = Mail::queued(SupervisionNoveltyMail::class)->last();
        $this->assertStringContainsString('/fichas/', (string) $mail->sheetUrl);
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->get((string) $mail->sheetUrl)->assertOk()->assertSee('Intento de hurto');
        $this->get(route('supervision.sheets.signed', ['company' => $log->security_company_id, 'kind' => 'support', 'id' => $log->id]))
            ->assertForbidden();
    }

    public function test_internal_client_admin_porteria_is_read_only(): void
    {
        $this->seedWithPilot();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $responsible = $this->internalClientAdmin($palmas, 'resp2@sj-seguridad.test');

        $this->actingAs($responsible)
            ->withSession($this->porteriaSession($palmas))
            ->postJson(route('access.blocklist.store'), ['reason' => 'Prueba'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Portería en solo lectura: los registros los hacen los vigilantes.');
    }

    /** @return array{0: User, 1: SupervisorZone, 2: SupervisorZone, 3: Client, 4: Client} */
    private function zonesSetup(): array
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $companyId = (int) $admin->security_company_id;
        app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);

        $north = SupervisorZone::query()->where('security_company_id', $companyId)->where('name', 'Norte')->firstOrFail();
        $south = SupervisorZone::query()->where('security_company_id', $companyId)->where('name', 'Sur')->firstOrFail();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $torres = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $torres->update(['has_supervision' => true]);

        return [$admin, $north, $south, $palmas, $torres];
    }

    private function internalClientAdmin(Client $client, string $email): User
    {
        $user = User::query()->create([
            'name' => 'Responsable Interno',
            'username' => 'resp.interno.'.random_int(1000, 9999),
            'password' => 'Clave12345!',
            'is_active' => true,
            'security_company_id' => $client->security_company_id,
            'primary_client_id' => $client->id,
            'admin_origin' => 'internal',
            'corporate_email' => $email,
            'must_change_password' => false,
        ]);
        $user->syncRoles(['client-admin']);
        $user->clients()->attach($client->id, ['is_primary' => true, 'assigned_at' => now()]);
        app(EnsureClientScopeCatalog::class)->execute($user);

        return $user;
    }
}
