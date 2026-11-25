<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\RiskModelParameter;

final class PresentObservatoryApiService
{
    /**
     * @return array<string, mixed>
     */
    public function event(ObservatoryEvent $event, bool $withReports = false): array
    {
        $payload = [
            'id' => (int) $event->id,
            'folio' => $event->folio(),
            'status' => $event->status?->value,
            'status_label' => $event->statusLabel(),
            'title' => $event->title,
            'opened_at' => $event->opened_at?->toIso8601String(),
            'closed_at' => $event->closed_at?->toIso8601String(),
            'reports_count' => $event->relationLoaded('reports')
                ? $event->reports->count()
                : (int) ($event->reports_count ?? $event->reports()->count()),
            'installation' => $event->installation ? $this->site($event->installation) : null,
            'client' => $event->client ? [
                'id' => (int) $event->client->id,
                'name' => $event->client->name,
                'slug' => $event->client->slug,
            ] : null,
        ];

        if ($withReports) {
            $payload['reports'] = $event->reports
                ->map(fn (ObservatoryReport $report): array => $this->report($report))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function band(ObservatoryReport $report): string
    {
        $companyId = (int) ($report->client?->security_company_id ?? 0);

        return $companyId > 0
            ? RiskModelParameter::forCompany($companyId)->bandOf($report->typeScore())
            : 'BAJO';
    }

    public function report(ObservatoryReport $report): array
    {
        return [
            'id' => (int) $report->id,
            'kind' => $report->typeSlug(),
            'kind_label' => $report->kindLabel(),
            'score' => $report->typeScore(),
            'band' => $this->band($report),
            'color' => $report->typeColor(),
            'source' => $report->source?->value,
            'source_label' => $report->sourceLabel(),
            'reporter_role' => $report->reporter_role?->value,
            'reporter_role_label' => $report->roleLabel(),
            'body' => $report->body,
            'is_anonymous' => $report->is_anonymous,
            'reporter_name' => $report->is_anonymous ? null : $report->reporter_name,
            'reporter_phone' => $report->is_anonymous ? null : $report->reporter_phone,
            'photo_url' => $report->photoUrl(),
            'photo_urls' => $report->photoUrls(),
            'latitude' => $report->latitude !== null ? (float) $report->latitude : null,
            'longitude' => $report->longitude !== null ? (float) $report->longitude : null,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function site(Installation $site): array
    {
        return [
            'id' => (int) $site->id,
            'name' => $site->name,
            'dane_code' => $site->dane_code,
            'city' => $site->city,
            'latitude' => $site->latitude !== null ? (float) $site->latitude : null,
            'longitude' => $site->longitude !== null ? (float) $site->longitude : null,
        ];
    }
}
