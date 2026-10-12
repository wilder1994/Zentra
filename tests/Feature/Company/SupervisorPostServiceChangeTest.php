<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\OperationalAlertType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\OperationalAlert;
use App\Models\SupervisorPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupervisorPostServiceChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_is_logged_without_observations_later_changes_require_them(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = $client->installations()->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Apoyo interno',
            'modality' => 8,
            'vista' => 'sitio',
        ])->assertRedirect();

        $post = SupervisorPost::query()
            ->where('client_id', $client->id)
            ->where('name', 'Apoyo interno')
            ->firstOrFail();

        $this->assertDatabaseHas('operational_alerts', [
            'type' => OperationalAlertType::ServiceChange->value,
            'supervisor_post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->from(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->put(route('company.clients.posts.update', [$client, $post]), [
                'installation_id' => $installation->id,
                'name' => 'Apoyo interno',
                'modality' => 24,
                'is_active' => '1',
                'vista' => 'sitio',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(8, $post->fresh()->modality);

        $this->actingAs($user)->put(route('company.clients.posts.update', [$client, $post]), [
            'installation_id' => $installation->id,
            'name' => 'Apoyo interno',
            'modality' => 24,
            'is_active' => '1',
            'vista' => 'sitio',
            'observations' => 'Cliente pide cobertura 24 h.',
        ])->assertRedirect();

        $this->assertSame(24, $post->fresh()->modality);
        $body = OperationalAlert::query()
            ->where('supervisor_post_id', $post->id)
            ->latest('id')
            ->value('body');
        $this->assertIsString($body);
        $this->assertStringContainsString('Modalidad 8 h → 24 h', $body);
        $this->assertStringContainsString('Cliente pide cobertura 24 h', $body);

        $other = Employee::query()
            ->where('security_company_id', $client->security_company_id)
            ->where('is_active', true)
            ->whereDoesntHave('supervisorPosts')
            ->firstOrFail();

        $this->actingAs($user)->put(route('company.clients.posts.update', [$client, $post]), [
            'installation_id' => $installation->id,
            'name' => 'Apoyo interno',
            'modality' => 24,
            'is_active' => '1',
            'employee_ids' => [$other->id],
            'vista' => 'sitio',
        ])->assertRedirect();
        $this->assertTrue($post->fresh()->employees->contains($other));

        $this->actingAs($user)
            ->from(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->put(route('company.clients.posts.update', [$client, $post]), [
                'installation_id' => $installation->id,
                'name' => 'Apoyo interno',
                'modality' => 24,
                'is_active' => '1',
                'employee_ids' => [],
                'vista' => 'sitio',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_archive_then_remove_requires_observations(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = $client->installations()->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Temporal',
            'modality' => 8,
            'vista' => 'sitio',
        ])->assertRedirect();

        $post = SupervisorPost::query()->where('name', 'Temporal')->firstOrFail();

        $this->actingAs($user)
            ->from(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->post(route('company.clients.posts.archive', [$client, $post]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($post->fresh()->is_active);

        $this->actingAs($user)
            ->delete(route('company.clients.posts.destroy', [$client, $post]), [
                'observations' => 'Fin de contrato de apoyo.',
            ])
            ->assertSessionHas('error', 'Archiva el puesto antes de darlo de baja.');

        $this->actingAs($user)
            ->post(route('company.clients.posts.archive', [$client, $post]), [
                'observations' => 'Fin de contrato de apoyo.',
            ])
            ->assertSessionHas('success');

        $this->assertFalse($post->fresh()->is_active);
        $this->assertNotNull($post->fresh()->archived_at);

        $this->actingAs($user)
            ->delete(route('company.clients.posts.destroy', [$client, $post]), [
                'observations' => 'Fin de contrato de apoyo.',
            ])
            ->assertRedirect();

        $this->assertSoftDeleted($post);
    }
}
