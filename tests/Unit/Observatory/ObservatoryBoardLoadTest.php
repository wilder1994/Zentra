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

    public function test_new_events_push_the_needle_to_red(): void
    {
        $this->assertSame(100, BuildObservatoryBoardService::loadRate(8, 0, 8));
    }

    public function test_treating_lowers_load_and_closing_lowers_it_more(): void
    {
        $this->assertSame(75, BuildObservatoryBoardService::loadRate(5, 5, 10));
        $this->assertSame(45, BuildObservatoryBoardService::loadRate(2, 5, 10));
        $this->assertSame(0, BuildObservatoryBoardService::loadRate(0, 0, 10));
    }
}
