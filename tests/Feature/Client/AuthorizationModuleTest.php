<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\AuthorizationStatus;
use App\Models\AccessLog;
use App\Models\AuthorizationGuest;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Location;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorPreAuthorization;
use App\Services\Access\PorteriaDoorService;
use App\Services\Structure\ManageAuthorizationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AuthorizationModuleTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = 'porteria/personas/test.png';

    public function test_admin_creates_move_with_guests_registered_as_visitors_and_shares_qr(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $member = StructureMember::query()->where('client_id', $client->id)->where('structure_id', $unit->id)->first();
        Visitor::query()->create([
            'client_id' => $client->id, 'document_type' => 'CC', 'document_number' => '1077000001',
            'first_name' => 'Ya', 'last_name' => 'Existe',
        ]);

        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->get(route('client.authorizations.create'))
            ->assertOk()
            ->assertSee('Personas autorizadas')
            ->assertSee('Zona común');

        $response = $this->actingAs($admin)->withSession($this->clientSession($client))
            ->post(route('client.authorizations.store'), [
                'type' => 'move',
                'destination_mode' => 'unit',
                'structure_id' => $unit->id,
                'member_id' => $member?->id,
                'authorized_text' => $member ? null : 'Propietario',
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addDay()->toDateString(),
                'multi_entry' => '1',
                'vehicle_plate' => 'xyz123',
                'guests' => [
                    ['document_type' => 'CC', 'document_number' => '1077000001', 'first_name' => 'Ya', 'last_name' => 'Existe'],
                    ['document_type' => 'CC', 'document_number' => '1077000002', 'first_name' => 'Nuevo', 'last_name' => 'Cargador', 'phone' => '3000000000'],
                    ['document_type' => 'CC', 'document_number' => '', 'first_name' => '', 'last_name' => ''],
                ],
            ]);

        $authorization = VisitorPreAuthorization::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('client.authorizations.show', $authorization));

        $this->assertTrue($authorization->multi_entry);
        $this->assertSame('XYZ123', $authorization->vehicle_plate);
        $this->assertSame((int) $unit->installation_id, (int) $authorization->installation_id);
        $this->assertSame(VisitorPreAuthorization::STATE_ACTIVE, $authorization->state());
        $this->assertCount(2, $authorization->guests);
        $this->assertSame(1, Visitor::query()->where('client_id', $client->id)->where('document_number', '1077000001')->count());
        $this->assertTrue($authorization->guests->every(fn (AuthorizationGuest $g) => $g->visitor_id !== null));
        $this->assertDatabaseHas('visitors', ['client_id' => $client->id, 'document_number' => '1077000002', 'phone' => '3000000000']);

        $passUrl = route('authorizations.pass', $authorization->qr_auth_token);
        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->get(route('client.authorizations.show', $authorization))
            ->assertOk()
            ->assertSee('Compartir por WhatsApp')
            ->assertSee($passUrl, false);

        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->get(route('client.authorizations.index'))
            ->assertOk()
            ->assertSee('Trasteo')
            ->assertSee('Ingresaron 0 de 2');

        auth()->logout();
        $this->get($passUrl)->assertOk()->assertSee('Vigente')->assertSee('Nuevo C.')->assertDontSee('1077000002');
    }

    public function test_installation_admin_cannot_authorize_outside_its_installations(): void
    {
        [, $client] = $this->admin();
        $unit = $this->unit($client);
        $other = Installation::query()->create([
            'client_id' => $client->id, 'name' => 'Otra sede', 'is_active' => true,
        ]);

        $tenant = app(TenantContext::class);
        $tenant->setClientId($client->id);
        $tenant->setInstallationIds([(int) $other->id]);

        try {
            app(ManageAuthorizationService::class)->create(null, [
                'structure_id' => $unit->id,
                'valid_from' => now()->toDateString(),
                'guests' => [['document_number' => '1077000009', 'first_name' => 'Fuera']],
            ]);
            $this->fail('Debió rechazar la unidad fuera de alcance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('structure_id', $e->errors());
        }

        $zone = app(ManageAuthorizationService::class)->create(null, [
            'destination_text' => 'Salón social',
            'valid_from' => now()->toDateString(),
            'guests' => [['document_number' => '1077000010', 'first_name' => 'Invitada']],
        ]);
        $this->assertSame((int) $other->id, (int) $zone->installation_id);
        $this->assertSame('Salón social', $zone->destinationLabel());
    }

    public function test_porteria_enters_with_qr_preloaded_and_single_entry_falls_back_to_normal_flow(): void
    {
        [$guard, $client, $session] = $this->guardOnShift();
        $authorization = $this->authorizationFor($client, '1077000020', multi: false);
        $guest = $authorization->guests()->firstOrFail();
        Visitor::query()->whereKey($guest->visitor_id)->update(['photo_path' => self::PHOTO]);

        $hits = $this->actingAs($guard)->withSession($session)
            ->getJson(route('access.logs.lookup', ['q' => route('authorizations.pass', $authorization->qr_auth_token)]))
            ->assertOk()
            ->assertJsonPath('authorization.state', 'active')
            ->json('hits');
        $this->assertCount(1, $hits);
        $this->assertTrue($hits[0]['auth']['can_enter']);
        $this->assertSame($guest->id, $hits[0]['auth']['guest_id']);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.logs.move'), [
                'kind' => 'visitor',
                'id' => $guest->visitor_id,
                'action' => 'enter',
                'authorization_guest_id' => $guest->id,
                'destination_text' => 'Intento de cambio',
            ])
            ->assertRedirect(route('access.logs.index', ['tab' => 'movimiento']));

        $log = AccessLog::query()->where('visitor_id', $guest->visitor_id)->latest('id')->firstOrFail();
        $this->assertSame((int) $authorization->structure_id, (int) $log->destination_structure_id);
        $this->assertNull($log->destination_text);
        $this->assertSame($guest->id, (int) $log->authorization_guest_id);
        $this->assertSame(1, $guest->fresh()->entries_count);
        $this->assertSame(AuthorizationStatus::Processed, $authorization->fresh()->status);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.logs.move'), ['kind' => 'visitor', 'id' => $guest->visitor_id, 'action' => 'exit'])
            ->assertRedirect();

        $again = $this->actingAs($guard)->withSession($session)
            ->getJson(route('access.logs.lookup', ['q' => '1077000020']))
            ->json('hits.0.auth');
        $this->assertFalse($again['can_enter']);
        $this->assertSame('used', $again['state']);

        $this->actingAs($guard)->withSession($session)
            ->from(route('access.logs.index', ['tab' => 'movimiento']))
            ->post(route('access.logs.move'), [
                'kind' => 'visitor', 'id' => $guest->visitor_id, 'action' => 'enter', 'authorization_guest_id' => $guest->id,
            ])
            ->assertSessionHasErrors('authorization_guest_id');

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.logs.move'), [
                'kind' => 'visitor', 'id' => $guest->visitor_id, 'action' => 'enter',
                'destination_text' => 'Administración', 'authorized_text' => 'Confirmado por teléfono',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, AccessLog::query()->where('visitor_id', $guest->visitor_id)->count());
    }

    public function test_expired_authorization_never_blocks_normal_entry(): void
    {
        [$guard, $client, $session] = $this->guardOnShift();
        $authorization = $this->authorizationFor($client, '1077000030', multi: true);
        $authorization->update(['valid_from' => now()->subDays(3)->startOfDay(), 'valid_until' => now()->subDay()->endOfDay()]);
        $guest = $authorization->guests()->firstOrFail();
        Visitor::query()->whereKey($guest->visitor_id)->update(['photo_path' => self::PHOTO]);

        $auth = $this->actingAs($guard)->withSession($session)
            ->getJson(route('access.logs.lookup', ['q' => '1077000030']))
            ->json('hits.0.auth');
        $this->assertSame('expired', $auth['state']);
        $this->assertFalse($auth['can_enter']);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.logs.move'), [
                'kind' => 'visitor', 'id' => $guest->visitor_id, 'action' => 'enter',
                'destination_structure_id' => $authorization->structure_id, 'authorized_text' => 'Confirmado por citófono',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', AccessLog::query()->where('visitor_id', $guest->visitor_id)->value('status'));
    }

    public function test_cancelled_authorization_hides_qr_on_public_pass(): void
    {
        [$admin, $client] = $this->admin();
        $tenant = app(TenantContext::class);
        $tenant->setClientId($client->id);
        $authorization = $this->authorizationFor($client, '1077000040', multi: false);

        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->patch(route('client.authorizations.cancel', $authorization))
            ->assertRedirect();
        $this->assertSame(VisitorPreAuthorization::STATE_CANCELLED, $authorization->fresh()->state());

        auth()->logout();
        $this->get(route('authorizations.pass', $authorization->qr_auth_token))
            ->assertOk()
            ->assertSee('ya no está vigente')
            ->assertDontSee('data-auth-qr', false);
    }

    /** @return array{0: User, 1: Client} */
    private function admin(): array
    {
        $this->seedWithPilot();

        return [
            User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail(),
            Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail(),
        ];
    }

    /** @return array{0: User, 1: Client, 2: array<string, mixed>} */
    private function guardOnShift(): array
    {
        $this->seedWithPilot();
        config(['access.geo.required' => false, 'access.shifts.enforced' => true]);
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $door = Location::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->firstOrFail();
        $session = $this->porteriaSession($client, (int) $door->id);

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.turnos.store'), ['location_id' => $door->id])
            ->assertRedirect();
        $session[PorteriaDoorService::SESSION_KEY] = $door->id;

        return [$guard, $client, $session];
    }

    private function authorizationFor(Client $client, string $document, bool $multi): VisitorPreAuthorization
    {
        $tenant = app(TenantContext::class);
        $tenant->setClientId($client->id);
        $tenant->setInstallationIds(null);

        return app(ManageAuthorizationService::class)->create(null, [
            'type' => 'visit',
            'structure_id' => $this->unit($client)->id,
            'authorized_text' => 'Residente',
            'valid_from' => now()->toDateString(),
            'multi_entry' => $multi,
            'guests' => [['document_number' => $document, 'first_name' => 'Invitado', 'last_name' => 'Prueba']],
        ]);
    }

    private function unit(Client $client): Structure
    {
        return Structure::query()->where('client_id', $client->id)->whereNotNull('installation_id')->orderBy('id')->firstOrFail();
    }

    /** @return array<string, int> */
    private function clientSession(Client $client): array
    {
        return [config('tenancy.session.active_client_key') => $client->id];
    }
}
