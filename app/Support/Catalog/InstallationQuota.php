<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\ClientLifecycle;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Scopes\ClientScope;
use App\Models\SecurityCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Bolsa de instalaciones de la empresa. Cada instalación activa de un cliente activo
 * ocupa un cupo de la bolsa y uno de cada línea que tenga el cliente (Accesos, Supervisión, Observatorio).
 */
final class InstallationQuota
{
    public const POOL = 'pool';

    public const ACCESS = 'access';

    public const SUPERVISION = 'supervision';

    public const OBSERVATORY = 'observatory';

    private const COLUMNS = [
        self::ACCESS => 'has_access',
        self::SUPERVISION => 'has_supervision',
        self::OBSERVATORY => 'has_observatory',
    ];

    private const LABELS = [
        self::POOL => 'instalaciones',
        self::ACCESS => 'Accesos',
        self::SUPERVISION => 'Supervisión',
        self::OBSERVATORY => 'Observatorio',
    ];

    public function __construct(private readonly SecurityCompany $company) {}

    public static function for(SecurityCompany $company): self
    {
        return new self($company);
    }

    public function capacity(string $line = self::POOL): int
    {
        $entitlements = CompanyEntitlements::for($this->company);

        return match ($line) {
            self::ACCESS => $entitlements->accessSeats(),
            self::SUPERVISION => $this->company->hasUnlimitedSupervision() ? PHP_INT_MAX : $entitlements->supervisionSeats(),
            self::OBSERVATORY => $entitlements->observatorySeats(),
            default => $entitlements->installationCapacity(),
        };
    }

    public function used(string $line = self::POOL, ?int $exceptInstallationId = null, ?int $exceptClientId = null): int
    {
        return $this->query($line)
            ->when($exceptInstallationId !== null, fn (Builder $q) => $q->whereKeyNot($exceptInstallationId))
            ->when($exceptClientId !== null, fn (Builder $q) => $q->where('client_id', '!=', $exceptClientId))
            ->count();
    }

    public function remaining(string $line = self::POOL): int
    {
        $capacity = $this->capacity($line);
        if ($capacity === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return max(0, $capacity - $this->used($line));
    }

    /** "Cliente A 6, Cliente B 4" de las instalaciones que ocupan cupo. */
    public function breakdown(string $line = self::POOL, int $limit = 6): string
    {
        $rows = $this->query($line)
            ->selectRaw('client_id, COUNT(*) as total')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->get();

        $names = Client::query()->withoutGlobalScopes()->whereIn('id', $rows->pluck('client_id'))->pluck('name', 'id');

        $parts = $rows->take($limit)->map(fn ($row) => ($names[$row->client_id] ?? 'Cliente').' '.$row->total)->all();
        if ($rows->count() > $limit) {
            $parts[] = 'otros '.$rows->skip($limit)->sum('total');
        }

        return implode(', ', $parts);
    }

    /**
     * Valida que caben $count instalaciones más en la bolsa y en cada línea indicada.
     *
     * @param  list<string>  $lines
     */
    public function assertRoom(array $lines, int $count, string $action, ?int $exceptInstallationId = null, ?int $exceptClientId = null): void
    {
        foreach ([self::POOL, ...$lines] as $line) {
            $capacity = $this->capacity($line);
            if ($capacity === PHP_INT_MAX) {
                continue;
            }

            $used = $this->used($line, $exceptInstallationId, $exceptClientId);
            if ($used + $count <= $capacity) {
                continue;
            }

            throw ValidationException::withMessages([
                $line === self::POOL ? 'package' : self::COLUMNS[$line] => $this->fullMessage($line, $action, $used, $capacity),
            ]);
        }
    }

    /** @return list<string> */
    public static function linesOf(Client $client): array
    {
        $lines = [];
        foreach (self::COLUMNS as $line => $column) {
            if ((bool) $client->{$column}) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    public static function label(string $line): string
    {
        return self::LABELS[$line] ?? $line;
    }

    private function fullMessage(string $line, string $action, int $used, int $capacity): string
    {
        $what = $line === self::POOL
            ? 'Tu plan es de '.$capacity.' instalaciones y ya usas '.$used
            : 'Tu plan de '.self::label($line).' cubre '.$capacity.' instalaciones y ya usas '.$used;

        $breakdown = $this->breakdown($line);
        $detail = $breakdown !== '' ? ': '.$breakdown.'.' : '.';

        $how = $line === self::POOL
            ? ' Archiva una instalación o un cliente para liberar cupo, o amplía el plan en Facturación.'
            : ' Quita la línea a otro cliente, archiva una instalación o amplía '.self::label($line).' en Facturación.';

        return $action.' '.$what.$detail.$how;
    }

    private function query(string $line): Builder
    {
        return Installation::query()
            ->withoutGlobalScope(ClientScope::class)
            ->where('is_active', true)
            ->whereHas('client', function (Builder $q) use ($line): void {
                $q->where('security_company_id', $this->company->id)
                    ->where('is_active', true)
                    ->where('lifecycle', ClientLifecycle::Active);
                if (isset(self::COLUMNS[$line])) {
                    $q->where(self::COLUMNS[$line], true);
                }
            });
    }
}
