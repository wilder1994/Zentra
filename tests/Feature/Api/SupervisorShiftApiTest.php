<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\GuardLog;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShift;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Support\Supervision\RecommendationEvidencePhotos;
use App\Support\Supervision\WeaponInspectionPhotos;
use Illuminate\Http\UploadedFile;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SupervisorShiftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_login_with_username_or_legacy_email(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();

        $this->postJson('/api/supervision/login', [
            'login' => $user->username,
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
            'device_id' => self::TEST_DEVICE_ID,
        ])->assertOk()
            ->assertJsonPath('must_change_password', true)
            ->assertJsonPath('user.username', $user->username);

        $user->update(['email' => 'legado.supervisor@sj-seguridad.test']);

        $this->postJson('/api/supervision/login', [
            'login' => 'legado.supervisor@sj-seguridad.test',
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
            'device_id' => self::TEST_DEVICE_ID,
        ])->assertOk()->assertJsonPath('user.username', $user->username);
    }

    public function test_supervisor_can_open_ping_and_close_shift(): void
    {
        $this->seedWithPilot();

        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::Sit1,
        );

        $token = $this->loginCompanySupervisor();

        $open = $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());
        $open->assertCreated();

        $ping = $this->withToken($token)->postJson('/api/supervision/shifts/ping', [
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'pending_outbox' => 3,
            'screen_on' => false,
            'source' => 'apk',
        ]);
        $ping->assertOk();
        $shift = SupervisorShift::query()->findOrFail($open->json('shift.id'));
        $this->assertSame(3, (int) $shift->pending_outbox_count);
        $location = $shift->locations()->latest('id')->first();
        $this->assertFalse((bool) $location?->screen_on);
        $this->assertSame('apk', $location?->source);

        $close = $this->withToken($token)->post('/api/supervision/shifts/close', $this->supervisorShiftClosePayload());
        $close->assertOk();
        $this->assertSame('closed', $close->json('shift.status'));
        $this->assertSame(12, $close->json('shift.km_traveled'));
    }

    public function test_open_shift_requires_ppe_and_photos(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();

        $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/shifts/open', [
                'km_start' => 100,
            ])->assertUnprocessable();
    }

    public function test_open_shift_rejects_unchecked_ppe_in_spanish(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $token = $this->loginCompanySupervisor();
        $payload = $this->supervisorShiftOpenPayload();
        $payload['ppe_checklist']['gloves_ok'] = false;

        $response = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/shifts/open', $payload);

        $response->assertUnprocessable();
        $this->assertSame(
            'Debe confirmar: Guantes',
            $response->json('errors')['ppe_checklist.gloves_ok'][0] ?? $response->json('errors.ppe_checklist.gloves_ok.0'),
        );
        $this->assertStringNotContainsString('validation.accepted', (string) $response->getContent());
    }

    public function test_intake_lists_checklists_and_empty_fleet(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();

        $this->withToken($token)->getJson('/api/supervision/intake')
            ->assertOk()
            ->assertJsonPath('first_vehicle', true)
            ->assertJsonPath('zones.0.name', 'Norte')
            ->assertJsonPath('shift_templates.0.name', 'Día')
            ->assertJsonStructure(['ppe', 'vehicle_check', 'zones', 'shift_templates', 'vehicles']);
    }

    public function test_supervisor_first_login_changes_username_and_password(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $token = $this->loginCompanySupervisor();

        $this->withToken($token)->postJson('/api/supervision/password', [
            'username' => 'luis.rojas.9911',
            'password' => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
        ])->assertOk()->assertJsonPath('username', 'luis.rojas.9911');

        $user->refresh();
        $this->assertSame('luis.rojas.9911', $user->username);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NuevaClave123!', $user->password));
    }

    public function test_login_fails_without_pro_package(): void
    {
        $this->seedWithPilot();

        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute($user->securityCompany, null);

        $this->postJson('/api/supervision/login', [
            'login' => $user->username,
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
        ])->assertForbidden();
    }

    public function test_pro_review_requires_supervision_flag(): void
    {
        $this->seedWithPilot();

        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::Sit5,
        );

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $client->update(['has_supervision' => false]);

        $token = $this->loginCompanySupervisor();

        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $this->withToken($token)->postJson('/api/supervision/reviews', [
            'client_id' => $client->id,
            'notes' => 'Revista',
        ])->assertUnprocessable();
    }

    public function test_review_saves_supervisor_gps_without_minuta(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $guardLogsBefore = GuardLog::query()->count();

        $posts = $this->withToken($token)->getJson('/api/supervision/posts?client_id='.$client->id);
        $posts->assertOk();
        $this->assertNotEmpty($posts->json('posts'));
        $postNames = collect($posts->json('posts'))->pluck('name')->all();
        $this->assertContains('Portería principal', $postNames);
        $this->assertNotContains('Puerta principal', $postNames);

        $guards = $this->withToken($token)->getJson('/api/supervision/guards?document=1144001122');
        $guards->assertOk()->assertJsonPath('guards.0.document_number', '1144001122');

        $payload = $this->supervisorReviewPayload($client, ['has_novelty' => 1]);
        $saved = $this->withToken($token)->post('/api/supervision/reviews', $payload);
        $saved->assertCreated()
            ->assertJsonPath('review.client_id', $client->id)
            ->assertJsonPath('review.has_novelty', true)
            ->assertJsonPath('review.supervisor_post_id', $this->supervisionPostFor($client)->id);
        $this->assertEqualsWithDelta((float) $payload['latitude'], (float) $saved->json('review.latitude'), 0.0002);
        $this->assertEqualsWithDelta((float) $payload['longitude'], (float) $saved->json('review.longitude'), 0.0002);

        $this->assertSame($guardLogsBefore, GuardLog::query()->count());

        $current = $this->withToken($token)->getJson('/api/supervision/shifts/current');
        $current->assertOk()->assertJsonPath('current_review.id', $saved->json('review.id'));
    }

    public function test_review_commits_inventory_logs_in_same_request(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $saved = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'logs' => json_encode([
                [
                    'module' => 'inventory',
                    'payload' => [
                        'items' => [
                            ['type' => 'Celular', 'status' => 'good', 'notes' => 'Sin novedad'],
                        ],
                    ],
                ],
            ]),
        ]));
        $saved->assertCreated();

        $this->assertTrue(
            SupervisorFieldLog::query()
                ->where('supervisor_shift_review_id', $saved->json('review.id'))
                ->where('module', 'inventory')
                ->exists()
        );
    }

    public function test_review_commits_weapons_with_photos(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $companyId = $this->pilotCompanyId();
        $type = SupervisorWeaponType::query()->create([
            'security_company_id' => $companyId,
            'name' => 'Pistola',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $brand = SupervisorWeaponBrand::query()->create([
            'security_company_id' => $companyId,
            'name' => 'Glock',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $photos = [];
        foreach (WeaponInspectionPhotos::requiredKeys(false) as $slot) {
            $photos[$slot] = UploadedFile::fake()->image($slot.'.jpg');
        }

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $saved = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'logs' => json_encode([
                [
                    'module' => 'weapons',
                    'payload' => [
                        'weapon_type_id' => $type->id,
                        'weapon_brand_id' => $brand->id,
                        'serial' => 'AR-9981',
                        'caliber' => '9 mm',
                        'permit_kind' => 'tenencia',
                        'permit_number' => 'PT-123',
                        'permit_expires_at' => now()->addYear()->toDateString(),
                        'ammo_quantity' => 12,
                        'ammo_caliber' => '9 mm',
                        'novelty' => 'no',
                        'cleaned' => 'no',
                    ],
                ],
            ]),
            'log_photos' => [0 => $photos],
        ]));
        $saved->assertCreated();

        $log = SupervisorFieldLog::query()
            ->where('supervisor_shift_review_id', $saved->json('review.id'))
            ->where('module', 'weapons')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('AR-9981', $log->payload['serial'] ?? null);
        $this->assertSame('no', $log->payload['cleaned'] ?? null);
        $this->assertNotEmpty($log->payload['photos']['right'] ?? null);
        $this->assertArrayNotHasKey('cleaning', $log->payload['photos'] ?? []);
    }

    public function test_review_commits_recommendations_with_photos(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $companyId = $this->pilotCompanyId();
        $physical = SupervisorRiskType::query()->create([
            'security_company_id' => $companyId,
            'name' => 'Riesgo físico',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $public = SupervisorRiskType::query()->create([
            'security_company_id' => $companyId,
            'name' => 'Riesgo público',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $photos = [];
        foreach (RecommendationEvidencePhotos::SLOTS as $slot) {
            $photos['0_'.$slot] = UploadedFile::fake()->image($slot.'.jpg');
            $photos['1_'.$slot] = UploadedFile::fake()->image($slot.'-b.jpg');
        }

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $saved = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'logs' => json_encode([
                [
                    'module' => 'recommendations',
                    'payload' => [
                        'items' => [
                            [
                                'risk_type_id' => $physical->id,
                                'risk' => 'Tramos oscuros en el costado norte.',
                                'likelihood' => '5',
                                'impact' => '4',
                                'consequence' => 'Facilita un ingreso no autorizado.',
                                'treatment' => 'Instalar luminarias LED.',
                            ],
                            [
                                'risk_type_id' => $public->id,
                                'risk' => 'La chapa queda suelta.',
                                'likelihood' => '2',
                                'impact' => '2',
                                'consequence' => 'La puerta no asegura el acceso.',
                                'treatment' => 'Cambiar chapa.',
                            ],
                        ],
                    ],
                ],
            ]),
            'log_photos' => [0 => $photos],
        ]));
        $saved->assertCreated();

        $log = SupervisorFieldLog::query()
            ->where('supervisor_shift_review_id', $saved->json('review.id'))
            ->where('module', 'recommendations')
            ->first();
        $this->assertNotNull($log);
        $this->assertCount(2, $log->payload['items'] ?? []);
        $this->assertNotEmpty($log->payload['items'][0]['photos']['evidence_1'] ?? null);
        $this->assertSame(2, SupervisorRecommendation::query()->where('client_id', $client->id)->count());
        $this->assertSame('recorded', SupervisorRecommendation::query()->where('client_id', $client->id)->first()?->status->value);
        $this->assertSame('extreme', SupervisorRecommendation::query()->where('risk', 'Tramos oscuros en el costado norte.')->first()?->risk_level->value);
        $this->assertSame('Riesgo físico', SupervisorRecommendation::query()->where('risk', 'Tramos oscuros en el costado norte.')->first()?->risk_type);
    }

    public function test_review_requires_photo_and_gps(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload());

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $payload = $this->supervisorReviewPayload($client);
        unset($payload['guard_photo'], $payload['latitude'], $payload['longitude']);

        $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $payload)
            ->assertUnprocessable();
    }
}
