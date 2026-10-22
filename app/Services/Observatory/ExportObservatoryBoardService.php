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

    private const GOLD = 'FFFBBF24';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FFF8FAFC';

    private const MUTED = 'FF94A3B8';

    private const GREEN = 'FF34D399';

    private const AMBER = 'FFF59E0B';

    private const INDIGO = 'FF818CF8';

    private const RED = 'FFF87171';

    private const WHITE = 'FFFFFFFF';

    private const ROW_RED = 'FF7F1D1D';

    private const ROW_AMBER = 'FF78350F';

    private const ROW_GREEN = 'FF064E3B';

    private const ROWS_PER_SLIDE = 12;

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
            foreach ($data['by_type'] as $type) {
                $this->typeSlide($this->slide($ppt), $type, $board, (int) $board['total'], $meta);
            }
            $this->rankingSlides($ppt, $data['ranking'], $meta);
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
        $this->text($slide, $meta['scope_kind'].' · '.$this->periodLabel($meta['from'], $meta['to']), 60, 332, 840, 26, 15, self::MUTED);
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
        $this->heading($slide, 'Resumen del periodo', $this->caption($meta));

        $total = (int) $board['total'];
        $load = (int) ($board['load_rate'] ?? 0);
        $loadFill = $total === 0 ? self::CARD : match (true) {
            $load <= 33 => self::GREEN,
            $load <= 66 => self::GOLD,
            default => self::RED,
        };
        $loadInk = $total === 0 ? self::INK : self::NAVY;

        $boxes = [
            [$this->int($total), 'Folios', $this->int((int) array_sum($board['kinds']['values'] ?? [])).' reportes', self::CARD, self::INK],
            [$this->int((int) $board['nuevo']), 'Nuevos', 'sin atender aún', self::CARD, self::AMBER],
            [$this->int((int) $board['en_atencion']), 'En atención', 'en seguimiento', self::CARD, self::INDIGO],
            [$this->int((int) $board['cerrado']), 'Cerrados', $this->pct((int) $board['cerrado'], $total).' del total', self::CARD, self::GREEN],
            [$load.' %', 'Carga pendiente', $total === 0 ? 'sin folios' : ($load <= 33 ? 'baja' : ($load <= 66 ? 'media' : 'alta')), $loadFill, $loadInk],
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

        $this->donut($slide, 'Estado de los folios', [
            'Nuevos' => (int) $board['nuevo'],
            'En atención' => (int) $board['en_atencion'],
            'Cerrados' => (int) $board['cerrado'],
        ], [self::AMBER, self::INDIGO, self::GREEN], 40, 208, 284, 206);

        $kinds = $this->combine($board['kinds']['labels'] ?? [], $board['kinds']['values'] ?? []);
        $kindColors = array_map(fn ($c): string => $this->argb((string) $c), $board['kinds']['colors'] ?? []);
        $this->coloredBars($slide, 'Reportes por tipo', $kinds, $kindColors, 338, 208, 284, 206);

        $sources = array_filter($this->combine($board['sources']['labels'] ?? [], $board['sources']['values'] ?? []));
        $this->coloredBars($slide, 'Canal de origen', $sources, [self::CYAN], 636, 208, 284, 206);

        $this->paragraphs($slide, [[$this->summaryNarrative($board, $kinds, $sources), self::INK]], 40, 424, 880, 96, 12);
    }

    /**
     * @param  array<string, mixed>  $board
     * @param  array<string, int>  $kinds
     * @param  array<string, int>  $sources
     */
    private function summaryNarrative(array $board, array $kinds, array $sources): string
    {
        $total = (int) $board['total'];
        if ($total === 0) {
            return 'No hubo folios con los filtros y el periodo elegidos.';
        }

        $text = 'En el periodo se abrieron '.$this->count($total, 'folio', 'folios').': '
            .$this->int((int) $board['nuevo']).' nuevos, '.$this->int((int) $board['en_atencion']).' en atención y '
            .$this->int((int) $board['cerrado']).' cerrados ('.$this->pct((int) $board['cerrado'], $total).').';
        $text .= ' La carga pendiente es de '.((int) $board['load_rate']).' % (un folio nuevo pesa 1 y uno en atención 0,4).';

        arsort($kinds);
        if (($top = array_key_first($kinds)) !== null && $kinds[$top] > 0) {
            $text .= ' El tipo con más reportes fue '.$top.' ('.$this->int($kinds[$top]).').';
        }

        $peaks = array_filter($this->combine($board['peaks']['labels'] ?? [], $board['peaks']['values'] ?? []));
        if ($peaks !== []) {
            $items = [];
            foreach (array_slice($peaks, 0, 3, true) as $day => $value) {
                $items[] = $day.' ('.$this->int($value).')';
            }
            $text .= ' Días con más reportes: '.implode(', ', $items).'.';
        }

        arsort($sources);
        if (($channel = array_key_first($sources)) !== null) {
            $text .= ' La mayoría llegó por '.mb_strtolower($channel).' ('.$this->int($sources[$channel]).').';
        }

        return $text;
    }

    /** @param  array<string, mixed>  $data */
    private function heatMapSlide(Slide $slide, array $data, array $meta): void
    {
        $this->heading($slide, 'Mapa de calor de los folios', $this->caption($meta));

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
        $note .= '. Cada mancha es una instalación: más grande y más intensa = más folios; el color es el tipo predominante.';
        if ($legend !== []) {
            $note .= ' Por tipo: '.implode(' · ', $legend).'.';
        }
        $this->paragraphs($slide, [[$note, self::INK]], 40, 452, 880, 68, 11);
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

    /**
     * @param  array{name: string, color: string, level: int, events: int, reports: int, status: array<string, int>, sites: array<string, int>}  $type
     * @param  array<string, mixed>  $board
     */
    private function typeSlide(Slide $slide, array $type, array $board, int $total, array $meta): void
    {
        $this->heading($slide, $type['name'].' · nivel '.$type['level'], $this->caption($meta));
        $this->box($slide, 40, 66, 6, 22, $this->argb($type['color']));

        $monthly = ($board['grain'] ?? 'day') !== 'day';
        $labels = array_values($board['trend']['labels'] ?? []);
        $series = collect($board['trend']['series'] ?? [])->firstWhere('label', $type['name']);
        $values = $this->combine($labels, $series['values'] ?? []);

        if ($type['events'] === 0) {
            $this->box($slide, 40, 100, 880, 285, self::CARD);
            $this->text($slide, 'Sin folios de '.$type['name'].' con los filtros y el periodo elegidos.', 40, 225, 880, 30, 14, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
            $this->paragraphs($slide, [['Este tipo no tuvo reportes en el periodo. Si se esperaban casos, revisa los filtros del tablero (estado, comuna o instalación).', self::MUTED]], 40, 398, 880, 70, 12);

            return;
        }

        $bar = new Bar;
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);
        $bar->setGapWidthPercent(60);
        $bar->addSeries($this->barSeries('Reportes', $values, $this->argb($type['color']), count($values) <= 16));
        $this->chart($slide, $bar, 40, 100, 520, 285, $monthly ? 'Reportes por mes' : 'Reportes por día', false);

        $this->donut($slide, 'Estado de los folios', [
            'Nuevos' => (int) ($type['status']['nuevo'] ?? 0),
            'En atención' => (int) ($type['status']['en_atencion'] ?? 0),
            'Cerrados' => (int) ($type['status']['cerrado'] ?? 0),
        ], [self::AMBER, self::INDIGO, self::GREEN], 574, 100, 346, 285);

        $this->paragraphs($slide, [[$this->typeNarrative($type, $values, $total, $monthly), self::INK]], 40, 398, 880, 122, 12);
    }

    /**
     * @param  array{name: string, events: int, reports: int, status: array<string, int>, sites: array<string, int>}  $type
     * @param  array<string, int>  $values
     */
    private function typeNarrative(array $type, array $values, int $total, bool $monthly): string
    {
        $text = 'Hubo '.$this->count($type['events'], 'folio', 'folios').' de '.$type['name']
            .' ('.$this->pct($type['events'], $total).' del total) con '.$this->count($type['reports'], 'reporte', 'reportes').': '
            .$this->int((int) ($type['status']['nuevo'] ?? 0)).' nuevos, '
            .$this->int((int) ($type['status']['en_atencion'] ?? 0)).' en atención y '
            .$this->int((int) ($type['status']['cerrado'] ?? 0)).' cerrados.';

        if ($values !== [] && max($values) > 0) {
            $peak = array_keys($values, max($values))[0];
            $text .= ' El '.($monthly ? 'mes' : 'día').' con más reportes fue '.$peak.' ('.$this->int($values[$peak]).').';
        }

        $sites = array_slice($type['sites'], 0, 3, true);
        if ($sites !== []) {
            $items = [];
            foreach ($sites as $name => $count) {
                $items[] = $name.' ('.$this->int($count).')';
            }
            $text .= ' Instalaciones con más folios: '.implode(', ', $items).'.';
        }

        return $text;
    }

    /** @param  list<array{name: string, client: ?string, comuna: ?string, count: int, score: int, top_type: string}>  $rows */
    private function rankingSlides(PhpPresentation $ppt, array $rows, array $meta): void
    {
        $pages = $rows === [] ? [[]] : array_chunk(array_slice($rows, 0, self::ROWS_PER_SLIDE * 3), self::ROWS_PER_SLIDE);
        $max = $rows === [] ? 0 : max(array_column($rows, 'score'));
        $total = count($pages);

        foreach ($pages as $page => $chunk) {
            $slide = $this->slide($ppt);
            $this->heading(
                $slide,
                'Instalaciones por riesgo'.($total > 1 ? ' ('.($page + 1).'/'.$total.')' : ''),
                'Puntaje = suma de los niveles de sus reportes · rojo alto · ámbar medio · verde bajo',
            );

            if ($chunk === []) {
                $this->text($slide, 'No hay instalaciones con folios en el periodo.', 40, 110, 880, 40, 14, self::MUTED);

                continue;
            }

            $widths = [40, 290, 200, 110, 70, 70, 100];
            $table = $slide->createTableShape(count($widths));
            $table->setOffsetX(40)->setOffsetY(100)->setWidth(array_sum($widths));
            $this->tableRow($table->createRow(), ['#', 'Instalación', 'Cliente', 'Comuna', 'Folios', 'Puntaje', 'Tipo principal'], $widths, self::CARD, self::MUTED, true);

            foreach ($chunk as $index => $row) {
                $ratio = $max > 0 ? $row['score'] / $max : 0;
                $fill = match (true) {
                    $ratio >= 0.66 => self::ROW_RED,
                    $ratio >= 0.33 => self::ROW_AMBER,
                    default => self::ROW_GREEN,
                };
                $this->tableRow($table->createRow(), [
                    (string) ($page * self::ROWS_PER_SLIDE + $index + 1),
                    Str::limit($row['name'], 38),
                    Str::limit((string) ($row['client'] ?? '—'), 22),
                    (string) ($row['comuna'] ?? '—'),
                    $this->int($row['count']),
                    $this->int($row['score']),
                    $row['top_type'],
                ], $widths, $fill, self::INK);
            }

            if ($page === 0) {
                $tableBottom = 100 + 26 * (count($chunk) + 1);
                if (count($chunk) <= 6) {
                    $scores = [];
                    foreach (array_slice($chunk, 0, 6) as $row) {
                        $scores[Str::limit($row['name'], 22)] = $row['score'];
                    }
                    $this->coloredBars($slide, 'Puntaje por instalación', $scores, [self::RED], 40, $tableBottom + 14, 880, 384 - $tableBottom - 14);
                }
                $this->paragraphs($slide, [[$this->rankingNarrative($rows), self::INK]], 40, 398, 880, 122, 12);
            }
        }
    }

    /** @param  list<array{name: string, count: int, score: int, top_type: string}>  $rows */
    private function rankingNarrative(array $rows): string
    {
        $first = $rows[0];
        $totalScore = array_sum(array_column($rows, 'score'));
        $text = $this->count(count($rows), 'instalación tuvo', 'instalaciones tuvieron').' folios en el periodo. '
            .'La de mayor riesgo es '.$first['name'].' con '.$this->int($first['score']).' puntos y '
            .$this->count($first['count'], 'folio', 'folios').', sobre todo de '.$first['top_type'].'.';

        if (count($rows) > 1 && $totalScore > 0) {
            $topTwo = $rows[0]['score'] + $rows[1]['score'];
            $text .= ' Las dos primeras concentran el '.$this->pct($topTwo, $totalScore).' del puntaje.';
        }
        $text .= ' El puntaje suma el nivel de cada reporte (nivel 3 pesa más que nivel 1), así que una instalación con pocos folios graves puede quedar arriba de otra con muchos leves.';

        return $text;
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
    private function coloredBars(Slide $slide, string $title, array $values, array $colors, int $x, int $y, int $width, int $height): void
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
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);
        $bar->setGapWidthPercent(70);
        $bar->addSeries($series);

        $this->chart($slide, $bar, $x, $y, $width, $height, $title, false);
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
    private function tableRow(Row $row, array $values, array $widths, string $fill, string $ink, bool $header = false): void
    {
        $row->setHeight(26);
        foreach ($values as $index => $value) {
            $cell = $row->nextCell();
            $cell->setWidth($widths[$index]);
            $cell->setFill($this->solid($fill));
            $cell->getActiveParagraph()->getAlignment()
                ->setHorizontal(in_array($index, [1, 2, 3, 6], true) ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER)
                ->setMarginLeft(6)
                ->setMarginRight(6);
            $run = $cell->createTextRun($value);
            $run->getFont()->setName('Calibri')->setSize($header ? 9 : 10)->setBold($header || $index === 5)->setColor(new Color($ink));
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

    private function pct(int $part, int $total): string
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
