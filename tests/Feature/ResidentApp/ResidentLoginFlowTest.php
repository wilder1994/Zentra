<?php

declare(strict_types=1);

namespace Tests\Feature\ResidentApp;

use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResidentLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resident_can_login_and_open_home_authorizations_and_correspondence(): void
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
            'username' => 'flujo.web',
            'email' => 'flujo@example.test',
            'password' => 'CLAVE12345',
            'is_active' => true,
            'delivery_channel' => StructureAppUser::CHANNEL_ANDROID,
        ]);

        $login = 'flujo.web@'.$client->login_suffix;

        $this->post(route('resident-app.login.store'), [
            'login' => $login,
            'password' => 'CLAVE12345',
        ])->assertRedirect(route('resident-app.home'));

        $this->get(route('resident-app.home'))
            ->assertOk()
            ->assertSee('Autorizar', false);

        $this->get(route('resident-app.authorizations'))
            ->assertOk();

        $this->get(route('resident-app.correspondence'))
            ->assertOk();

        $this->get(route('resident-app.profile'))
            ->assertOk()
            ->assertSee($login, false);
    }
}
