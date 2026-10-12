<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Support\Supervision\SupervisorFieldSheetIntro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanySupervisionFieldSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_lists_and_prints_review_sheet(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );

        $token = $this->loginCompanySupervisor();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();
        $reviewId = (int) $this->withToken($token)
            ->post('/api/supervision/reviews', $this->supervisorReviewPayload($client, ['has_novelty' => 1]))
            ->assertCreated()
            ->json('review.id');

        $api = $this->withToken($token)->getJson('/api/supervision/sheets');
        $api->assertOk();
        $api->assertJsonPath('sheets.0.kind', 'review');
        $api->assertJsonPath('sheets.0.id', $reviewId);

        $apiPrint = $this->withToken($token)->get('/api/supervision/sheets/review/'.$reviewId);
        $apiPrint->assertOk();
        $apiPrint->assertSee('Acta de revista de supervisión de puesto', false);

        $list = $this->actingAs($admin)->get(route('company.supervision.index', ['tab' => 'sheets']));
        $list->assertOk();
        $list->assertSee('Fichas');
        $list->assertSee('FC-');
        $list->assertSee('Revista');

        $print = $this->actingAs($admin)->get(route('company.supervision.sheets.show', [
            'kind' => 'review',
            'id' => $reviewId,
        ]));
        $print->assertOk();
        $print->assertSee('Acta de revista de supervisión de puesto', false);
        $print->assertSee('Imprimir / Guardar PDF');
        $print->assertSee('Cliente:', false);
        $print->assertSee('Instalación:', false);
        $print->assertSee('Puesto:', false);
        $print->assertSee($client->name);
        $print->assertSee('Decreto 356 de 1994', false);
        $print->assertDontSee('generado desde Zentra');
        $print->assertDontSee('>Zentra<', false);

        $this->assertDatabaseHas('supervisor_shift_reviews', [
            'id' => $reviewId,
            'sheet_intro' => SupervisorFieldSheetIntro::DEFAULT,
        ]);

        $admin->securityCompany->update(['field_sheet_intro' => 'Texto nuevo de empresa.']);
        $again = $this->actingAs($admin)->get(route('company.supervision.sheets.show', [
            'kind' => 'review',
            'id' => $reviewId,
        ]));
        $again->assertSee('Decreto 356 de 1994', false);
        $again->assertDontSee('Texto nuevo de empresa.');
    }
}
