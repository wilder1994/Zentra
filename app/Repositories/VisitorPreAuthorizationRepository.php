<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AuthorizationStatus;
use App\Models\VisitorPreAuthorization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class VisitorPreAuthorizationRepository
{
    public const TABS = ['hoy', 'proximas', 'historial'];

    /**
     * @param  list<int>|null  $installationIds  null = todas
     * @param  array{q?: string|null, type?: string|null}  $filters
     */
    public function paginate(int $clientId, ?array $installationIds, string $tab, array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->tab($this->scoped($clientId, $installationIds), $tab)
            ->with(['structure', 'member', 'installation', 'guests'])
            ->withCount(['guests', 'guests as entered_count' => fn (Builder $q) => $q->where('entries_count', '>', 0)]);

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('visitor_name', 'like', "%{$term}%")
                    ->orWhere('destination_text', 'like', "%{$term}%")
                    ->orWhere('vehicle_plate', 'like', "%{$term}%")
                    ->orWhereHas('structure', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('guests', fn (Builder $g) => $g->where('document_number', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"));
            });
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $tab === 'proximas'
            ? $query->orderBy('valid_from')->orderBy('id')->paginate($perPage)->withQueryString()
            : $query->orderByDesc('valid_from')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return array<string, int>
     */
    public function counts(int $clientId, ?array $installationIds): array
    {
        return [
            'hoy' => $this->tab($this->scoped($clientId, $installationIds), 'hoy')->count(),
            'proximas' => $this->tab($this->scoped($clientId, $installationIds), 'proximas')->count(),
        ];
    }

    /** @param list<int>|null $installationIds */
    public function scoped(int $clientId, ?array $installationIds): Builder
    {
        return VisitorPreAuthorization::query()
            ->where('client_id', $clientId)
            ->when($installationIds !== null, fn (Builder $q) => $q->whereIn('installation_id', $installationIds));
    }

    private function tab(Builder $query, string $tab): Builder
    {
        $endOfToday = now()->endOfDay();

        return match ($tab) {
            'proximas' => $query->where('status', '!=', AuthorizationStatus::Cancelled->value)
                ->where('valid_from', '>', $endOfToday),
            'historial' => $query->where(fn (Builder $q) => $q
                ->where('valid_until', '<', now())
                ->orWhereIn('status', [AuthorizationStatus::Cancelled->value, AuthorizationStatus::Processed->value])),
            default => $query->where('status', AuthorizationStatus::Pending->value)
                ->where('valid_from', '<=', $endOfToday)
                ->where('valid_until', '>=', now()),
        };
    }
}
