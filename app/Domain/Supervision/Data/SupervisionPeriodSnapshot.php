<?php

declare(strict_types=1);

namespace App\Domain\Supervision\Data;

final readonly class SupervisionPeriodSnapshot
{
    /**
     * @param  list<array{name: string, shifts: int, reviews: int, km: int, logs: int, alarms: int, supports: int}>  $bySupervisor
     * @param  list<array{
     *     name: string,
     *     status: string,
     *     installations: int,
     *     posts: int,
     *     posts_done: int,
     *     expected: int,
     *     done: int,
     *     percent: ?float,
     *     reviews: int,
     *     novelty: int,
     *     pending: list<array{installation: string, post: string, missing: int, expected: int}>
     * }>  $byClient  Ordenado rojo → ámbar → verde y alfabético.
     * @param  array<string, array{label: string, total: int, ok: int, attention: int, critical: int}>  $modules
     * @param  array{total: int, low: int, medium: int, high: int, extreme: int}  $recommendations
     * @param  array<string, mixed>  $charts
     * @param  list<array{level: string, text: string}>  $alerts  Solo lo que pide acción; vacío si no hay nada.
     */
    public function __construct(
        public string $companyName,
        public string $from,
        public string $to,
        public string $caption,
        public int $reviews,
        public int $reviewsWithNovelty,
        public int $sitesContracted,
        public int $sitesVisited,
        public int $postsTotal,
        public int $postsDone,
        public int $kmTraveled,
        public int $openShifts,
        public int $fieldLogs,
        public string $semaphore,
        public ?float $coveragePercent,
        public array $bySupervisor,
        public array $byClient,
        public array $modules,
        public array $recommendations,
        public array $charts,
        public array $alerts,
    ) {}
}
