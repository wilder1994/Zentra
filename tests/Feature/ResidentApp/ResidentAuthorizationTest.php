<?php

declare(strict_types=1);

namespace Tests\Feature\ResidentApp;

use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Models\VisitorPreAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResidentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resident_can_create_authorization_for_own_unit(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $member = StructureMember::query()
            ->shareable()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereDoesntHave('appUser')
            ->firstOrFail();

        $appUser = StructureAppUser::query()->create([
            'client_id' => $client->id,
            'member_id' => $member->id,
            'username' => 'residente.prueba',
            'email' => 'residente@example.test',
            'password' => Hash::make('CLAVE12345'),
            'is_active' => true,
            'delivery_channel' => StructureAppUser::CHANNEL_ANDROID,
        ]);
        $member->update(['has_app_access' => true]);

        $this->actingAs($appUser, 'resident')
            ->post(route('resident-app.authorizations.store'), [
                'subject' => 'person',
                'type' => 'visit',
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->toDateString(),
                'guest_document_type' => 'CC',
                'guest_document_number' => '1234567890',
                'guest_first_name' => 'Visitante',
                'guest_last_name' => 'Prueba',
            ])
            ->assertRedirect(route('resident-app.authorizations'));

        $this->assertDatabaseHas('visitor_pre_authorizations', [
            'client_id' => $client->id,
            'structure_id' => $member->structure_id,
            'member_id' => $member->id,
            'origin' => 'resident_app',
            'visitor_document' => '1234567890',
        ]);

        $auth = VisitorPreAuthorization::query()
            ->where('visitor_document', '1234567890')
            ->first();
        $this->assertNotNull($auth);
        $this->assertSame('active', $auth->state());
    }

    #[Test]
    public function resident_can_create_vehicle_authorization_with_full_vehicle_data(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $member = StructureMember::query()
            ->shareable()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereDoesntHave('appUser')
            ->firstOrFail();

        $appUser = StructureAppUser::query()->create([
            'client_id' => $client->id,
            'member_id' => $member->id,
            'username' => 'residente.auto',
            'email' => 'auto@example.test',
            'password' => Hash::make('CLAVE12345'),
            'is_active' => true,
            'delivery_channel' => StructureAppUser::CHANNEL_ANDROID,
        ]);

        $this->actingAs($appUser, 'resident')
            ->post(route('resident-app.authorizations.store'), [
                'subject' => 'vehicle',
                'type' => 'visit',
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->toDateString(),
                'guest_document_type' => 'CC',
                'guest_document_number' => '1099001122',
                'guest_first_name' => 'Conductor',
                'guest_last_name' => 'Prueba',
                'vehicle_plate' => 'abc123',
                'vehicle_brand' => 'Mazda',
                'vehicle_model' => '3',
                'vehicle_color' => 'Rojo',
                'vehicle_type' => 'carro',
            ])
            ->assertRedirect(route('resident-app.authorizations'));

        $this->assertDatabaseHas('visitor_pre_authorizations', [
            'client_id' => $client->id,
            'vehicle_plate' => 'ABC123',
            'origin' => 'resident_app',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'client_id' => $client->id,
            'plate' => 'ABC123',
            'brand' => 'Mazda',
            'model' => '3',
            'color' => 'Rojo',
            'type' => 'carro',
            'is_visitor_vehicle' => 1,
        ]);
    }

    #[Test]
    public function resident_api_login_returns_token(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $member = StructureMember::query()
            ->shareable()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereDoesntHave('appUser')
            ->firstOrFail();

        StructureAppUser::query()->create([
            'client_id' => $client->id,
            'member_id' => $member->id,
            'username' => 'api.user',
            'email' => 'api@example.test',
            'password' => Hash::make('CLAVE12345'),
            'is_active' => true,
            'delivery_channel' => StructureAppUser::CHANNEL_ANDROID,
        ]);

        $this->postJson('/api/resident/login', [
            'login' => 'api.user@'.$client->login_suffix,
            'password' => 'CLAVE12345',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['login', 'name']]);
    }
}
