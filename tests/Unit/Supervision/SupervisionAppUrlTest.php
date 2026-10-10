<?php

declare(strict_types=1);

namespace Tests\Unit\Supervision;

use App\Support\Supervision\SupervisionAppUrl;
use Tests\TestCase;

final class SupervisionAppUrlTest extends TestCase
{
    public function test_uses_configured_pwa_url(): void
    {
        config(['supervision.pwa_url' => 'http://zentra_supervision.test/']);

        $this->assertSame('http://zentra_supervision.test', SupervisionAppUrl::pwa());
    }

    public function test_derives_pwa_host_from_zentra_app_url(): void
    {
        config(['supervision.pwa_url' => '', 'app.url' => 'http://zentra.test']);

        $this->assertSame('http://zentra_supervision.test', SupervisionAppUrl::pwa());
    }

    public function test_production_app_url_uses_campo_path(): void
    {
        config(['supervision.pwa_url' => '', 'app.url' => 'https://zentra.wcodex.cloud']);

        $this->assertSame('https://zentra.wcodex.cloud/campo/', SupervisionAppUrl::pwa());
    }
}
