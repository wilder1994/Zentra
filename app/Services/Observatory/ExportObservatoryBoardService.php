<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Chart\Gridlines;
use PhpOffice\PhpPresentation\Shape\Chart\Legend;
use PhpOffice\PhpPresentation\Shape\Chart\Marker;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\AbstractType;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Doughnut;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Line;
use PhpOffice\PhpPresentation\Shape\Table\Row;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Outline;

/**
 * Informe PPTX del tablero del Observatorio.
 * Todo sale ya filtrado (alcance, fechas, estado, tipo, comuna) por quien llama.
 */
final class ExportObservatoryBoardService
{
    private const CANVAS = 'FFF8FAFC';

    private const NAVY = 'FF0F172A';

    private const CARD = 'FFFFFFFF';

    private const SLATE = 'FFE2E8F0';

    private const LINE = 'FFCBD5E1';

    private const GOLD = 'FF0F766E';

    private const NUEVO = 'FFEF4444';

    private const TRAMITE = 'FFF59E0B';

    private const CERRADO = 'FF10B981';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FF0F172A';

    private const MUTED = 'FF64748B';

    private const GREEN = 'FF34D399';

    private const AMBER = 'FFF59E0B';

    private const INDIGO = 'FF818CF8';

    private const RED = 'FFF87171';

    private const WHITE = 'FFFFFFFF';

    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * @param  array<string, mixed>  $board  Salida de BuildObservatoryBoardService.
     * @param  array<string, mixed>  $data  Salida de BuildObservatoryExportDataService.
     * @param  array{scope: string, scope_kind: string, company: string, logo_path: ?string, filters: list<string>, from: ?string, to: ?string, grain: string}  $meta
     * @return array{path: string, filename: string}
     */
    public function execute(array $board, array $data, array $meta): array
    {
        $this->tempFiles = [];

        $ppt = new PhpPresentation;
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);
        $ppt->getDocumentProperties()
            ->setCreator('Zentra')
            ->setLastModifiedBy('Zentra')
            ->setTitle('Informe del Observatorio')
            ->setSubject($this->caption($meta))
            ->setDescription('Informe del tablero del Observatorio');

