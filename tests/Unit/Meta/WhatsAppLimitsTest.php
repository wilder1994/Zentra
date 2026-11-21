<?php

declare(strict_types=1);

namespace Tests\Unit\Meta;

use App\Support\Meta\WhatsAppLimits;
use PHPUnit\Framework\TestCase;

final class WhatsAppLimitsTest extends TestCase
{
    public function test_split_cuts_between_words(): void
    {
        [$title, $rest] = WhatsAppLimits::split('AGUSTIN NIETO CABALLERO SEDE ANTONIO JOSE RICAUTE', 24);

        $this->assertSame('AGUSTIN NIETO CABALLERO', $title);
        $this->assertSame('SEDE ANTONIO JOSE RICAUTE', $rest);
        $this->assertLessThanOrEqual(24, mb_strlen($title));
    }

    public function test_split_keeps_short_names_whole(): void
    {
        $this->assertSame(['REPUBLICA DEL PERU', ''], WhatsAppLimits::split('REPUBLICA DEL PERU', 24));
    }

    public function test_split_hard_cuts_a_name_without_spaces(): void
    {
        [$title, $rest] = WhatsAppLimits::split(str_repeat('A', 40), 24);

        $this->assertSame(24, mb_strlen($title));
        $this->assertSame(16, mb_strlen($rest));
    }

    public function test_clip_adds_an_ellipsis_within_the_limit(): void
    {
        $clipped = WhatsAppLimits::clip(str_repeat('ab ', 20), 20);

        $this->assertSame(20, mb_strlen($clipped));
        $this->assertStringEndsWith('…', $clipped);
    }
}
