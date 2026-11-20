<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Tests\TestCase;

final class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_page_is_public(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('Política de privacidad')
            ->assertSee('soporte.admin@sjp.com.co')
            ->assertSee('+57 310 246 6733');
    }
}
