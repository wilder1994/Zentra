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

/**
 * Informe PPTX del tablero del Observatorio, con el mismo estilo del informe de Supervisión.
 * Todo sale ya filtrado (alcance, fechas, estado, tipo, comuna) por quien llama.
 */
final class ExportObservatoryBoardService
{
    private const NAVY = 'FF0F172A';

    private const CARD = 'FF1E293B';

    private const SLATE = 'FF334155';

    private const GOLD = 'FF14B8A6';

    private const NUEVO = 'FFEF4444';

    private const TRAMITE = 'FFF59E0B';

    private const CERRADO = 'FF10B981';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FFF8FAFC';

    private const MUTED = 'FF94A3B8';

    private const GREEN = 'FF34D399';

    private const AMBER = 'FFF59E0B';

    private const INDIGO = 'FF818CF8';

    private const RED = 'FFF87171';

    private const WHITE = 'FFFFFFFF';

    /** @var list<string> */
    private array $tempFiles = [];

    public function __construct(
        private readonly RenderObservatoryHeatMapService $heatMap,
    ) {}

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

            $this->coverSlide($ppt->getActiveSlide(), $board, $meta, $logo);
            $this->summarySlide($this->slide($ppt), $board, $meta);
            $this->heatMapSlide($this->slide($ppt), $data, $meta);
            $this->mixSlide($this->slide($ppt), $board, $meta);
            $this->trendSlide($this->slide($ppt), $board, $meta);
            $this->rankSlide($this->slide($ppt), $board, $meta);
            $this->riskSlides($ppt, $meta);
            $this->reportSlides($ppt, $data, $meta);
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

    /** @param  array<string, mixed>  $board */
    private function coverSlide(Slide $slide, array $board, array $meta, ?string $logo): void
    {
        $this->paintSlide($slide);

        $this->image($slide, $this->zentraLogo(), 60, 48, 280, 104);
        $this->box($slide, 640, 48, 260, 104, self::WHITE);
        if ($logo !== null) {
            $this->image($slide, $logo, 652, 56, 236, 88);
        } else {
            $this->text($slide, $meta['company'], 652, 82, 236, 40, 16, self::NAVY, true, Alignment::HORIZONTAL_CENTER);
        }

        $this->box($slide, 60, 180, 840, 2, self::SLATE);

        $this->text($slide, 'INFORME DEL OBSERVATORIO', 60, 200, 840, 26, 14, self::GOLD, true);
        $this->text($slide, 'Eventos y riesgos', 60, 228, 840, 60, 40, self::INK, true);
        $scopeSize = mb_strlen($meta['scope']) > 48 ? 15 : (mb_strlen($meta['scope']) > 36 ? 18 : 22);
        $this->text($slide, Str::limit($meta['scope'], 90), 60, 294, 840, 34, $scopeSize, self::CYAN, true);
        $this->text($slide, $meta['scope_kind'].' · '.$this->periodLabel($meta['from'], $meta['to']).' · '.$this->grainLabel((string) ($meta['grain'] ?? 'year')), 60, 332, 840, 26, 15, self::MUTED);
        $this->text(
            $slide,
            $meta['filters'] === [] ? 'Sin filtros adicionales' : 'Filtros: '.implode(' · ', $meta['filters']),
            60,
            360,
            840,
            24,
            12,
            self::MUTED,
        );
        $this->text(
            $slide,
            $this->count((int) $board['total'], 'folio', 'folios').' · '.$this->int((int) $board['cerrado']).' cerrados · '
                .$this->int((int) $board['nuevo'] + (int) $board['en_atencion']).' por atender',
            60,
            400,
            840,
            26,
            14,
            self::INK,
            true,
        );

        $this->box($slide, 0, 470, 960, 70, self::CARD);
        $this->box($slide, 0, 470, 8, 70, self::GOLD);
        $this->text($slide, 'Generado el '.now()->format('d/m/Y H:i'), 60, 492, 420, 26, 12, self::MUTED);
        $this->text($slide, 'Zentra · Observatorio', 480, 492, 420, 26, 12, self::MUTED, false, Alignment::HORIZONTAL_RIGHT);
    }

    /** @param  array<string, mixed>  $board */
    private function summarySlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Tablero', $this->caption($meta));

