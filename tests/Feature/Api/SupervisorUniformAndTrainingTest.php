<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorTraining;
use App\Models\SupervisorTrainingTopic;
use App\Models\SupervisorUniformItem;
use App\Models\User;
use App\Services\Company\BuildSupervisionSummaryService;
use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Services\Company\ExportSupervisionExecutiveReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class SupervisorUniformAndTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_exposes_new_modules_only_to_app_build_23(): void
    {
        [$token] = $this->setUpSupervisor();

        $old = collect($this->withToken($token)->getJson('/api/supervision/catalog')->json('modules'))->pluck('key');
        $this->assertNotContains('uniform', $old);
        $this->assertNotContains('trainings', $old);

        $new = collect($this->withToken($token)->getJson('/api/supervision/catalog?app_build=23')->json('modules'));
        $keys = $new->pluck('key')->values();
        $this->assertSame($keys->search('reviews') + 1, $keys->search('uniform'));
        $this->assertTrue($new->firstWhere('key', 'uniform')['mandatory']);
        $this->assertSame('checklist', $new->firstWhere('key', 'uniform')['fields'][0]['type']);
        $this->assertSame('topic', collect($new->firstWhere('key', 'trainings')['fields'])->firstWhere('name', 'topic')['type']);
    }

    public function test_review_requires_uniform_and_records_training_once_per_month(): void
    {
        [$token, $client, $items, $topic] = $this->setUpSupervisor();

        $this->withToken($token)->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, ['app_build' => 23]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logs');

        $partial = [['item_id' => $items[0]->id, 'status' => 'no']];
        $this->withToken($token)->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
                'app_build' => 23,
                'logs' => json_encode([['module' => 'uniform', 'payload' => ['items' => $partial, 'novelty' => 'no']]]),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $full = [
            ['item_id' => $items[0]->id, 'status' => 'no'],
            ['item_id' => $items[1]->id, 'status' => 'yes'],
        ];
        $this->withToken($token)->withHeaders(['Accept' => 'application/json'])
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
                'app_build' => 23,
                'logs' => json_encode([['module' => 'uniform', 'payload' => ['items' => $full, 'novelty' => 'no', 'notes' => 'Botas rotas']]]),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('log_photos');

        $training = [
            'module' => 'trainings',
            'payload' => [
                'kind' => 'custom',
                'modality' => 'onsite',
                'topic_id' => $topic->id,
                'reason' => 'Extintor bloqueado',
                'description' => 'Uso del extintor del puesto',
                'started_at' => now()->subMinutes(20)->toIso8601String(),
                'ended_at' => now()->subMinutes(5)->toIso8601String(),
            ],
        ];
        $saved = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'app_build' => 23,
            'logs' => json_encode([
                ['module' => 'uniform', 'payload' => ['items' => $full, 'novelty' => 'no', 'notes' => 'Botas rotas']],
                $training,
            ]),
            'log_photos' => [
                0 => ['uniform_1' => UploadedFile::fake()->image('u.jpg')],
                1 => ['training_1' => UploadedFile::fake()->image('t.jpg')],
            ],
        ]));
        $saved->assertCreated()->assertJsonPath('discarded_trainings', []);

        $uniform = SupervisorFieldLog::query()->where('module', 'uniform')->firstOrFail();
        $this->assertSame('attention', $uniform->outcome->value);
        $this->assertSame('yes', $uniform->payload['novelty']);
        $this->assertTrue(SupervisorShiftReview::query()->findOrFail($uniform->supervisor_shift_review_id)->has_novelty);
        $row = SupervisorTraining::query()->firstOrFail();
        $this->assertSame(15, $row->minutes);
        $this->assertSame(now()->format('Y-m'), $row->period);

        $this->withToken($token)->getJson('/api/supervision/trainings/check?employee_id='.$row->employee_id.'&topic_id='.$topic->id)
            ->assertOk()
            ->assertJsonPath('trained', true);

        $again = $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'app_build' => 23,
            'logs' => json_encode([
                ['module' => 'uniform', 'payload' => ['items' => [
                    ['item_id' => $items[0]->id, 'status' => 'no'],
                    ['item_id' => $items[1]->id, 'status' => 'na'],
                ], 'novelty' => 'no']],
                ['module' => 'trainings', 'payload' => ['kind' => 'monthly', 'modality' => 'virtual', 'topic_name' => '  uso DEL extintor ']],
            ]),
            'log_photos' => [1 => ['training_1' => UploadedFile::fake()->image('t.jpg')]],
        ]));
        $again->assertCreated();
        $this->assertCount(1, $again->json('discarded_trainings'));
        $this->assertSame(1, SupervisorTraining::query()->count());
        $this->assertSame(1, SupervisorTrainingTopic::query()->count());
    }

    public function test_topic_api_reuses_existing_names_and_summary_and_pptx_include_trainings(): void
    {
        [$token, $client, $items, $topic] = $this->setUpSupervisor();

        $this->withToken($token)->postJson('/api/supervision/training-topics', ['name' => 'USO del Extintor'])
            ->assertOk()
            ->assertJsonPath('topic.value', (string) $topic->id)
            ->assertJsonPath('created', false);
        $this->withToken($token)->postJson('/api/supervision/training-topics', ['name' => 'Primeros auxilios'])
            ->assertCreated()
            ->assertJsonPath('created', true);
        $this->assertSame('app', SupervisorTrainingTopic::query()->where('name', 'Primeros auxilios')->value('created_from'));

        $this->withToken($token)->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, [
            'app_build' => 23,
            'logs' => json_encode([
                ['module' => 'uniform', 'payload' => ['items' => [
                    ['item_id' => $items[0]->id, 'status' => 'regular'],
                    ['item_id' => $items[1]->id, 'status' => 'good'],
                ], 'novelty' => 'yes', 'notes' => 'Gorra desteñida']],
                ['module' => 'trainings', 'payload' => ['kind' => 'monthly', 'modality' => 'virtual', 'topic_id' => $topic->id]],
            ]),
            'log_photos' => [
                0 => ['uniform_1' => UploadedFile::fake()->image('u.jpg')],
                1 => ['training_1' => UploadedFile::fake()->image('t.jpg')],
            ],
        ]))->assertCreated();

        $company = SecurityCompany::query()->findOrFail($this->pilotCompanyId());
        $snapshot = app(BuildSupervisionSummaryService::class)->execute($company, new SupervisionQueryFilter(now()->subDay()->toDateString(), now()->toDateString()));
        $this->assertSame(1, $snapshot->charts['trainings']['total']);
        $this->assertSame(1, $snapshot->charts['trainings']['monthly']);
        $this->assertSame(1, $snapshot->charts['trainings']['coverage']['covered']);
        $this->assertSame(1, $snapshot->charts['uniform']['novelty']);
        $this->assertSame('Gorra', $snapshot->charts['uniform']['by_item'][0]['name']);

        $file = app(ExportSupervisionExecutiveReportService::class)->execute($snapshot);
        $this->assertFileExists($file['path']);
        @unlink($file['path']);

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($admin)->get(route('company.supervision-training-topics.index'))->assertOk()->assertSee('Creado en campo');
        $this->actingAs($admin)->get(route('company.supervision-uniform-items.index'))->assertOk()->assertSee('Gorra');
        $this->actingAs($admin)->delete(route('company.supervision-training-topics.destroy', $topic))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /** @return array{0: string, 1: Client, 2: list<SupervisorUniformItem>, 3: SupervisorTrainingTopic} */
    private function setUpSupervisor(): array
    {
        $this->seedWithPilot();
        $companyId = $this->pilotCompanyId();
        $items = [
            SupervisorUniformItem::query()->create(['security_company_id' => $companyId, 'name' => 'Gorra', 'is_active' => true, 'sort_order' => 10]),
            SupervisorUniformItem::query()->create(['security_company_id' => $companyId, 'name' => 'Botas', 'is_active' => true, 'sort_order' => 20]),
        ];
        $topic = SupervisorTrainingTopic::query()->create([
            'security_company_id' => $companyId,
            'name' => 'Uso del extintor',
            'normalized_name' => SupervisorTrainingTopic::normalize('Uso del extintor'),
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        return [$token, $client, $items, $topic];
    }
}
