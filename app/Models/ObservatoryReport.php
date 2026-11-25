<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObservatoryReportKind;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Support\Observatory\RiskMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class ObservatoryReport extends Model
{
    protected $fillable = [
        'event_id',
        'client_id',
        'installation_id',
        'source',
        'reporter_role',
        'kind',
        'observatory_report_type_id',
        'body',
        'is_anonymous',
        'is_adult',
        'reporter_name',
        'reporter_phone',
        'reported_by_user_id',
        'photo_path',
        'photo_paths',
        'video_path',
        'latitude',
        'longitude',
        'ip_hash',
        'reporter_key',
    ];

    protected function casts(): array
    {
        return [
            'source' => ObservatoryReportSource::class,
            'reporter_role' => ObservatoryReporterRole::class,
            'is_anonymous' => 'boolean',
            'is_adult' => 'boolean',
            'photo_paths' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ObservatoryReportType::class, 'observatory_report_type_id');
    }

    public function kindLabel(): string
    {
        if ($this->reportType instanceof ObservatoryReportType) {
            return $this->reportType->name;
        }

        return ObservatoryReportKind::tryFrom((string) $this->kind)?->label()
            ?: (filled($this->kind) ? (string) $this->kind : '—');
    }

    public function typeColor(): string
    {
        return $this->reportType instanceof ObservatoryReportType
            ? $this->reportType->color
            : '#94a3b8';
    }

    public function typeScore(): float
    {
        return $this->reportType instanceof ObservatoryReportType
            ? RiskMath::score((int) $this->reportType->impacto, (int) $this->reportType->urgencia)
            : 0.0;
    }

    public function typeSlug(): string
    {
        return $this->reportType instanceof ObservatoryReportType
            ? $this->reportType->slug
            : (string) $this->kind;
    }

    public function sourceLabel(): string
    {
        return $this->source instanceof ObservatoryReportSource ? $this->source->label() : '—';
    }

    public function roleLabel(): string
    {
        return $this->reporter_role instanceof ObservatoryReporterRole ? $this->reporter_role->label() : '';
    }

    public function originLabel(): string
    {
        $role = $this->roleLabel();

        return $role !== '' ? $this->sourceLabel().' · '.$role : $this->sourceLabel();
    }

    public function reporterLabel(): string
    {
        if ($this->is_anonymous) {
            return 'Anónimo';
        }

        $name = trim((string) $this->reporter_name);

        return $name !== '' ? $name : 'Identificado';
    }

    public function photoUrl(): ?string
    {
        $urls = $this->photoUrls();

        return $urls[0] ?? null;
    }

    /** @return list<string> */
    public function photoUrls(): array
    {
        $paths = [];
        if (filled($this->photo_path)) {
            $paths[] = (string) $this->photo_path;
        }
        foreach ($this->photo_paths ?? [] as $path) {
            if (filled($path)) {
                $paths[] = (string) $path;
            }
        }

        $urls = [];
        foreach (array_values(array_unique($paths)) as $path) {
            $urls[] = Storage::disk('public')->url($path);
        }

        return $urls;
    }

    public function videoUrl(): ?string
    {
        if (! filled($this->video_path)) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->video_path);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ObservatoryEvent::class, 'event_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }
}
