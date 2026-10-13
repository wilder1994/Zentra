<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Models\CommonZone;
use App\Models\CommonZoneBooking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CommonZoneBookingRepository
{
    public const TABS = ['hoy', 'proximas', 'historial'];

    private const WITH = ['zone:id,name,type,installation_id', 'structure:id,name', 'member:id,first_name,last_name', 'user:id,name', 'authorization:id,qr_auth_token'];

    /**
     * @param  list<int>|null  $installationIds
     * @return Collection<int, CommonZone>
     */
    public function zones(int $clientId, ?array $installationIds, bool $onlyActive = false): Collection
    {
        return CommonZone::query()
            ->with('installation:id,name')
            ->where('client_id', $clientId)
            ->when($installationIds !== null, fn ($q) => $q->whereIn('installation_id', $installationIds))
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, CommonZoneBooking> */
    public function forMonth(CommonZone $zone, Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return CommonZoneBooking::query()
            ->with(self::WITH)
            ->where('common_zone_id', $zone->id)
            ->whereNotIn('status', [BookingStatus::Rejected->value, BookingStatus::Cancelled->value])
            ->whereDate('date', '<=', $end)
            ->where(fn ($q) => $q->whereDate('date_until', '>=', $start)
                ->orWhere(fn ($q) => $q->whereNull('date_until')->whereDate('date', '>=', $start)))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return Collection<int, CommonZoneBooking>
     */
    public function pending(int $clientId, ?array $installationIds): Collection
    {
        return $this->scoped($clientId, $installationIds)
            ->with(self::WITH)
            ->where('status', BookingStatus::Pending->value)
            ->whereDate('date', '>=', today()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  list<int>|null  $installationIds
     * @param  array{q?: string|null, zone?: string|int|null}  $filters
     */
    public function paginate(int $clientId, ?array $installationIds, string $tab, array $filters = []): LengthAwarePaginator
    {
        $today = today()->toDateString();
        $query = $this->scoped($clientId, $installationIds)->with(self::WITH);

        match ($tab) {
            'proximas' => $query->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->whereDate('date', '>', $today)
                ->orderBy('date')->orderBy('start_time'),
            'historial' => $query->where(fn ($q) => $q->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Rejected->value, BookingStatus::Cancelled->value])
                ->orWhereRaw('COALESCE(date_until, date) < ?', [$today]))
                ->orderByDesc('date')->orderByDesc('start_time'),
            default => $this->coveringToday($query)
                ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value, BookingStatus::Completed->value])
                ->orderBy('start_time'),
        };

        if (filled($filters['zone'] ?? null)) {
            $query->where('common_zone_id', (int) $filters['zone']);
        }
        if (filled($term = trim((string) ($filters['q'] ?? '')))) {
            $query->where(fn (Builder $q) => $q
                ->whereHas('structure', fn ($s) => $s->where('name', 'like', "%{$term}%"))
                ->orWhereHas('member', fn ($m) => $m->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%"))
                ->orWhereHas('zone', fn ($z) => $z->where('name', 'like', "%{$term}%")));
        }

        return $query->paginate(24)->withQueryString();
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return array<string, int>
     */
    public function counts(int $clientId, ?array $installationIds): array
    {
        $today = today()->toDateString();

        return [
            'hoy' => $this->coveringToday($this->scoped($clientId, $installationIds))
                ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value, BookingStatus::Completed->value])
                ->count(),
            'proximas' => $this->scoped($clientId, $installationIds)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->whereDate('date', '>', $today)
                ->count(),
        ];
    }

    /** @param list<int>|null $installationIds */
    private function scoped(int $clientId, ?array $installationIds): Builder
    {
        return CommonZoneBooking::query()
            ->where('client_id', $clientId)
            ->when($installationIds !== null, fn ($q) => $q->whereIn('installation_id', $installationIds));
    }

    private function coveringToday(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->whereDate('date', '<=', $today)
            ->where(fn ($q) => $q->whereDate('date_until', '>=', $today)
                ->orWhere(fn ($q) => $q->whereNull('date_until')->whereDate('date', $today)));
    }
}
