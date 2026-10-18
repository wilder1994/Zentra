<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Models\SupervisorPost;
use App\Models\SupervisorShiftReview;
use App\Support\Supervision\SupervisionOperationalDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cumplimiento de revistas por puesto: cada puesto activo espera `reviews_per_day` revistas
 * (por defecto 1, a cualquier hora) en cada día operativo (06:00–06:00) en que presta servicio.
 * Las cifras expected/done de range() son revistas; las de más en un día no suman.
 */
final class BuildPostComplianceService
{
    /**
     * @return array{
     *     expected: int,
     *     done: int,
     *     percent: ?float,
     *     days: list<array{date: string, expected: int, done: int}>,
     *     posts: array<int, array{post: SupervisorPost, expected: int, done: int}>,
     *     clients: array<int, array{id: int, name: string, expected: int, done: int}>
     * }
     */
    public function range(
        int $companyId,
        CarbonInterface $fromDate,
        CarbonInterface $toDate,
        ?SupervisionQueryFilter $filter = null,
    ): array {
        $dates = SupervisionOperationalDay::datesBetween($fromDate, $toDate);
        $today = SupervisionOperationalDay::dateFor();
        $dates = array_values(array_filter($dates, fn (CarbonImmutable $d) => $d->lte($today)));

        $posts = $this->posts($companyId);
        $reviewed = $this->reviewedPostDays($posts, $dates, $filter);

        $expected = 0;
        $done = 0;
        $days = [];
        $perPost = [];
        $perClient = [];
        foreach ($dates as $date) {
            [$start, $end] = SupervisionOperationalDay::window($date);
            $key = $date->format('Y-m-d');
            $dayExpected = 0;
            $dayDone = 0;
            foreach ($posts as $post) {
                if (! $this->expectedOn($post, $date, $start, $end)) {
                    continue;
                }
                $target = $post->reviewsPerDay();
                $hit = min($target, $reviewed[$post->id][$key] ?? 0);
                $dayExpected += $target;
                $dayDone += $hit;

                $perPost[$post->id] ??= ['post' => $post, 'expected' => 0, 'done' => 0];
                $perPost[$post->id]['expected'] += $target;
                $perPost[$post->id]['done'] += $hit;

                $clientId = (int) $post->client_id;
                $perClient[$clientId] ??= ['id' => $clientId, 'name' => (string) $post->client?->name, 'expected' => 0, 'done' => 0];
                $perClient[$clientId]['expected'] += $target;
                $perClient[$clientId]['done'] += $hit;
            }
            $days[] = ['date' => $key, 'expected' => $dayExpected, 'done' => $dayDone];
            $expected += $dayExpected;
            $done += $dayDone;
        }

        return [
            'expected' => $expected,
            'done' => $done,
            'percent' => $expected > 0 ? round(($done / $expected) * 100, 1) : null,
            'days' => $days,
            'posts' => $perPost,
            'clients' => $perClient,
        ];
    }

    /**
     * Estado de cada puesto en un día operativo. done = hizo todas las revistas contratadas.
     *
     * @return list<array{post: SupervisorPost, expected: bool, done: bool, target: int, reviews: int}>
     */
    public function day(int $companyId, ?CarbonInterface $date = null): array
    {
        $date = CarbonImmutable::instance($date ?? SupervisionOperationalDay::dateFor())->startOfDay();
        [$start, $end] = SupervisionOperationalDay::window($date);
        $posts = $this->posts($companyId);
        $reviewed = $this->reviewedPostDays($posts, [$date]);
        $key = $date->format('Y-m-d');

        return $posts
            ->map(function (SupervisorPost $post) use ($reviewed, $key, $date, $start, $end): array {
                $target = $post->reviewsPerDay();
                $reviews = (int) ($reviewed[$post->id][$key] ?? 0);

                return [
                    'post' => $post,
                    'expected' => $this->expectedOn($post, $date, $start, $end),
                    'done' => $reviews >= $target,
                    'target' => $target,
                    'reviews' => $reviews,
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, SupervisorPost> */
    private function posts(int $companyId): Collection
    {
        return SupervisorPost::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('is_active', true)
            ->whereHas('client', fn ($q) => $q->withoutGlobalScopes()
                ->where('security_company_id', $companyId)
                ->where('has_supervision', true)
                ->where('is_active', true))
            ->with([
                'client' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'name', 'latitude', 'longitude', 'address', 'city']),
                'installation' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->orderBy('client_id')
            ->orderBy('installation_id')
            ->orderBy('name')
            ->get();
    }

    private function expectedOn(SupervisorPost $post, CarbonImmutable $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if ($post->created_at !== null && $post->created_at->gte($end)) {
            return false;
        }
        if ($post->deleted_at !== null && $post->deleted_at->lte($start)) {
            return false;
        }

        return $post->expectsReviewOn($date);
    }

    /**
     * @param  Collection<int, SupervisorPost>  $posts
     * @param  list<CarbonImmutable>  $dates
     * @return array<int, array<string, int>> post_id => [Y-m-d => revistas]
     */
    private function reviewedPostDays(Collection $posts, array $dates, ?SupervisionQueryFilter $filter = null): array
    {
        if ($posts->isEmpty() || $dates === []) {
            return [];
        }

        [$from] = SupervisionOperationalDay::window($dates[0]);
        [, $to] = SupervisionOperationalDay::window($dates[count($dates) - 1]);

        $out = [];
        SupervisorShiftReview::query()
            ->whereIn('supervisor_post_id', $posts->pluck('id')->all())
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<', $to)
            ->when($filter !== null, fn ($q) => $q->whereHas('shift', fn ($s) => $s->matchingFilter($filter)))
            ->get(['supervisor_post_id', 'recorded_at'])
            ->each(function (SupervisorShiftReview $review) use (&$out): void {
                $key = SupervisionOperationalDay::dateFor($review->recorded_at)->format('Y-m-d');
                $postId = (int) $review->supervisor_post_id;
                $out[$postId][$key] = ($out[$postId][$key] ?? 0) + 1;
            });

        return $out;
    }
}
