<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Client;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Support\Observatory\ObservatoryFilters;
use Illuminate\Http\Request;

/** Arma el PPTX del tablero fuera del ciclo que pinta la página. */
final class CompileObservatoryPptx
{
    /**
     * @param  list<int>|null  $allowed
     * @param  array<string, mixed>  $query
     * @return array{path: string, filename: string}
     */
    public function forCompany(User $user, int $companyId, ?array $allowed, array $query): array
    {
        $request = Request::create('/company/observatory/tablero.pptx', 'GET', $query);
        $request->setUserResolver(static fn (): User => $user);
        $filters = ObservatoryFilters::fromRequest($request, $companyId, null, $allowed);
        $siteIds = $filters->siteIds($companyId, $allowed);
        $boardService = app(BuildObservatoryBoardService::class);
        $board = $boardService->execute(
            $companyId,
            $filters->clientId,
            $siteIds,
            $filters->from,
            $filters->to,
            $filters->grain(),
            $filters->status,
            $filters->typeIds,
        );
        $company = SecurityCompany::query()->findOrFail($companyId);
        [$scope, $scopeKind] = match (true) {
            $filters->installationId !== null => [Installation::query()->withoutGlobalScopes()->whereKey($filters->installationId)->value('name'), 'Instalación'],
            $filters->clientId !== null => [Client::query()->whereKey($filters->clientId)->value('name'), 'Cliente'],
            default => [$company->displayName(), 'Empresa'],
        };
        $types = $filters->typesForExport($board['types'] ?? []);
        $events = $filters->applySearch(
            $boardService->scoped($companyId, $filters->clientId, $siteIds, $filters->from, $filters->to, $filters->status, $filters->typeIds),
            true,
        )->orderByDesc('opened_at');
        $data = app(BuildObservatoryExportDataService::class)->execute($events, $types);

        return app(ExportObservatoryBoardService::class)->execute($board, $data, [
            'scope' => (string) ($scope ?: $company->displayName()),
            'scope_kind' => $scopeKind,
            'company' => $company->displayName(),
            'logo_path' => $company->logo_path,
            'filters' => $filters->labels($board['types'] ?? []),
            'from' => $filters->from,
            'to' => $filters->to,
            'grain' => $filters->grain(),
            'risk' => app(ExportObservatoryBoardService::class)->riskRows(
                app(BuildInstallationRiskService::class)->execute($companyId, $filters->clientId, $siteIds),
            ),
            'comunas' => app(BuildInstallationRiskService::class)->comunaBoard($companyId, $filters->comuna),
        ]);
    }

    /**
     * @param  list<int>|null  $allowed
     * @param  array<string, mixed>  $query
     * @return array{path: string, filename: string}
     */
    public function forClient(int $clientId, ?array $allowed, array $query): array
    {
        $request = Request::create('/client/observatory/tablero.pptx', 'GET', $query);
        $filters = ObservatoryFilters::fromRequest($request, null, $clientId, $allowed);
        $siteIds = $filters->siteIds(null, $allowed);
        $client = Client::query()->with('securityCompany')->findOrFail($clientId);
        $boardService = app(BuildObservatoryBoardService::class);
        $board = $boardService->execute(null, $clientId, $siteIds, $filters->from, $filters->to, $filters->grain(), $filters->status, $filters->typeIds);
        $site = $filters->installationId !== null
            ? Installation::query()->whereKey($filters->installationId)->value('name')
            : null;
        $types = $filters->typesForExport($board['types'] ?? []);
        $events = $filters->applySearch(
            $boardService->scoped(null, $clientId, $siteIds, $filters->from, $filters->to, $filters->status, $filters->typeIds),
            false,
        )->orderByDesc('opened_at');
        $data = app(BuildObservatoryExportDataService::class)->execute($events, $types);
        $company = $client->securityCompany;

        return app(ExportObservatoryBoardService::class)->execute($board, $data, [
            'scope' => (string) ($site ?: $client->name),
            'scope_kind' => $site ? 'Instalación' : 'Cliente',
            'company' => $company?->displayName() ?? $client->name,
            'logo_path' => $company?->logo_path,
            'filters' => $filters->labels($board['types'] ?? []),
            'from' => $filters->from,
            'to' => $filters->to,
            'grain' => $filters->grain(),
            'risk' => app(ExportObservatoryBoardService::class)->riskRows(
                app(BuildInstallationRiskService::class)->execute((int) $client->security_company_id, $clientId, $siteIds),
            ),
            'comunas' => app(BuildInstallationRiskService::class)->comunaBoard((int) $client->security_company_id, $filters->comuna),
        ]);
    }
}