        $total = (int) $board['total'];
        $load = (int) ($board['load_rate'] ?? 0);
        $loadFill = $total === 0 ? self::CARD : match (true) {
            $load >= 67 => self::GREEN,
            $load >= 34 => self::GOLD,
            default => self::RED,
        };
        $loadInk = $total === 0 ? self::INK : self::NAVY;

        $boxes = [
            [$this->int($total), 'Folios', $this->int((int) array_sum($board['kinds']['values'] ?? [])).' reportes', self::CARD, self::INK],
            [$this->int((int) $board['nuevo']), 'Nuevos', 'sin atender aún', self::CARD, self::NUEVO],
            [$this->int((int) $board['en_atencion']), 'En trámite', 'en seguimiento', self::CARD, self::TRAMITE],
            [$this->int((int) $board['cerrado']), 'Cerrados', $this->pct((int) $board['cerrado'], $total).' del total', self::CARD, self::CERRADO],
            [$load.' %', 'Gestión', $total === 0 ? 'sin folios' : ($load >= 67 ? 'alta' : ($load >= 34 ? 'media' : 'baja')), $loadFill, $loadInk],
        ];
        foreach ($boxes as $index => [$value, $label, $detail, $fill, $ink]) {
            $shape = $slide->createRichTextShape()
                ->setOffsetX(40 + ($index * 178))
                ->setOffsetY(100)
                ->setWidth(168)
                ->setHeight(96);
            $shape->setFill($this->solid($fill));
            $shape->setInsetLeft(12)->setInsetTop(10);
            $run = $shape->createTextRun($value);
            $run->getFont()->setName('Calibri')->setBold(true)->setSize(24)->setColor(new Color($ink));
            $shape->createBreak();
            $run = $shape->createTextRun(mb_strtoupper($label));
            $run->getFont()->setName('Calibri')->setBold(true)->setSize(9)->setColor(new Color($fill === self::CARD ? self::INK : self::NAVY));
            $shape->createBreak();
            $run = $shape->createTextRun($detail);
            $run->getFont()->setName('Calibri')->setSize(9)->setColor(new Color($fill === self::CARD ? self::MUTED : self::NAVY));
        }
    }

    /** @param  array<string, mixed>  $data */
    private function heatMapSlide(Slide $slide, array $data, array $meta): void
    {
        $this->heading($slide, 'Mapa', $this->caption($meta));

        $map = $this->heatMap->execute($data['points']);
        if ($map === null) {
            $this->box($slide, 40, 100, 880, 338, self::CARD);
            $this->text($slide, 'Ningún folio del periodo tiene ubicación en el mapa.', 40, 250, 880, 30, 14, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
        } else {
            $this->tempFiles[] = $map['path'];
            $this->box($slide, 38, 98, 604, 342, self::SLATE);
            $this->image($slide, $map['path'], 40, 100, 600, 338);
            $this->pointsPanel($slide, $map['groups']);
        }

        $legend = [];
        foreach ($data['by_type'] as $type) {
            if ($type['events'] > 0) {
                $legend[] = $type['name'].' '.$this->int($type['events']);
            }
        }
        $note = $this->count(count($data['points']), 'folio ubicado', 'folios ubicados').' en el mapa';
        if ($data['without_coords'] > 0) {
            $note .= ' · '.$this->count($data['without_coords'], 'sin ubicación', 'sin ubicación');
        }
        if ($legend !== []) {
            $note .= ' · '.implode(' · ', $legend);
        }
        $this->text($slide, $note, 40, 456, 880, 48, 12, self::MUTED);
    }

    /** @param  list<array{site: string, folios: list<string>, color: string}>  $groups */
    private function pointsPanel(Slide $slide, array $groups): void
    {
        $shape = $slide->createRichTextShape()
            ->setOffsetX(656)
            ->setOffsetY(100)
            ->setWidth(264)
            ->setHeight(338);
        $shape->setFill($this->solid(self::CARD));
        $shape->setInsetLeft(12)->setInsetRight(10)->setInsetTop(10);

        $title = $shape->getActiveParagraph()->createTextRun('PUNTOS CON MÁS FOLIOS');
        $title->getFont()->setName('Calibri')->setBold(true)->setSize(10)->setColor(new Color(self::GOLD));

        foreach (array_slice($groups, 0, 7) as $group) {
            $paragraph = $shape->createParagraph();
            $paragraph->setSpacingBefore(6);
            $dot = $paragraph->createTextRun('● ');
            $dot->getFont()->setName('Calibri')->setSize(11)->setColor(new Color($this->argb($group['color'])));
            $name = $paragraph->createTextRun(Str::limit($group['site'], 30).' · '.count($group['folios']));
            $name->getFont()->setName('Calibri')->setBold(true)->setSize(10)->setColor(new Color(self::INK));

            $folios = $shape->createParagraph();
            $shown = array_slice($group['folios'], 0, 4);
            $rest = count($group['folios']) - count($shown);
            $run = $folios->createTextRun('   '.implode(', ', $shown).($rest > 0 ? ' y '.$rest.' más' : ''));
            $run->getFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::MUTED));
        }
    }

    /** @param  array<string, mixed>  $board */
    private function mixSlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Atención, fuentes y orígenes', $this->caption($meta));

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
        $this->donut($slide, 'Atención de folios', $attention, $attentionColors, 40, 100, 288, 390);

        [$labels, $values, $colors] = $this->positiveColored($board['sources']['labels'] ?? [], $board['sources']['values'] ?? [], $board['sources']['colors'] ?? []);
        $this->donut($slide, 'Fuentes', $this->combine($labels, $values), $colors, 336, 100, 288, 390);

        [$labels, $values, $colors] = $this->positiveColored($board['origins']['labels'] ?? [], $board['origins']['values'] ?? [], $board['origins']['colors'] ?? []);
        $this->coloredBars($slide, 'Orígenes', $this->combine($labels, $values), $colors, 632, 100, 288, 390, true);
    }

    /** @param  array<string, mixed>  $board */
    private function trendSlide(Slide $slide, array $board, array $meta): void
    {
        $grain = $this->grainLabel((string) ($board['grain'] ?? $meta['grain'] ?? 'year'));
        $this->heading($slide, 'Tendencia · '.$grain, $this->caption($meta));

        $labels = array_values($board['trend']['labels'] ?? []);
        $series = [];
        foreach ($board['trend']['series'] ?? [] as $row) {
            $series[] = [
                'label' => (string) ($row['label'] ?? 'Reportes'),
                'color' => (string) ($row['color'] ?? '#94a3b8'),
                'values' => array_map(intval(...), $row['values'] ?? []),
            ];
        }
        $this->lines($slide, 'Tendencia', $labels, $series, 40, 100, 880, 390);
    }

    /** @param  array<string, mixed>  $board */
    private function rankSlide(Slide $slide, array $board, array $meta): void
    {
        $this->heading($slide, 'Delitos y reportes por instalación', $this->caption($meta));

        $crimes = $board['crimes'] ?? [];
        $colors = $this->spreadInks(array_map(strval(...), $crimes['colors'] ?? []));
        $this->coloredBars(
            $slide,
            'Delitos',
            $this->combine($crimes['labels'] ?? [], $crimes['values'] ?? []),
            array_map($this->argb(...), $colors),
            40,
            100,
            430,
            390,
            true,
        );

        $rows = array_slice($board['sites']['rows'] ?? [], 0, 8);
        $this->siteBars($slide, $rows, 486, 100, 434, 390);
    }

    private function riskSlides(PhpPresentation $ppt, array $meta): void
    {
        $rows = array_values($meta['risk'] ?? []);
        $chunks = $rows === [] ? [[]] : array_chunk($rows, 8);
        foreach ($chunks as $index => $chunk) {
            $slide = $this->slide($ppt);
            $page = count($chunks) > 1 ? ' · '.($index + 1).'/'.count($chunks) : '';
            $this->heading($slide, 'Riesgo por instalación'.$page, $this->caption($meta));
            if ($chunk === []) {
                $this->emptyChart($slide, 'Riesgo por instalación', 40, 110, 880, 360);

                continue;
            }
            $body = [];
            foreach ($chunk as $row) {
                $body[] = [
                    'cells' => [
                        Str::limit((string) $row['name'], 32),
                        Str::limit((string) ($row['comuna'] ?? ''), 22),
                        $this->int((int) ($row['reports'] ?? 0)),
                        number_format((float) ($row['score'] ?? 0), 1),
                        $this->bandLabel((string) ($row['band'] ?? 'BAJO')),
                    ],
                    'inks' => [self::INK, self::MUTED, self::INK, self::INK, $this->argb((string) ($row['color'] ?? '#14532d'))],
                ];
            }
            $this->grid($slide, ['Instalación', 'Comuna', 'Reportes', 'R final', 'Semáforo'], $body, [250, 180, 110, 110, 150]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function reportSlides(PhpPresentation $ppt, array $data, array $meta): void
    {
        $rows = array_values($data['reports'] ?? []);
        $chunks = $rows === [] ? [[]] : array_chunk($rows, 6);
        foreach ($chunks as $index => $chunk) {
            $slide = $this->slide($ppt);
            $page = count($chunks) > 1 ? ' · '.($index + 1).'/'.count($chunks) : '';
            $this->heading($slide, 'Reportes'.$page, $this->caption($meta));
            if ($chunk === []) {
                $this->emptyChart($slide, 'Reportes', 40, 110, 880, 360);

                continue;
            }
            $body = [];
            foreach ($chunk as $row) {
                $body[] = [
                    'cells' => [
                        (string) $row['at'],
                        (string) $row['folio'],
                        Str::limit((string) $row['site'], 22),
                        Str::limit((string) $row['origin'], 22),
                        Str::limit((string) $row['kind'], 16),
                        Str::limit((string) $row['excerpt'], 70),
                    ],
                    'inks' => array_fill(0, 6, self::INK),
                ];
            }
            $this->grid($slide, ['Fecha', 'Folio', 'Instalación', 'Origen', 'Delito', 'Extracto'], $body, [110, 100, 140, 130, 100, 300]);
        }
    }

    private function closingSlide(Slide $slide, array $meta, ?string $logo): void
    {
        $this->text($slide, 'Gracias', 0, 150, 960, 110, 80, self::INK, true, Alignment::HORIZONTAL_CENTER);
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
        $series->setDlblNumFormat('0;-0;;');
        $series->getFont()->setName('Calibri')->setSize(10)->setBold(true)->setColor(new Color(self::NAVY));
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
            $point = $this->barSeries((string) $row['label'], $values, $this->argb((string) $row['color']), false);
            $point->setShowValue(false);
            $line->addSeries($point);
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
            $series = $this->barSeries($crime, $values, $this->argb($colors[$crime]), false);
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
    private function grid(Slide $slide, array $headers, array $rows, array $widths): void
    {
        $table = $slide->createTableShape(count($headers));
        $table->setOffsetX(40)->setOffsetY(108);
        $this->tableRow($table->createRow(), $headers, $widths, self::SLATE, array_fill(0, count($headers), self::INK), true);
        foreach ($rows as $row) {
            $this->tableRow($table->createRow(), $row['cells'], $widths, self::CARD, $row['inks']);
        }
    }

    private function emptyChart(Slide $slide, string $title, int $x, int $y, int $width, int $height): void
    {
        $this->box($slide, $x, $y, $width, $height, self::CARD);
        $this->text($slide, $title, $x, $y + 10, $width, 24, 12, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, 'Sin registros en el periodo', $x, $y + (int) ($height / 2) - 12, $width, 24, 12, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
    }

    /** @param  array<string, int>  $values */
    private function barSeries(string $title, array $values, string $color, bool $showValues): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue($showValues);
        $series->setFill($this->solid($color));
        $series->setLabelPosition(Series::LABEL_OUTSIDEEND);
        $series->setDlblNumFormat('0;-0;;');
        $series->getFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::INK));

        return $series;
    }

    /**
     * @param  list<string>  $rows
     * @param  list<int>  $widths
     */
    private function tableRow(Row $row, array $values, array $widths, string $fill, string|array $ink, bool $header = false): void
    {
        $inks = is_array($ink) ? $ink : array_fill(0, count($values), $ink);
        $row->setHeight($header ? 24 : 32);
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
                $border->setColor(new Color(self::NAVY));
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
        $background->setColor(new Color(self::NAVY));
        $slide->setBackground($background);

        $this->box($slide, 0, 0, 960, 8, self::GOLD);
    }

    private function heading(Slide $slide, string $title, string $caption): void
    {
        $this->text($slide, $title, 40, 24, 880, 40, 22, self::INK, true);
        $this->text($slide, $caption, 52, 62, 868, 28, 11, self::MUTED);
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
