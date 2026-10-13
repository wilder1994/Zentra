<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Correspondence;
use App\Models\StructureMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_registers_packages_for_census_unit_and_delivers_them(): void
    {
        [$guard, $client, $session, $member] = $this->context();
        $photo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($guard)->withSession($session)
            ->get(route('access.correspondence.create'))
            ->assertOk()
            ->assertSee('Unidad del censo');

        $this->actingAs($guard)->withSession($session)
            ->getJson(route('access.correspondence.members', ['structure_id' => $member->structure_id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $member->id]);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), [
                'structure_id' => $member->structure_id,
                'structure_member_id' => $member->id,
                'package_type' => 'caja',
                'carrier' => 'Servientrega',
                'courier_guide' => 'GUIA-001',
                'quantity' => 2,
                'package_photo_data' => $photo,
            ])
            ->assertRedirect(route('access.correspondence.index'));

        $first = Correspondence::query()->where('courier_guide', 'GUIA-001')->sole();
        $this->assertSame(2, $first->quantity);
        $this->assertNotNull($first->location_id);
        $this->assertNotEmpty($first->photo_path);
        $this->assertSame((int) $client->id, (int) $first->client_id);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), [
                'structure_id' => $member->structure_id,
                'package_type' => 'sobre',
                'courier_guide' => 'GUIA-002',
            ])
            ->assertRedirect();
        $items = Correspondence::query()->whereIn('courier_guide', ['GUIA-001', 'GUIA-002'])->orderBy('id')->get();
        $this->assertCount(2, $items);

        $this->actingAs($guard)->withSession($session)
            ->get(route('access.correspondence.index', ['q' => 'GUIA-001']))
            ->assertOk()
            ->assertSee('2 × Caja')
            ->assertSee('Todo (3)')
            ->assertSee($member->full_name);

        $this->actingAs($guard)->withSession($session)
            ->patch(route('access.correspondence.deliver', $items->first()), [])
            ->assertSessionHasErrors('delivered_to_name');

        $this->actingAs($guard)->withSession($session)
            ->patch(route('access.correspondence.deliver', $items->first()), ['delivered_to_member_id' => $member->id])
            ->assertRedirect();
        $this->assertSame('delivered', $items->first()->fresh()->status);
        $this->assertSame((int) $member->id, (int) $items->first()->fresh()->delivered_to_member_id);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.deliver-unit'), [
                'structure_id' => $member->structure_id,
                'delivered_to_name' => 'Portero vecino',
                'delivered_to_document' => '123',
            ])
            ->assertRedirect();
        $this->assertSame('delivered', $items->last()->fresh()->status);
        $this->assertSame('Portero vecino', $items->last()->fresh()->delivered_to_name);
        $this->assertSame(2, AuditLog::query()->where('action', 'correspondence.deliver')->count());

        $this->actingAs($guard)->withSession($session)
            ->get(route('access.correspondence.show', $items->last()))
            ->assertOk()
            ->assertSee('Portero vecino · 123');
    }

    public function test_free_text_recipient_and_void_requires_admin_permission(): void
    {
        [$guard, , $session] = $this->context();

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), [])
            ->assertSessionHasErrors('package_type');

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), ['package_type' => 'sobre'])
            ->assertSessionHasErrors('structure_id');

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), ['package_type' => 'sobre', 'recipient_text' => 'Administración'])
            ->assertRedirect();
        $item = Correspondence::query()->where('recipient_text', 'Administración')->firstOrFail();
        $this->assertNull($item->structure_id);

        $this->actingAs($guard)->withSession($session)
            ->patch(route('access.correspondence.void', $item), ['void_reason' => 'Duplicado'])
            ->assertForbidden();

        $guard->givePermissionTo('access.view.audit');
        $this->actingAs($guard->fresh())->withSession($session)
            ->patch(route('access.correspondence.void', $item), ['void_reason' => 'Duplicado'])
            ->assertRedirect(route('access.correspondence.index'));
        $this->assertSame('voided', $item->fresh()->status);
        $this->assertSame('Duplicado', $item->fresh()->void_reason);
    }

    public function test_cannot_target_member_of_another_unit(): void
    {
        [$guard, $client, $session, $member] = $this->context();
        $other = StructureMember::query()->where('client_id', $client->id)
            ->where('structure_id', '!=', $member->structure_id)->first();
        if ($other === null) {
            $this->markTestSkipped('El piloto no tiene una segunda unidad con miembros.');
        }

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.correspondence.store'), [
                'structure_id' => $member->structure_id,
                'structure_member_id' => $other->id,
                'package_type' => 'caja',
            ])
            ->assertSessionHasErrors('structure_member_id');
    }

    /** @return array{0: User, 1: Client, 2: array<string, mixed>, 3: StructureMember} */
    private function context(): array
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $member = StructureMember::query()->withoutGlobalScopes()->where('client_id', $client->id)->whereNotNull('structure_id')->where('is_active', true)->firstOrFail();

        return [$guard, $client, $this->porteriaSession($client), $member];
    }
}
