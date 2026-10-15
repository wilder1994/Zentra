<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\Client;
use App\Models\GuardLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PorteriaRevistaTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $vigilante;

    private Location $door;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWithPilot();
        config(['access.geo.required' => true, 'access.shifts.enforced' => false]);

        $this->client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $this->vigilante = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $this->door = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function writeLine(array $data)
    {
        return $this->actingAs($this->vigilante)
            ->withSession($this->porteriaSession($this->client, (int) $this->door->id))
            ->from(route('access.guard_logs.index'))
            ->post(route('access.guard_logs.store'), $data + ['location_id' => $this->door->id]);
    }

    public function test_book_shows_header_and_entries_of_the_day(): void
    {
        $this->writeLine(['type' => 'general', 'description' => 'Recibo el puesto sin novedad.']);

        $this->actingAs($this->vigilante)
            ->withSession($this->porteriaSession($this->client, (int) $this->door->id))
            ->get(route('access.guard_logs.index'))
            ->assertOk()
            ->assertSee('Minuta de servicio')
            ->assertSee($this->client->name)
            ->assertSee($this->door->name)
            ->assertSee('Recibo el puesto sin novedad.')
            ->assertSee('Revista del supervisor');
    }

    public function test_annotation_saves_without_gps_signature_or_supervisor_code(): void
    {
        $this->travelTo(now()->setTime(21, 30));

        $this->writeLine(['type' => 'novedad', 'description' => 'Luz del parqueadero apagada.'])
            ->assertRedirect(route('access.guard_logs.index', ['door' => $this->door->id]))
            ->assertSessionHasNoErrors();

        $log = GuardLog::query()->firstOrFail();
        $this->assertSame('novedad', $log->type);
        $this->assertSame('nocturno', $log->shift_type);
        $this->assertSame('21:30', $log->log_time->format('H:i'));
        $this->assertNotNull($log->signed_at);
        $this->assertNull($log->latitude);
    }

    public function test_time_is_set_by_server_not_by_client(): void
    {
        $this->writeLine(['type' => 'general', 'description' => 'Ronda.', 'log_time' => '2020-01-01 03:00:00']);

        $this->assertTrue(GuardLog::query()->firstOrFail()->log_time->isToday());
    }

    public function test_revista_accepts_company_supervisor_code(): void
    {
        $supervisor = $this->companySupervisor();

        $this->writeLine([
            'type' => 'revista',
            'description' => 'Revista de puesto. Sin novedad.',
            'supervision_code' => $supervisor->supervisor_code,
            'latitude' => 3.4516,
            'longitude' => -76.5320,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guard_logs', [
            'type' => 'revista',
            'supervisor_name' => $supervisor->name,
            'location_id' => $this->door->id,
        ]);
    }

    public function test_revista_requires_a_valid_code(): void
    {
        $this->writeLine(['type' => 'revista', 'description' => 'Revista.'])
            ->assertSessionHasErrors('supervision_code');

        $this->writeLine(['type' => 'revista', 'description' => 'Revista.', 'supervision_code' => '000000'])
            ->assertSessionHasErrors('supervision_code');

        $this->assertSame(0, GuardLog::query()->count());
    }

    public function test_correction_is_a_new_line_and_nothing_is_deleted(): void
    {
        $this->writeLine(['type' => 'general', 'description' => 'Ingresa placa ABC123.']);
        $original = GuardLog::query()->firstOrFail();

        $this->writeLine([
            'type' => 'correccion',
            'description' => 'La placa correcta es ABC128.',
            'corrects_guard_log_id' => $original->id,
        ])->assertSessionHasNoErrors();

        $fix = GuardLog::query()->where('type', 'correccion')->firstOrFail();
        $this->assertSame($original->id, (int) $fix->corrects_guard_log_id);
        $this->assertSame(2, GuardLog::query()->count());

        $this->writeLine(['type' => 'correccion', 'description' => 'Sin original.'])
            ->assertSessionHasErrors('corrects_guard_log_id');

        $this->assertFalse(Route::has('access.guard_logs.destroy'));
    }
}
