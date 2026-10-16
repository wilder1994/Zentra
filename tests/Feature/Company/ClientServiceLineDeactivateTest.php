<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Installation;
use App\Models\Location;
use App\Models\ObservatoryEvent;
use App\Models\SupervisorPost;
use App\Models\User;
use App\Support\Catalog\ServiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientServiceLineDeactivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_deactivate_observatory_without_mode(): void
    {
        [$user, $client] = $this->pilotClientWithObservatory();

        $response = $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_observatory' => '0',
        ]));

        $response->assertSessionHasErrors('has_observatory');
        $this->assertTrue($client->fresh()->has_observatory);
    }

    public function test_temporary_deactivate_observatory_keeps_events_and_frees_flag(): void
    {
        [$user, $client, $event] = $this->pilotClientWithObservatoryEvent();

        $response = $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_observatory' => '0',
            'line_deactivate_mode_observatory' => ServiceLine::MODE_TEMPORARY,
        ]));

        $response->assertRedirect(route('company.clients.show', $client));
        $client->refresh();
        $this->assertFalse($client->has_observatory);
        $this->assertNull($client->observatory_line_archived_at);
        $this->assertNull($event->fresh()->archived_at);
    }

    public function test_archive_deactivate_observatory_archives_events(): void
    {
        [$user, $client, $event] = $this->pilotClientWithObservatoryEvent();

        $response = $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_observatory' => '0',
            'line_deactivate_mode_observatory' => ServiceLine::MODE_ARCHIVE,
        ]));

        $response->assertRedirect(route('company.clients.show', $client));
        $client->refresh();
        $this->assertFalse($client->has_observatory);
        $this->assertNotNull($client->observatory_line_archived_at);
        $event->refresh();
        $this->assertNotNull($event->archived_at);
        $this->assertTrue($event->archived_by_service_line);
    }

    public function test_archive_deactivate_access_archives_doors(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('security_company_id', $user->security_company_id)->where('has_access', true)->firstOrFail();
        $door = Location::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->first();
        $this->assertNotNull($door);

        $response = $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_access' => '0',
            'line_deactivate_mode_access' => ServiceLine::MODE_ARCHIVE,
        ]));

        $response->assertRedirect(route('company.clients.show', $client));
        $this->assertFalse($client->fresh()->has_access);
        $door->refresh();
        $this->assertFalse($door->is_active);
        $this->assertTrue($door->archived_by_service_line);
    }

    public function test_archive_deactivate_supervision_archives_posts(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()
            ->where('security_company_id', $user->security_company_id)
            ->where('has_supervision', true)
            ->firstOrFail();
        $post = SupervisorPost::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->first();
        if ($post === null) {
            $installation = Installation::query()->withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
            $post = SupervisorPost::query()->create([
                'client_id' => $client->id,
                'installation_id' => $installation->id,
                'name' => 'Puesto prueba',
                'modality' => 8,
                'service_days' => 31,
                'serves_holidays' => false,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_supervision' => '0',
            'line_deactivate_mode_supervision' => ServiceLine::MODE_ARCHIVE,
        ]));

        $response->assertRedirect(route('company.clients.show', $client));
        $this->assertFalse($client->fresh()->has_supervision);
        $post->refresh();
        $this->assertFalse($post->is_active);
        $this->assertTrue($post->archived_by_service_line);
    }

    public function test_public_intake_hidden_when_observatory_off(): void
    {
        [$user, $client] = $this->pilotClientWithObservatory();
        $this->actingAs($user)->put(route('company.clients.update', $client), $this->payload($client, [
            'has_observatory' => '0',
            'line_deactivate_mode_observatory' => ServiceLine::MODE_TEMPORARY,
        ]))->assertRedirect();

        $this->get(route('observatory.public.show', $client->slug))->assertNotFound();
    }

    /** @return array{0: User, 1: Client} */
    private function pilotClientWithObservatory(): array
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('security_company_id', $user->security_company_id)->firstOrFail();
        $client->forceFill(['has_observatory' => true])->save();

        return [$user, $client];
    }

    /** @return array{0: User, 1: Client, 2: ObservatoryEvent} */
    private function pilotClientWithObservatoryEvent(): array
    {
        [$user, $client] = $this->pilotClientWithObservatory();
        $installation = Installation::query()->withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
        $event = ObservatoryEvent::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'status' => 'nuevo',
            'title' => 'Folio prueba',
            'opened_at' => now(),
        ]);

        return [$user, $client, $event];
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(Client $client, array $overrides): array
    {
        return array_merge([
            'party_type' => $client->party_type?->value ?? 'legal_entity',
            'name' => $client->name,
            'legal_name' => $client->legal_name ?: $client->name,
            'document_type' => $client->document_type ?: 'NIT',
            'tax_id' => $client->tax_id,
            'email' => $client->email ?: 'cliente@test.local',
            'phone' => $client->phone,
            'representative_name' => $client->representative_name ?: 'Rep',
            'representative_email' => $client->representative_email ?: 'rep@test.local',
            'structure_type_id' => $client->structure_type_id,
            'address' => $client->address,
            'city' => $client->city,
            'department' => $client->department,
            'is_active' => $client->is_active ? '1' : '0',
            'has_access' => $client->has_access ? '1' : '0',
            'has_supervision' => $client->has_supervision ? '1' : '0',
            'has_observatory' => $client->has_observatory ? '1' : '0',
        ], $overrides);
    }
}
