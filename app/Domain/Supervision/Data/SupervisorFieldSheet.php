<?php

declare(strict_types=1);

namespace App\Domain\Supervision\Data;

use App\Enums\SupervisorFieldSheetKind;
use DateTimeInterface;

final readonly class SupervisorFieldSheet
{
    /**
     * Secciones: `rows` texto libre; opcionales `fields` (etiqueta/valor), `table` (head + rows con `cells` y `tone`),
     * `note` (observación resaltada) y `novelty`.
     *
     * @param  list<array{title: string, rows: list<string>, photos: list<array{label: string, src: string}>, fields?: list<array{label: string, value: string}>, table?: array{head: list<string>, rows: list<array{cells: list<string>, tone?: ?string}>, center?: list<int>}, note?: ?string, novelty?: bool}>  $sections
     * @param  array{photos: list<array{label: string, src: ?string, pending?: ?string}>, fields: list<array{label: string, value: string}>}|null  $hero
     * @param  array{photos: list<array{label: string, src: ?string, pending?: ?string}>, fields: list<array{label: string, value: string, strong?: bool}>}|null  $odometer
     * @param  array{src: ?string, fields: list<array{label: string, value: string}>, note: ?string}|null  $routeMap
     * @param  list<array{label: string, novelty: bool}>  $moduleSummary
     * @param  list<string>  $signatures
     */
    public function __construct(
        public SupervisorFieldSheetKind $kind,
        public int $id,
        public string $folio,
        public string $companyName,
        public string $companyLegalName,
        public ?string $companyTaxId,
        public ?string $companyLogoSrc,
        public string $intro,
        public string $supervisorName,
        public ?string $username,
        public ?string $zoneName,
        public ?string $shiftLabel,
        public DateTimeInterface $recordedAt,
        public ?string $clientName,
        public ?string $installationName,
        public ?string $postName,
        public ?string $guardName,
        public bool $hasNovelty,
        public ?string $notes,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $guardPhotoSrc,
        public array $sections,
        public bool $isDraft = false,
        public ?string $mapImageSrc = null,
        public ?array $hero = null,
        public ?array $odometer = null,
        public ?array $routeMap = null,
        public array $moduleSummary = [],
        public array $signatures = [],
    ) {}
}
