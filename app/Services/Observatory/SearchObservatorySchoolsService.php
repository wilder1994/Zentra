<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Client;
use App\Models\Installation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Busca colegios de un cliente por nombre, código DANE o cercanía. */
final class SearchObservatorySchoolsService
{
    /** Palabras que casi todos escriben y que no distinguen un colegio de otro. */
    private const FILLER = [
        'colegio', 'institucion', 'institución', 'educativa', 'escuela', 'centro',
        'de', 'del', 'la', 'el', 'los', 'las', 'ie', 'sem', 'em',
    ];

    public function find(Client $client, int $installationId): ?Installation
    {
        /** @var Installation|null $installation */
        $installation = $this->base($client)->whereKey($installationId)->first();

        return $installation;
    }

    /** @return array{schools: Collection<int, Installation>, has_more: bool} */
    public function byText(Client $client, string $text, int $limit = 10): array
    {
        $text = trim($text);
        $query = $this->base($client);

        if (preg_match('/^\d{4,}$/', $text) === 1) {
            $query->where('dane_code', 'like', '%'.$text.'%');
        } else {
            $terms = $this->terms($text);
            if ($terms === []) {
                return ['schools' => collect(), 'has_more' => false];
            }
            foreach ($terms as $term) {
                $query->where('name', 'like', '%'.$term.'%');
            }
        }

        $rows = $query->orderBy('name')->limit($limit + 1)->get();

        return [
            'schools' => $rows->take($limit)->values(),
            'has_more' => $rows->count() > $limit,
        ];
    }

    /**
     * Cada colegio trae `distance_km`.
     *
     * @return Collection<int, Installation>
     */
    public function nearest(Client $client, float $latitude, float $longitude, int $limit = 5): Collection
    {
        $distance = '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))))';

        return $this->base($client)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('installations.*')
            ->selectRaw($distance.' as distance_km', [$latitude, $longitude, $latitude])
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();
    }

    /** @return Builder<Installation> */
    private function base(Client $client): Builder
    {
        return Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->observatoryCommunity();
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 2 && ! in_array($word, self::FILLER, true)) {
                $terms[] = $word;
            }
        }

        return array_slice(array_values(array_unique($terms)), 0, 5);
    }
}
