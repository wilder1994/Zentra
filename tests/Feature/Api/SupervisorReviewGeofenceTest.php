<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
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
