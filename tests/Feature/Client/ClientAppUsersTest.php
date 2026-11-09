<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\Structure;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Models\User;
use App\Support\Company\CompanyOperateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClientAppUsersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creates_app_user_sends_mail_and_lists_under_new_label(): void
    {
        Mail::fake();
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $member = StructureMember::query()
            ->shareable()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereDoesntHave('appUser')
            ->firstOrFail();
        $member->update(['email' => 'residente@example.test']);

        $session = [
            config('tenancy.session.active_client_key') => $client->id,
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
        ];

        $this->actingAs($admin)->withSession($session)
            ->get(route('client.app-users.index'))
            ->assertOk()
            ->assertSee('Usuarios app');

        $response = $this->actingAs($admin)->withSession($session)
            ->post(route('client.app-users.store'), [
                'member_id' => $member->id,
                'delivery_channel' => StructureAppUser::CHANNEL_ANDROID,
            ]);

        $appUser = StructureAppUser::query()->where('member_id', $member->id)->first();
        $this->assertNotNull($appUser);
        $response->assertRedirect(route('client.app-users.show', $appUser));

        Mail::assertSent(\App\Mail\StructureAppUserCredentialsMail::class);
        $this->assertTrue((bool) $member->fresh()->has_app_access);
        $this->assertDatabaseHas('structure_app_user_events', [
            'structure_app_user_id' => $appUser->id,
            'event' => 'created',
        ]);
    }

    #[Test]
    public function rejects_member_without_email(): void
    {
        Mail::fake();
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $member = StructureMember::query()
            ->shareable()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereDoesntHave('appUser')
            ->firstOrFail();
        $member->update(['email' => null]);

        $session = [
            config('tenancy.session.active_client_key') => $client->id,
        ];

        $this->actingAs($admin)->withSession($session)
            ->from(route('client.app-users.create'))
            ->post(route('client.app-users.store'), [
                'member_id' => $member->id,
                'delivery_channel' => StructureAppUser::CHANNEL_IOS_WEB,
            ])
            ->assertSessionHasErrors('member_id');

        Mail::assertNothingSent();
    }

    #[Test]
    public function client_downloads_page_shows_share_link(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession([config('tenancy.session.active_client_key') => $client->id])
            ->get(route('client.downloads.index'))
            ->assertOk()
            ->assertSee('App de residentes')
            ->assertSee('Copiar enlace');
    }
}
