<?php

declare(strict_types=1);

namespace Tests\Unit\Observatory;

use App\Services\Observatory\BuildObservatoryBoardService;
use PHPUnit\Framework\TestCase;

final class ObservatoryBoardLoadTest extends TestCase
{
    public function test_empty_board_has_no_load(): void
    {
        $this->assertSame(0, BuildObservatoryBoardService::loadRate(0, 0, 0));
    }

    public function test_new_folios_do_not_count_as_managed(): void
    {
        $this->assertSame(0, BuildObservatoryBoardService::loadRate(8, 0, 8));
    }

    public function test_closing_raises_gestion_and_treating_raises_half(): void
    {
        $this->assertSame(25, BuildObservatoryBoardService::loadRate(5, 5, 10));
        $this->assertSame(55, BuildObservatoryBoardService::loadRate(2, 5, 10));
        $this->assertSame(100, BuildObservatoryBoardService::loadRate(0, 0, 10));
    }
}
