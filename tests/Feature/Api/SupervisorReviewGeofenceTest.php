<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\OperationalAlert;
use App\Models\SupervisionReviewRadius;
use App\Models\SupervisionReviewSettings;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Support\Supervision\SupervisorReviewGeofence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupervisorReviewGeofenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_blocked_outside_80_meters(): void
    {
        $this->seedWithPilot();
        $this->grantSupervision();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $payload = $this->supervisorReviewPayload($client, [
            'latitude' => 3.4516,
            'longitude' => -76.5320,
        ]);

        $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');
    }

    public function test_start_selfie_is_served_after_opening_shift(): void
    {
        $this->seedWithPilot();
        $this->grantSupervision();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertSuccessful();

        $this->withToken($token)->get('/api/supervision/shift-photo/start-selfie')->assertOk();
    }

    public function test_installation_exception_widens_radius_up_to_cap(): void
    {
        $this->seedWithPilot();
        $this->grantSupervision();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $post = $this->supervisionPostFor($client);
        $post->loadMissing('installation');
        $baseLat = (float) ($post->latitude ?? $post->installation->latitude);
        $baseLng = (float) ($post->longitude ?? $post->installation->longitude);
        $farLat = $baseLat + 0.0011;

        SupervisionReviewSettings::current()->update(['default_radius_m' => 80, 'max_radius_m' => 300]);
        $exception = SupervisionReviewRadius::query()->create([
            'security_company_id' => $client->security_company_id,
            'client_id' => $client->id,
            'installation_id' => $post->installation_id,
            'radius_m' => 200,
            'reason' => 'Instalación grande',
        ]);

        $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
                'latitude' => $farLat,
                'longitude' => $baseLng,
            ]))
            ->assertSuccessful();

        $exception->update(['radius_m' => 1000]);
        SupervisionReviewSettings::current()->update(['max_radius_m' => 100]);

        $this->assertSame(100, app(SupervisorReviewGeofence::class)->radiusFor($post->fresh()));
    }

    public function test_only_platform_settings_can_manage_radius(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.review-radius.store'), [
                'security_company_id' => $client->security_company_id,
                'radius_m' => 150,
                'reason' => 'Trampa',
            ])
            ->assertForbidden();

        $this->assertSame(0, SupervisionReviewRadius::query()->count());
    }

    public function test_super_admin_saves_and_removes_exception(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $super = User::role('super-admin')->firstOrFail();

        $this->actingAs($super)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Radio de revistas');

        $this->actingAs($super)
            ->post(route('admin.settings.review-radius.store'), [
                'security_company_id' => $client->security_company_id,
                'client_id' => $client->id,
                'radius_m' => 5000,
                'reason' => 'Muy grande',
            ])
            ->assertSessionHasErrors('radius_m');

        $this->actingAs($super)
            ->post(route('admin.settings.review-radius.store'), [
                'security_company_id' => $client->security_company_id,
                'client_id' => $client->id,
                'radius_m' => 150,
                'reason' => 'Conjunto amplio',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $radius = SupervisionReviewRadius::query()->firstOrFail();
        $this->assertSame(150, $radius->radius_m);
        $this->assertSame('client', $radius->level());

        $this->actingAs($super)->get(route('admin.settings.index'))->assertSee('Conjunto amplio');

        $this->actingAs($super)
            ->delete(route('admin.settings.review-radius.destroy', $radius))
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame(0, SupervisionReviewRadius::query()->count());
    }

    public function test_creating_far_post_is_allowed_and_annotated(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $site = $client->installations()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $site->id,
            'name' => 'Puesto lejano',
            'modality' => 12,
            'latitude' => 4.6000000,
            'longitude' => -74.0800000,
            'vista' => 'sitio',
        ])->assertRedirect();

        $this->assertDatabaseHas('supervisor_posts', ['name' => 'Puesto lejano']);
        $this->assertTrue(
            OperationalAlert::query()->where('body', 'like', '%Puesto lejano%')->exists()
        );
    }

    private function grantSupervision(): void
    {
        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );
    }
}
