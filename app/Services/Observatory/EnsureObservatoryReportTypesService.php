<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Client;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Support\Observatory\ObservatoryReportTypeDefaults;
use Illuminate\Support\Str;

final class EnsureObservatoryReportTypesService
{
    public function execute(Client $client): void
    {
        $companyId = $this->companyId($client);
        if ($companyId <= 0) {
            return;
        }
        if (ObservatoryReportType::query()->where('security_company_id', $companyId)->exists()) {
            $this->backfillReports($client);

            return;
        }

        foreach (ObservatoryReportTypeDefaults::rows() as $row) {
            ObservatoryReportType::query()->create([
                'security_company_id' => $companyId,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'impacto' => $row['impacto'],
                'urgencia' => $row['urgencia'],
                'color' => $row['color'],
                'is_active' => true,
                'sort_order' => $row['sort_order'],
            ]);
        }

        $this->backfillReports($client);
    }

    public function resolve(Client $client, string $slug): ?ObservatoryReportType
    {
        $this->execute($client);

        return ObservatoryReportType::query()
            ->where('security_company_id', $client->security_company_id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /** @param  iterable<int>  $clientIds */
    public function optionsByClient(iterable $clientIds): array
    {
        $ids = collect($clientIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $clients = Client::query()->whereIn('id', $ids->all())->get(['id', 'security_company_id']);
        foreach ($clients->unique('security_company_id') as $client) {
            $this->execute($client);
        }

        $byCompany = ObservatoryReportType::query()
            ->whereIn('security_company_id', $clients->pluck('security_company_id')->unique()->all())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('security_company_id');

        $options = [];
        foreach ($clients as $client) {
            $rows = $byCompany->get((int) $client->security_company_id, collect());
            $options[(int) $client->id] = $rows->mapWithKeys(
                fn (ObservatoryReportType $type): array => [$type->slug => $type->name],
            )->all();
        }

        return $options;
    }

    public function nextColor(Client $client): string
    {
        $used = ObservatoryReportType::query()
            ->where('security_company_id', $this->companyId($client))
            ->pluck('color')
            ->map(fn (string $color): string => strtolower($color))
            ->all();

        foreach (ObservatoryReportTypeDefaults::palette() as $color) {
            if (! in_array(strtolower($color), $used, true)) {
                return $color;
            }
        }

        return ObservatoryReportTypeDefaults::palette()[0];
    }

    public function uniqueSlug(Client $client, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'endogeno';
        }

        $slug = $base;
        $i = 2;
        $companyId = $this->companyId($client);
        while (ObservatoryReportType::query()
            ->where('security_company_id', $companyId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function companyId(Client $client): int
    {
        $companyId = (int) $client->security_company_id;
        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Client::query()->whereKey($client->id)->value('security_company_id');
    }

    private function backfillReports(Client $client): void
    {
        $types = ObservatoryReportType::query()
            ->where('security_company_id', $this->companyId($client))
            ->get()
            ->keyBy('slug');

        ObservatoryReport::query()
            ->where('client_id', $client->id)
            ->whereNull('observatory_report_type_id')
            ->orderBy('id')
            ->each(function (ObservatoryReport $report) use ($types): void {
                $type = $types->get((string) $report->kind);
                if ($type instanceof ObservatoryReportType) {
                    $report->forceFill(['observatory_report_type_id' => $type->id])->save();
                }
            });
    }
}
