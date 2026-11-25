<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Models\ObservatoryCrimeFigure;
use App\Models\ObservatoryCrimeType;
use App\Models\User;
use App\Support\Geo\CaliComunaLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObservatoryCrimeFigureTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_saves_comuna_figures_and_the_map_reads_them(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $code = app(CaliComunaLayer::class)->codes()[0];

        $this->actingAs($admin)
            ->get(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertOk()
            ->assertSee('← Riesgos')
            ->assertSee('Delitos por comuna')
            ->assertSee('Guardar')
            ->assertDontSee('Tablero')
            ->assertDontSee('Crear delito')
            ->assertSee('Hurtos')
            ->assertSee('Índice ponderado')
            ->assertSee('R comuna normalizado');

        $typeId = (int) ObservatoryCrimeType::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('slug', 'hurtos')
            ->value('id');

        $this->actingAs($admin)
            ->post(route('company.observatory.crimes.figures'), [
                'year' => (int) now()->year,
                'figures' => [$code => [$typeId => 4321]],
            ])
            ->assertRedirect();

        $this->assertSame(4321, (int) ObservatoryCrimeFigure::query()->where('comuna_code', $code)->value('total'));
        $payload = app(\App\Services\Observatory\ManageObservatoryCrimeFiguresService::class)
            ->mapPayload((int) $admin->security_company_id, now()->startOfYear()->toDateString(), now()->toDateString());
        $found = collect($payload['rows'] ?? [])->contains(
            fn (array $row): bool => (int) ($row['counts']['hurtos'] ?? 0) === 4321,
        );
        $this->assertTrue($found, 'La cifra no llegó al mapa. Código '.$code);

        $this->actingAs($admin)
            ->get(route('company.observatory.events.index', ['vista' => 'tablero']))
            ->assertOk()
            ->assertSee('Riesgos')
            ->assertViewHas('map', function (array $map) use ($code): bool {
                $rows = $map['crimes']['rows'] ?? [];

                return collect($rows)->contains(
                    fn (array $row): bool => $row['code'] === $code && (int) ($row['counts']['hurtos'] ?? 0) === 4321,
                );
            });
    }

    public function test_company_creates_a_crime_type_from_the_comuna_grid(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertOk()
            ->assertSee('Guardar')
            ->assertDontSee('Crear delito')
            ->assertDontSee('Nueva comuna');

        $this->actingAs($admin)
            ->post(route('company.observatory.crimes.store'), [
                'name' => 'Extorsión',
                'color' => '#f59e0b',
                'impacto' => 7,
                'probabilidad' => 4,
                'return' => 'comunas',
            ])
            ->assertRedirect(route('company.observatory.types.index', ['seccion' => 'comunas']));

        $type = ObservatoryCrimeType::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('slug', 'extorsion')
            ->first();

        $this->assertNotNull($type);
        $this->assertSame(7, (int) $type->impacto);
        $this->assertSame(4, (int) $type->probabilidad);

        $this->actingAs($admin)
            ->get(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertOk()
            ->assertSee('Extorsión');
    }

    public function test_crime_with_figures_is_archived_instead_of_deleted(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $code = app(CaliComunaLayer::class)->codes()[0];

        $this->actingAs($admin)
            ->get(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertOk()
            ->assertSee('Guardar')
            ->assertDontSee('Eliminar')
            ->assertDontSee('Archivar')
            ->assertDontSee('Nueva comuna');

        $type = ObservatoryCrimeType::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('slug', 'hurtos')
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('company.observatory.crimes.update', $type), [
                'name' => 'Hurtos graves',
                'color' => $type->color,
                'impacto' => 8,
                'probabilidad' => 6,
                'is_active' => 1,
                'return' => 'comunas',
            ])
            ->assertRedirect(route('company.observatory.types.index', ['seccion' => 'comunas']));

        $this->assertSame('Hurtos graves', $type->fresh()->name);

        $this->actingAs($admin)
            ->post(route('company.observatory.crimes.figures'), [
                'year' => (int) now()->year,
                'figures' => [$code => [$type->id => 15]],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('company.observatory.crimes.destroy', $type), ['return' => 'comunas'])
            ->assertRedirect(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertSessionHasErrors('name');

        $this->assertNotNull($type->fresh());

        $this->actingAs($admin)
            ->post(route('company.observatory.crimes.archive', $type), ['return' => 'comunas'])
            ->assertRedirect(route('company.observatory.types.index', ['seccion' => 'comunas']));

        $this->assertFalse((bool) $type->fresh()->is_active);

        $payload = app(\App\Services\Observatory\ManageObservatoryCrimeFiguresService::class)
            ->mapPayload((int) $admin->security_company_id);
        $slugs = collect($payload['types'])->pluck('slug');
        $this->assertFalse($slugs->contains('hurtos-graves'));

        $fresh = ObservatoryCrimeType::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('company.observatory.crimes.destroy', $fresh), ['return' => 'comunas'])
            ->assertRedirect(route('company.observatory.types.index', ['seccion' => 'comunas']))
            ->assertSessionHasNoErrors();

        $this->assertNull($fresh->fresh());
    }
}
