<?php

declare(strict_types=1);

namespace Tests\Feature\ResidentApp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResidentLoginPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_page_is_public(): void
    {
        $this->get(route('resident-app.login'))
            ->assertOk()
            ->assertSee('Acceso de residentes', false);
    }

    #[Test]
    public function login_page_works_even_if_web_user_is_logged_in(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->role('super-admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('resident-app.login'))
            ->assertOk()
            ->assertSee('Acceso de residentes', false);
    }
}