        try {
            $logo = $this->companyLogo($meta['logo_path'] ?? null);

            $this->coverSlide($ppt->getActiveSlide(), $meta, $logo);
            $this->summarySlide($this->slide($ppt), $board, $meta);
            $this->attentionSlide($this->slide($ppt), $board, $meta);
            $this->originSlide($this->slide($ppt), $board, $meta);
            $this->crimeSlide($this->slide($ppt), $board, $meta);
            $this->riskSlide($this->slide($ppt), $meta);
            $this->comunaSlide($this->slide($ppt), $meta);
            $this->closingSlide($this->slide($ppt), $meta, $logo);

            $path = $this->save($ppt);
        } finally {
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }
            $this->tempFiles = [];
        }

        return [
            'path' => $path,
            'filename' => $this->fileName($meta),
        ];
    }

    /** «1 ene 2026 – 21 oct 2026». */
    public function periodLabel(?string $from, ?string $to): string
    {
        $fmt = fn (string $date): string => Carbon::parse($date)->locale('es')->isoFormat('D MMM YYYY');

        return match (true) {
            filled($from) && filled($to) => $fmt($from).' – '.$fmt($to),
            filled($from) => 'Desde '.$fmt($from),
            filled($to) => 'Hasta '.$fmt($to),
            default => 'Periodo abierto',
        };
    }

    /** @param  array{scope: string, scope_kind: string, filters: list<string>, from: ?string, to: ?string}  $meta */
    /**
     * @param  array{parameters?: \App\Models\RiskModelParameter, rows?: list<array<string, mixed>>}  $risk
     * @return list<array{name: string, comuna: string, reports: int, score: float, band: string, color: string}>
     */
    public function riskRows(array $risk): array
    {
        $parameters = $risk['parameters'] ?? null;
        $rows = [];
        foreach ($risk['rows'] ?? [] as $row) {
            $band = (string) ($row['band'] ?? 'BAJO');
            $rows[] = [
                'name' => (string) ($row['name'] ?? ''),
                'comuna' => (string) ($row['comuna'] ?? ''),
                'reports' => (int) ($row['reports'] ?? 0),
                'score' => (float) ($row['r_final'] ?? 0),
                'band' => $band,
                'color' => $parameters?->colorFor($band) ?? '#14532d',
            ];
        }

        return $rows;
    }

    public function fileName(array $meta): string
    {
        $from = $meta['from'] ?: now()->toDateString();
        $to = $meta['to'] ?: $from;

        return sprintf(
            'Informe_Observatorio_%s_%s_%s.pptx',
            Str::slug($meta['scope']) ?: 'tablero',
            $from,
            $to,
        );
    }

    /** @param  array{scope: string, scope_kind: string, filters: list<string>, from: ?string, to: ?string}  $meta */
    private function caption(array $meta): string
    {
        $parts = [$this->periodLabel($meta['from'], $meta['to']), $meta['scope_kind'].': '.$meta['scope']];

        return implode(' · ', array_merge($parts, $meta['filters']));
    }

    // ── Diapositivas ─────────────────────────────────────────────

    /** @param  array{scope: string, scope_kind: string, filters: list<string>, from: ?string, to: ?string, grain?: string, company: string}  $meta */
    private function coverSlide(Slide $slide, array $meta, ?string $logo): void
    {
        $this->paintSlide($slide);

        $this->image($slide, $this->zentraLogo(), 60, 36, 220, 72);
        if ($logo !== null) {
            $this->image($slide, $logo, 680, 36, 220, 72);
        } else {
            $this->text($slide, $meta['company'], 560, 52, 340, 36, 16, self::INK, true, Alignment::HORIZONTAL_RIGHT);
        }

        $this->box($slide, 60, 128, 120, 3, self::GOLD);
        $this->text($slide, 'INFORME DEL OBSERVATORIO', 60, 148, 840, 24, 13, self::GOLD, true);
        $scopeSize = mb_strlen($meta['scope']) > 48 ? 22 : 32;
        $this->text($slide, Str::limit($meta['scope'], 80), 60, 180, 840, 70, $scopeSize, self::INK, true);
        $this->text($slide, $meta['scope_kind'].' · '.$this->periodLabel($meta['from'], $meta['to']).' · '.$this->grainLabel((string) ($meta['grain'] ?? 'year')), 60, 300, 840, 28, 15, self::MUTED);
        $this->text(
            $slide,
            $meta['filters'] === [] ? 'Sin filtros adicionales' : 'Filtros: '.implode(' · ', $meta['filters']),
            60,
            340,
            840,
            48,
            14,
            self::INK,
        );

        $this->box($slide, 0, 500, 960, 40, self::NAVY);
        $this->text($slide, 'Generado el '.now()->format('d/m/Y H:i'), 60, 508, 420, 24, 12, self::WHITE);
        $this->text($slide, 'Zentra · Observatorio', 480, 508, 420, 24, 12, self::WHITE, false, Alignment::HORIZONTAL_RIGHT);
    }

    /** @param  array<string, mixed>  $board */
    private function summarySlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Resumen', $this->caption($meta));

        $total = (int) $board['total'];
        $reports = (int) array_sum($board['kinds']['values'] ?? []);
        $nuevo = (int) $board['nuevo'];
        $tramite = (int) $board['en_atencion'];
        $cerrado = (int) $board['cerrado'];
        $load = (int) ($board['load_rate'] ?? 0);
        $level = $total === 0 ? 'sin folios' : ($load >= 67 ? 'alta' : ($load >= 34 ? 'media' : 'baja'));

        $cards = [
            [$this->int($total), 'Folios', 'En el periodo hay '.$this->count($total, 'folio', 'folios').'. Reúnen '.$this->count($reports, 'reporte', 'reportes').'.'],
            [$this->int($nuevo), 'Nuevos', $this->count($nuevo, 'folio sigue nuevo', 'folios siguen nuevos').', sin atención.'],
            [$this->int($tramite), 'En trámite', $this->count($tramite, 'folio está en trámite', 'folios están en trámite').', en seguimiento.'],
            [$this->int($cerrado), 'Cerrados', $this->count($cerrado, 'folio está cerrado', 'folios están cerrados').', el '.$this->pct($cerrado, $total).' del total.'],
            [$load.' %', 'Gestión', 'La gestión del periodo es '.$load.' %. Queda '.$level.'.'],
        ];

        foreach ($cards as $index => [$value, $label, $copy]) {
            $y = 108 + ($index * 78);
            $this->box($slide, 40, $y, 250, 70, self::CARD);
            $this->box($slide, 40, $y, 4, 70, self::GOLD);
            $this->text($slide, $value, 56, $y + 8, 220, 28, 20, self::INK, true);
            $this->text($slide, mb_strtoupper($label), 56, $y + 38, 220, 22, 11, self::MUTED, true);
            $this->text($slide, $copy, 320, $y + 8, 590, 62, 14, self::INK);
        }
    }

    /** @param  array<string, mixed>  $board */
    private function attentionSlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Atención y fuentes', $this->caption($meta));

        $attention = [];
        $attentionColors = [];
        foreach ([
            ['Cerrados', (int) ($board['cerrado'] ?? 0), self::CERRADO],
            ['En trámite', (int) ($board['en_atencion'] ?? 0), self::TRAMITE],
            ['Nuevos', (int) ($board['nuevo'] ?? 0), self::NUEVO],
        ] as [$label, $value, $color]) {
            if ($value < 1) {
                continue;
            }
            $attention[$label] = $value;
            $attentionColors[] = $color;
        }
        $this->donut($slide, 'Atención de folios', $attention, $attentionColors, 36, 100, 430, 300);
        $this->text($slide, $this->leaderNote('La atención se concentra en', $attention, 'No hay folios en el periodo.'), 36, 408, 430, 90, 13, self::INK);

        [$labels, $values, $colors] = $this->positiveColored($board['sources']['labels'] ?? [], $board['sources']['values'] ?? [], $board['sources']['colors'] ?? []);
        $sources = $this->combine($labels, $values);
        $this->donut($slide, 'Fuentes', $sources, $colors, 494, 100, 430, 300);
        $this->text($slide, $this->leaderNote('La fuente principal es', $sources, 'No hay reportes con fuente en el periodo.'), 494, 408, 430, 90, 13, self::INK);
    }

    /** @param  array<string, mixed>  $board */
    private function originSlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Orígenes y tendencia', $this->caption($meta));

        [$labels, $values, $colors] = $this->positiveColored($board['origins']['labels'] ?? [], $board['origins']['values'] ?? [], $board['origins']['colors'] ?? []);
        $origins = $this->combine($labels, $values);
        $this->coloredBars($slide, 'Orígenes', $origins, $colors, 36, 100, 430, 300, true);
        $this->text($slide, $this->leaderNote('El origen que más pesa es', $origins, 'No hay orígenes en el periodo.'), 36, 408, 430, 90, 13, self::INK);

        $grain = $this->grainLabel((string) ($board['grain'] ?? $meta['grain'] ?? 'year'));
        $labels = array_values($board['trend']['labels'] ?? []);
        $series = [];
        foreach ($board['trend']['series'] ?? [] as $row) {
            $series[] = [
                'label' => (string) ($row['label'] ?? 'Reportes'),
                'color' => (string) ($row['color'] ?? '#0f766e'),
                'values' => array_map(intval(...), $row['values'] ?? []),
            ];
        }
        $this->lines($slide, 'Tendencia · '.$grain, $labels, $series, 494, 100, 430, 300);
        $this->text($slide, $this->trendNote($labels, $series), 494, 408, 430, 90, 13, self::INK);
    }

    /** @param  array<string, mixed>  $board */
    private function crimeSlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Delitos y reportes', $this->caption($meta));

        $crimes = $board['crimes'] ?? [];
        $colors = $this->spreadInks(array_map(strval(...), $crimes['colors'] ?? []));
        $crimeValues = $this->combine($crimes['labels'] ?? [], $crimes['values'] ?? []);
        $this->coloredBars($slide, 'Delitos', $crimeValues, array_map($this->argb(...), $colors), 36, 100, 430, 300, true);
        $this->text($slide, $this->leaderNote('El delito con más reportes es', $crimeValues, 'No hay delitos en el periodo.'), 36, 408, 430, 90, 13, self::INK);

        $rows = array_slice($board['sites']['rows'] ?? [], 0, 8);
        $this->siteBars($slide, $rows, 494, 100, 430, 300);
        $this->text($slide, $this->siteNote($rows), 494, 408, 430, 90, 13, self::INK);
    }

    private function riskSlide(Slide $slide, array $meta): void
    {
        $this->heading($slide, 'Riesgo por instalación', 'Top 20 por índice de riesgo');

        $rows = array_values($meta['risk'] ?? []);
        usort($rows, static fn (array $a, array $b): int => ((float) $b['score']) <=> ((float) $a['score']) ?: strcmp((string) $a['name'], (string) $b['name']));
        $rows = array_slice($rows, 0, 20);
        if ($rows === []) {
            $this->text($slide, 'No hay instalaciones con índice de riesgo en este alcance.', 40, 220, 880, 40, 16, self::MUTED);

            return;
        }

        $this->splitGrid($slide, ['Instalación', 'Reportes', 'R', 'Banda'], $rows, [210, 70, 50, 70], function (array $row): array {
            return [
                'cells' => [
                    Str::limit((string) $row['name'], 28),
                    $this->int((int) ($row['reports'] ?? 0)),
                    number_format((float) ($row['score'] ?? 0), 1, ',', '.'),
                    $this->bandLabel((string) ($row['band'] ?? 'BAJO')),
                ],
                'inks' => [self::INK, self::INK, self::INK, $this->argb((string) ($row['color'] ?? '#14532d'))],
            ];
        });
    }

    private function comunaSlide(Slide $slide, array $meta): void
    {
        $this->heading($slide, 'Riesgo delictivo', 'Por comuna, en el orden del tablero');

        $rows = array_values($meta['comunas'] ?? []);
        if ($rows === []) {
            $this->text($slide, 'No hay comunas para este filtro.', 40, 220, 880, 40, 16, self::MUTED);

            return;
        }

        $this->splitGrid($slide, ['Comuna', 'Índice', 'Banda'], $rows, [220, 70, 80], function (array $row): array {
            return [
                'cells' => [
                    Str::limit((string) $row['name'], 26),
                    number_format((float) ($row['score'] ?? 0), 1, ',', '.'),
                    $this->bandLabel((string) ($row['band'] ?? 'BAJO')),
                ],
                'inks' => [self::INK, self::INK, $this->argb((string) ($row['color'] ?? '#14532d'))],
            ];
        });
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $widths
     * @param  callable(array<string, mixed>): array{cells: list<string>, inks: list<string>}  $map
     */
    private function splitGrid(Slide $slide, array $headers, array $rows, array $widths, callable $map): void
    {
        $half = (int) ceil(count($rows) / 2);
        $this->grid($slide, $headers, array_map($map, array_slice($rows, 0, $half)), $widths, 36, 108, 22);
        $right = array_slice($rows, $half);
        if ($right !== []) {
            $this->grid($slide, $headers, array_map($map, $right), $widths, 500, 108, 22);
        }
    }

    /** @param  array<string, int>  $values */
    private function leaderNote(string $lead, array $values, string $empty): string
    {
        if ($values === [] || array_sum($values) === 0) {
            return $empty;
        }
        arsort($values);
        $label = (string) array_key_first($values);

        return $lead.' '.$label.', con '.$this->count((int) $values[$label], 'registro', 'registros').'.';
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, values: list<int>}>  $series
     */
    private function trendNote(array $labels, array $series): string
    {
        $totals = [];
        foreach ($labels as $index => $label) {
            $sum = 0;
            foreach ($series as $row) {
                $sum += (int) ($row['values'][$index] ?? 0);
            }
            $totals[$label] = $sum;
        }
        if (array_sum($totals) === 0) {
            return 'No hay reportes en la tendencia de este periodo.';
        }
        arsort($totals);
        $label = (string) array_key_first($totals);

        return 'El punto más alto de la tendencia es '.$label.', con '.$this->count((int) $totals[$label], 'reporte', 'reportes').'.';
    }

    /** @param  list<array{name: string, parts: list<array{count: int}>}>  $rows */
    private function siteNote(array $rows): string
    {
        if ($rows === []) {
            return 'No hay reportes por instalación en el periodo.';
        }
        $top = $rows[0];
        $count = 0;
        foreach ($top['parts'] ?? [] as $part) {
            $count += (int) ($part['count'] ?? 0);
        }

        return 'La instalación con más reportes es '.Str::limit((string) $top['name'], 42).', con '.$this->count($count, 'reporte', 'reportes').'.';
    }

    private function closingSlide(Slide $slide, array $meta, ?string $logo): void
    {
        $this->text($slide, 'Gracias', 0, 168, 960, 70, 44, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->box($slide, 400, 270, 160, 4, self::GOLD);
        $this->text($slide, $meta['scope'], 0, 290, 960, 34, 20, self::CYAN, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, 'Observatorio · '.$this->periodLabel($meta['from'], $meta['to']), 0, 326, 960, 26, 13, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        if ($logo !== null) {
            $this->image($slide, $this->zentraLogo(), 300, 400, 170, 64);
            $this->box($slide, 490, 400, 170, 64, self::WHITE);
            $this->image($slide, $logo, 498, 406, 154, 52);
        } else {
            $this->image($slide, $this->zentraLogo(), 395, 400, 170, 64);
        }
    }

    // ── Gráficos ─────────────────────────────────────────────────

    private function chart(Slide $slide, AbstractType $type, int $x, int $y, int $width, int $height, string $title, bool $legend = true): void
    {
        $chart = $slide->createChartShape();
        $chart->setResizeProportional(false)
            ->setIncludeSpreadsheet(true)
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($width)
            ->setHeight($height);
        $chart->setFill($this->solid(self::CARD));

        $chart->getTitle()->setText($title);
        $chart->getTitle()->getFont()->setName('Calibri')->setSize(12)->setBold(true)->setColor(new Color(self::INK));

        $chart->getLegend()->setVisible($legend);
        $chart->getLegend()->setPosition(Legend::POSITION_BOTTOM);
        $chart->getLegend()->getFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::MUTED));

        $plot = $chart->getPlotArea();
        $plot->setType($type);
        foreach ([$plot->getAxisX(), $plot->getAxisY()] as $axis) {
            $axis->setTitle('');
            $axis->getTickLabelFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::MUTED));
        }

        if (! $type instanceof Doughnut) {
            $grid = new Gridlines;
            $grid->getOutline()->setWidth(1);
            $grid->getOutline()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::SLATE));
            $plot->getAxisY()->setMajorGridlines($grid);
            $plot->getAxisX()->setFormatCode('#,##0');
            $plot->getAxisY()->setFormatCode('#,##0');
        }
    }

    /**
     * @param  array<string, int>  $values
     * @param  list<string>  $colors
     */
    private function donut(Slide $slide, string $title, array $values, array $colors, int $x, int $y, int $width, int $height): void
    {
        if (array_sum($values) === 0) {
            $this->emptyChart($slide, $title, $x, $y, $width, $height);

            return;
        }

        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue(true);
        $series->setShowPercentage(false);
        $series->setDlblNumFormat('#,##0;-#,##0;;');
        $series->getFont()->setName('Calibri')->setSize(10)->setBold(true)->setColor(new Color(self::WHITE));
        foreach (array_values($colors) as $index => $color) {
            $series->getDataPointFill($index)->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
        }

        $donut = new Doughnut;
        $donut->setHoleSize(55);
        $donut->addSeries($series);

        $this->chart($slide, $donut, $x, $y, $width, $height, $title);
    }

    /**
     * Barras de una serie, cada barra con su color (o todas con el primero).
     *
     * @param  array<string, int>  $values
     * @param  list<string>  $colors
     */
    private function coloredBars(Slide $slide, string $title, array $values, array $colors, int $x, int $y, int $width, int $height, bool $horizontal = false): void
    {
        if (array_sum($values) === 0) {
            $this->emptyChart($slide, $title, $x, $y, $width, $height);

            return;
        }

        $series = $this->barSeries($title, $values, $colors[0] ?? self::CYAN, true);
        if (count($colors) > 1) {
            foreach (array_values($colors) as $index => $color) {
                $series->getDataPointFill($index)->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
            }
        }

        $bar = new Bar;
        $bar->setBarDirection($horizontal ? Bar::DIRECTION_HORIZONTAL : Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);
        $bar->setGapWidthPercent(70);
        $bar->addSeries($series);

        $this->chart($slide, $bar, $x, $y, $width, $height, $title, false);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, color: string, values: list<int>}>  $series
     */
    private function lines(Slide $slide, string $title, array $labels, array $series, int $x, int $y, int $width, int $height): void
    {
        $has = false;
        foreach ($series as $row) {
            if (array_sum($row['values']) > 0) {
                $has = true;
            }
        }
        if ($labels === [] || ! $has) {
            $this->emptyChart($slide, $title, $x, $y, $width, $height);

            return;
        }

        $line = new Line;
        foreach ($series as $row) {
            $values = [];
            foreach (array_values($labels) as $index => $label) {
                $values[(string) $label] = (int) ($row['values'][$index] ?? 0);
            }
            $line->addSeries($this->lineSeries((string) $row['label'], $values, $this->argb((string) $row['color']), false));
        }

        $this->chart($slide, $line, $x, $y, $width, $height, $title);
    }

    /** @param  list<array{name: string, parts: list<array{name: string, color: string, count: int}>}>  $rows */
    private function siteBars(Slide $slide, array $rows, int $x, int $y, int $width, int $height): void
    {
        if ($rows === []) {
            $this->emptyChart($slide, 'Reportes por instalación', $x, $y, $width, $height);

            return;
        }

        $sites = [];
        $matrix = [];
        $colors = [];
        foreach ($rows as $row) {
            $sites[] = (string) $row['name'];
            $parts = $row['parts'] ?? [];
            $inks = $this->spreadInks(array_map(fn (array $part): string => (string) ($part['color'] ?? '#14532d'), $parts));
            foreach ($parts as $index => $part) {
                $name = (string) $part['name'];
                $matrix[$name][(string) $row['name']] = (int) $part['count'];
                $colors[$name] ??= $inks[$index] ?? '#14532d';
            }
        }

        $bar = new Bar;
        $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
        $bar->setBarGrouping(Bar::GROUPING_STACKED);
        $bar->setGapWidthPercent(40);
        foreach ($matrix as $crime => $counts) {
            $values = [];
            foreach ($sites as $site) {
                $values[$site] = $counts[$site] ?? 0;
            }
            $series = $this->barSeries($crime, $values, $this->argb($colors[$crime]), false, true);
            $series->setShowValue(false);
            $bar->addSeries($series);
        }

        $this->chart($slide, $bar, $x, $y, $width, $height, 'Reportes por instalación');
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array{cells: list<string>, inks: list<string>}>  $rows
     * @param  list<int>  $widths
     */
    private function grid(Slide $slide, array $headers, array $rows, array $widths, int $x = 40, int $y = 108, int $rowHeight = 28): void
    {
        $table = $slide->createTableShape(count($headers));
        $table->setOffsetX($x)->setOffsetY($y);
        $this->tableRow($table->createRow(), $headers, $widths, self::NAVY, array_fill(0, count($headers), self::WHITE), true, 20);
        foreach ($rows as $row) {
            $this->tableRow($table->createRow(), $row['cells'], $widths, self::CARD, $row['inks'], false, $rowHeight);
        }
    }

    private function emptyChart(Slide $slide, string $title, int $x, int $y, int $width, int $height): void
    {
        $this->box($slide, $x, $y, $width, $height, self::SLATE);
        $this->text($slide, $title, $x, $y + 10, $width, 24, 12, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, 'Sin registros en el periodo', $x, $y + (int) ($height / 2) - 12, $width, 24, 12, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
    }

    /** @param  array<string, int>  $values */
    private function barSeries(string $title, array $values, string $color, bool $showValues, bool $stacked = false): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue($showValues);
        $series->setFill($this->solid($color));
        $series->setLabelPosition($stacked ? Series::LABEL_CENTER : Series::LABEL_OUTSIDEEND);
        $series->setDlblNumFormat('#,##0;-#,##0;;');
        $series->getFont()->setName('Calibri')->setSize(9)->setBold($stacked)->setColor(new Color($stacked ? self::NAVY : self::INK));

        return $series;
    }

    /** @param  array<string, int>  $values */
    private function lineSeries(string $title, array $values, string $color, bool $showValues): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue($showValues);
        $series->setLabelPosition(Series::LABEL_TOP);
        $series->setDlblNumFormat('#,##0;-#,##0;;');
        $series->getFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::INK));

        $outline = new Outline;
        $outline->setWidth(3);
        $outline->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
        $series->setOutline($outline);
        $series->getMarker()->setSymbol(Marker::SYMBOL_CIRCLE)->setSize(6);
        $series->getMarker()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));

        return $series;
    }

    /**
     * @param  list<string>  $rows
     * @param  list<int>  $widths
     */
    private function tableRow(Row $row, array $values, array $widths, string $fill, string|array $ink, bool $header = false, int $height = 28): void
    {
        $inks = is_array($ink) ? $ink : array_fill(0, count($values), $ink);
        $row->setHeight($header ? 20 : $height);
        foreach ($values as $index => $value) {
            $cell = $row->nextCell();
            $cell->setWidth($widths[$index]);
            $cell->setFill($this->solid($fill));
            $cell->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setMarginLeft(6)
                ->setMarginRight(6);
            $run = $cell->createTextRun($value);
            $run->getFont()->setName('Calibri')->setSize($header ? 9 : 10)->setBold($header)->setColor(new Color($inks[$index] ?? self::INK));
            foreach ([$cell->getBorders()->getTop(), $cell->getBorders()->getBottom(), $cell->getBorders()->getLeft(), $cell->getBorders()->getRight()] as $border) {
                $border->setColor(new Color(self::LINE));
            }
        }
    }

    // ── Utilidades ───────────────────────────────────────────────

    /**
     * @param  list<mixed>  $labels
     * @param  list<mixed>  $values
     * @return array<string, int>
     */
    private function combine(array $labels, array $values): array
    {
        $out = [];
        foreach (array_values($labels) as $index => $label) {
            $name = trim((string) $label);
            if ($name === '' || $name === '—') {
                continue;
            }
            $out[$name] = (int) ($values[$index] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, string>
     */
    private function chartValues(array $values): array
    {
        if ($values === []) {
            return ['Sin datos' => '0'];
        }

        $mapped = [];
        foreach ($values as $label => $value) {
            $mapped[(string) $label] = (string) $value;
        }

        return $mapped;
    }

    private function grainLabel(string $grain): string
    {
        return $grain === 'day' ? 'Por día' : 'Por mes';
    }

    private function bandLabel(string $band): string
    {
        return match ($band) {
            'CRITICO' => 'Crítico',
            'ALTO' => 'Alto',
            'MEDIO' => 'Medio',
            default => 'Bajo',
        };
    }

    /**
     * @param  list<mixed>  $labels
     * @param  list<mixed>  $values
     * @return array{0: list<string>, 1: list<int>}
     */
    private function positive(array $labels, array $values): array
    {
        $keptLabels = [];
        $keptValues = [];
        foreach (array_values($labels) as $index => $label) {
            $value = (int) ($values[$index] ?? 0);
            if ($value < 1) {
                continue;
            }
            $keptLabels[] = (string) $label;
            $keptValues[] = $value;
        }

        return [$keptLabels, $keptValues];
    }

    /**
     * @param  list<mixed>  $labels
     * @param  list<mixed>  $values
     * @param  list<mixed>  $colors
     * @return array{0: list<string>, 1: list<int>, 2: list<string>}
     */
    private function positiveColored(array $labels, array $values, array $colors): array
    {
        $keptLabels = [];
        $keptValues = [];
        $keptColors = [];
        foreach (array_values($labels) as $index => $label) {
            $value = (int) ($values[$index] ?? 0);
            if ($value < 1) {
                continue;
            }
            $keptLabels[] = (string) $label;
            $keptValues[] = $value;
            $keptColors[] = $this->argb((string) ($colors[$index] ?? '#14b8a6'));
        }

        return [$keptLabels, $keptValues, $keptColors];
    }

    /** @param  list<string>  $bases
     * @return list<string>
     */
    private function familyArgb(array $bases, int $count): array
    {
        return array_map($this->argb(...), $this->familyInks($bases, $count));
    }

    /** @param  list<string>  $bases
     * @return list<string>
     */
    private function familyInks(array $bases, int $count): array
    {
        $seeds = $bases !== [] ? array_values($bases) : ['#14532d', '#854d0e', '#9a3412', '#9f1239'];
        $out = [];
        $size = count($seeds);
        for ($index = 0; $index < $count; $index++) {
            $lap = intdiv($index, $size);
            $seed = $seeds[$index % $size];
            $out[] = $lap === 0 ? $seed : $this->shiftInk($seed, $lap);
        }

        return $out;
    }

    /** @param  list<string>  $colors
     * @return list<string>
     */
    private function spreadInks(array $colors): array
    {
        $seen = [];
        $out = [];
        foreach ($colors as $color) {
            $key = strtolower($color);
            $used = $seen[$key] ?? 0;
            $seen[$key] = $used + 1;
            $out[] = $used === 0 ? $color : $this->shiftInk($color, $used);
        }

        return $out;
    }

    private function shiftInk(string $hex, int $step): string
    {
        [$hue, $sat, $light] = $this->rgbToHsl($hex);
        $nextLight = min(64, max(28, $light + ($step % 2 === 0 ? 14 : -10)));

        return $this->hslToHex($hue + ($step * 18), min(72, $sat + 8), $nextLight);
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function rgbToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $light = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $light * 100];
        }
        $delta = $max - $min;
        $sat = $light > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
        $hue = match (true) {
            $max === $red => fmod(($green - $blue) / $delta, 6),
            $max === $green => (($blue - $red) / $delta) + 2,
            default => (($red - $green) / $delta) + 4,
        };

        return [$hue * 60, $sat * 100, $light * 100];
    }

    private function hslToHex(float $hue, float $sat, float $light): string
    {
        $sat /= 100;
        $light /= 100;
        $chroma = (1 - abs((2 * $light) - 1)) * $sat;
        $hue = fmod($hue, 360);
        if ($hue < 0) {
            $hue += 360;
        }
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $light - ($chroma / 2);
        [$red, $green, $blue] = match ((int) floor($hue / 60)) {
            0 => [$chroma, $x, 0],
            1 => [$x, $chroma, 0],
            2 => [0, $chroma, $x],
            3 => [0, $x, $chroma],
            4 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($red + $m) * 255), (int) round(($green + $m) * 255), (int) round(($blue + $m) * 255));
    }

    private function argb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (preg_match('/^[0-9a-fA-F]{8}$/', $hex) === 1) {
            return strtoupper($hex);
        }
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1) {
            return 'FF'.strtoupper($hex);
        }

        return self::CYAN;
    }

    private function zentraLogo(): string
    {
        return resource_path('images/branding/logo-zentra.png');
    }

    private function companyLogo(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $absolute = Storage::disk('local')->path($path);
        $info = @getimagesize($absolute);
        if ($info === false) {
            return null;
        }

        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        if ($info[2] === IMAGETYPE_PNG && $extension === 'png') {
            return $absolute;
        }
        if ($info[2] === IMAGETYPE_JPEG && in_array($extension, ['jpg', 'jpeg'], true)) {
            return $absolute;
        }

        $image = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            default => false,
        };
        if ($image === false) {
            return null;
        }

        $base = tempnam(sys_get_temp_dir(), 'zentra_logo_');
        if ($base === false) {
            imagedestroy($image);

            return null;
        }
        @unlink($base);
        $png = $base.'.png';
        imagesavealpha($image, true);
        imagepng($image, $png);
        imagedestroy($image);
        $this->tempFiles[] = $png;

        return $png;
    }

    /** Ajusta la imagen dentro de la caja sin deformarla y la centra. */
    private function image(Slide $slide, string $path, int $x, int $y, int $maxWidth, int $maxHeight): void
    {
        $size = @getimagesize($path);
        if ($size === false || $size[0] === 0 || $size[1] === 0) {
            return;
        }

        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);
        $width = (int) round($size[0] * $scale);
        $height = (int) round($size[1] * $scale);

        $slide->createDrawingShape()
            ->setPath($path)
            ->setResizeProportional(false)
            ->setWidth($width)
            ->setHeight($height)
            ->setOffsetX($x + (int) (($maxWidth - $width) / 2))
            ->setOffsetY($y + (int) (($maxHeight - $height) / 2));
    }

    private function slide(PhpPresentation $ppt): Slide
    {
        $slide = $ppt->createSlide();
        $this->paintSlide($slide);

        return $slide;
    }

    private function paintSlide(Slide $slide): void
    {
        $background = new BackgroundColor;
        $background->setColor(new Color(self::CANVAS));
        $slide->setBackground($background);

        $this->box($slide, 0, 0, 960, 8, self::GOLD);
    }

    private function heading(Slide $slide, string $title, string $caption): void
    {
        $this->text($slide, $title, 40, 18, 880, 36, 26, self::INK, true);
        $this->box($slide, 40, 56, 72, 3, self::GOLD);
        $this->text($slide, $caption, 40, 64, 880, 24, 12, self::MUTED);
    }

    /** @param  list<array{0: string, 1: string}>  $paragraphs */
    private function paragraphs(Slide $slide, array $paragraphs, int $x, int $y, int $width, int $height, int $size = 12): void
    {
        $this->box($slide, $x, $y, 4, $height, self::GOLD);

        $shape = $slide->createRichTextShape()
            ->setOffsetX($x + 4)
            ->setOffsetY($y)
            ->setWidth($width - 4)
            ->setHeight($height);
        $shape->setFill($this->solid(self::CARD));
        $shape->setInsetLeft(14)->setInsetRight(14)->setInsetTop(10)->setInsetBottom(10);

        foreach ($paragraphs as $index => [$content, $color]) {
            $paragraph = $index === 0 ? $shape->getActiveParagraph() : $shape->createParagraph();
            $paragraph->getAlignment()->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
            $run = $paragraph->createTextRun($content);
            $run->getFont()->setName('Calibri')->setSize($size)->setColor(new Color($color));
        }
    }

    private function box(Slide $slide, int $x, int $y, int $width, int $height, string $fill): void
    {
        $shape = $slide->createRichTextShape()
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($width)
            ->setHeight($height);
        $shape->setFill($this->solid($fill));
    }

    private function text(
        Slide $slide,
        string $content,
        int $x,
        int $y,
        int $width,
        int $height,
        int $size,
        string $color,
        bool $bold = false,
        string $align = Alignment::HORIZONTAL_LEFT,
    ): void {
        $shape = $slide->createRichTextShape()
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($width)
            ->setHeight($height);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal($align);
        $run = $shape->createTextRun($content);
        $run->getFont()
            ->setName('Calibri')
            ->setSize($size)
            ->setBold($bold)
            ->setColor(new Color($color));
    }

    private function count(int $value, string $one, string $many): string
    {
        return $this->int($value).' '.($value === 1 ? $one : $many);
    }

    private function int(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function pct(int|float $part, int|float $total): string
    {
        return $total > 0 ? number_format($part * 100 / $total, 1, ',', '.').' %' : '0 %';
    }

    private function solid(string $argb): Fill
    {
        $fill = new Fill;
        $fill->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($argb));

        return $fill;
    }

    private function save(PhpPresentation $ppt): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zentra_obs_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del Observatorio.');
        }

        unlink($path);
        $path .= '.pptx';
        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);

        return $path;
    }
}
