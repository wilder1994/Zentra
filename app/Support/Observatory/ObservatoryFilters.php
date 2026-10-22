<?php

declare(strict_types=1);

namespace App\Support\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\Installation;
use App\Models\ObservatoryReportType;
use App\Support\Geo\CaliComunaLayer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Filtros del Observatorio (tablero, eventos, en vivo y PPTX), iguales en panel empresa y cliente.
 * Sin periodo se usa el año en curso; la gráfica agrupa por día hasta 62 días y por mes después.
 */
final readonly class ObservatoryFilters
{
    private const DAILY_MAX_DAYS = 62;

    /**
     * @param  list<int>|null  $typeIds  null = todos los tipos
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $status,
        public string $type,
        public ?array $typeIds,
        public ?int $clientId,
        public ?int $installationId,
        public string $comuna,
        public string $search,
        public string $vista,
    ) {}

    /**
     * @param  int|null  $companyId  panel empresa: valida cliente e instalación de la empresa
     * @param  int|null  $tenantClientId  panel cliente: cliente fijo
     * @param  list<int>|null  $allowedInstallationIds  instalaciones permitidas al usuario (null = todas)
     */
    public static function fromRequest(Request $request, ?int $companyId, ?int $tenantClientId, ?array $allowedInstallationIds = null): self
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::enum(ObservatoryEventStatus::class)],
            'type' => ['nullable', 'string', 'max:80'],
            'vista' => ['nullable', 'string', Rule::in(['tablero', 'eventos'])],
            'comuna' => ['nullable', 'string', 'max:12'],
            'client_id' => ['nullable', 'integer'],
            'installation_id' => ['nullable', 'integer'],
        ]);

        $clientId = $tenantClientId;
        if ($tenantClientId === null && ! empty($validated['client_id'])) {
            $clientId = \App\Models\Client::query()
                ->whereKey((int) $validated['client_id'])
                ->where('security_company_id', $companyId)
                ->value('id');
            $clientId = $clientId !== null ? (int) $clientId : null;
        }

        $installationId = null;
        if (! empty($validated['installation_id'])) {
            $site = Installation::query()
                ->withoutGlobalScopes()
                ->whereKey((int) $validated['installation_id'])
                ->when($tenantClientId !== null, fn ($q) => $q->where('client_id', $tenantClientId))
                ->when($tenantClientId === null && $companyId !== null, fn ($q) => $q->whereHas(
                    'client',
                    fn ($c) => $c->where('security_company_id', $companyId),
                ))
                ->first(['id', 'client_id']);
            if ($site !== null && ($allowedInstallationIds === null || in_array((int) $site->id, $allowedInstallationIds, true))) {
                $installationId = (int) $site->id;
                $clientId ??= (int) $site->client_id;
            }
        }

        [$from, $to] = self::period($validated['from'] ?? null, $validated['to'] ?? null);
        $type = trim((string) ($validated['type'] ?? ''));
        $typeIds = null;
        if ($type !== '') {
            $match = collect(ObservatoryReportType::catalogForScope($companyId, $clientId, false))->firstWhere('slug', $type);
            $typeIds = $match !== null ? array_map('intval', $match['ids']) : [];
        }

        return new self(
            from: $from,
            to: $to,
            status: (string) ($validated['status'] ?? ''),
            type: $type,
            typeIds: $typeIds,
            clientId: $clientId,
            installationId: $installationId,
            comuna: app(CaliComunaLayer::class)->normalize($validated['comuna'] ?? null),
            search: trim((string) ($validated['q'] ?? '')),
            vista: (string) ($validated['vista'] ?? 'tablero'),
        );
    }

    /** @return array{0: string, 1: string} */
    public static function period(?string $from, ?string $to): array
    {
        $today = CarbonImmutable::today();
        $start = $from !== null && $from !== '' ? CarbonImmutable::parse($from) : $today->startOfYear();
        $end = $to !== null && $to !== '' ? CarbonImmutable::parse($to) : $today;
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    /** «day» hasta 62 días; «year» = barras por mes. */
    public function grain(): string
    {
        $days = CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) + 1;

        return $days <= self::DAILY_MAX_DAYS ? 'day' : 'year';
    }

    /**
     * Instalaciones del alcance: comuna ∩ instalación elegida ∩ permitidas.
     *
     * @param  list<int>|null  $allowedInstallationIds
     * @return list<int>|null
     */
    public function siteIds(?int $companyId, ?array $allowedInstallationIds): ?array
    {
        $ids = app(CaliComunaLayer::class)->scopeInstallationIds($this->comuna, $companyId, $this->clientId, $allowedInstallationIds);
        if ($this->installationId === null) {
            return $ids;
        }

        return $ids === null || in_array($this->installationId, $ids, true) ? [$this->installationId] : [];
    }

    /** Texto o folio («EV-000024», «24») sobre la lista de eventos. */
    public function applySearch(Builder $query, bool $withClient): Builder
    {
        if ($this->search === '') {
            return $query;
        }
        $term = $this->search;
        $folio = preg_match('/^\s*(?:ev[-\s]?)?0*(\d{1,9})\s*$/i', $term, $m) === 1 ? (int) $m[1] : null;

        return $query->where(function (Builder $inner) use ($term, $folio, $withClient): void {
            $inner->where('title', 'like', '%'.$term.'%')
                ->orWhereHas('reports', fn (Builder $r) => $r->where('body', 'like', '%'.$term.'%'))
                ->orWhereHas('installation', fn (Builder $i) => $i->where('name', 'like', '%'.$term.'%')
                    ->orWhere('dane_code', 'like', '%'.$term.'%'));
            if ($withClient) {
                $inner->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', '%'.$term.'%'));
            }
            if ($folio !== null) {
                $inner->orWhere('id', $folio);
            }
        });
    }

    /**
     * Filtros activos en texto para el PPTX (el alcance y el periodo van aparte).
     *
     * @param  list<array{slug: string, name: string}>  $types
     * @return list<string>
     */
    public function labels(array $types): array
    {
        $labels = [];
        if ($this->status !== '') {
            $labels[] = 'Estado: '.(ObservatoryEventStatus::tryFrom($this->status)?->label() ?? $this->status);
        }
        if ($this->type !== '') {
            $labels[] = 'Tipo: '.(collect($types)->firstWhere('slug', $this->type)['name'] ?? $this->type);
        }
        if ($this->comuna !== '') {
            $labels[] = 'Comuna: '.app(CaliComunaLayer::class)->label($this->comuna);
        }
        if ($this->search !== '') {
            $labels[] = 'Texto: '.$this->search;
        }

        return $labels;
    }

    /**
     * Tipos que el PPTX muestra: todos los del alcance o solo el filtrado.
     *
     * @param  list<array{slug: string}>  $types
     * @return list<array{slug: string, name: string, level: int, color: string}>
     */
    public function typesForExport(array $types): array
    {
        return array_values(array_filter($types, fn (array $type): bool => $this->type === '' || $type['slug'] === $this->type));
    }

    /** @return array<string, string|int> Para enlaces (pestañas, PPTX, en vivo). */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
            'type' => $this->type,
            'client_id' => $this->clientId,
            'installation_id' => $this->installationId,
            'comuna' => $this->comuna,
            'q' => $this->search,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
