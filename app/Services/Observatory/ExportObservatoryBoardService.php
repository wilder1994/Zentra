<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use Illuminate\Support\Str;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Chart\Legend;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

final class ExportObservatoryBoardService
{
    private const NAVY = 'FF0F172A';

    private const CARD = 'FF1E293B';

    private const GOLD = 'FFFBBF24';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FFF8FAFC';

    private const MUTED = 'FF94A3B8';

    /**
     * @param  array<string, mixed>  $board
     * @param  array{scope: string, caption: string, from: ?string, to: ?string}  $meta
     * @return array{path: string, filename: string}
     */
    public function execute(array $board, array $meta): array
    {
        $ppt = new PhpPresentation;
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);
        $ppt->getDocumentProperties()
            ->setCreator('Zentra')
            ->setLastModifiedBy('Zentra')
            ->setTitle('Observatorio')
            ->setSubject($meta['caption'])
            ->setDescription('Salida del tablero del Observatorio');

        $this->coverSlide($ppt->getActiveSlide(), $board, $meta);
        $this->kpiSlide($this->slide($ppt), $board, $meta['caption']);
        $this->rankingSlide($this->slide($ppt), $board, $meta['caption']);
        $this->chartSlide($this->slide($ppt), 'Tendencia por tipo', $this->trendBar($board['trend'] ?? []));
        $this->chartSlide($this->slide($ppt), 'Días con más reportes', $this->singleBar(
            $this->zip($board['peaks']['labels'] ?? [], $board['peaks']['values'] ?? []),
            'Eventos',
            self::GOLD,
        ));
        $this->chartSlide($this->slide($ppt), 'Canal de origen', $this->singleBar(
            $this->zip($board['sources']['labels'] ?? [], $board['sources']['values'] ?? []),
            'Reportes',
            self::CYAN,
        ));

        return [
            'path' => $this->save($ppt),
            'filename' => $this->fileName($meta),
        ];
    }

    /**
     * @param  array{scope: string, caption: string, from: ?string, to: ?string}  $meta
     */
    public function caption(?string $from, ?string $to, string $grain, string $comunaLabel): string
    {
        $period = match (true) {
            filled($from) && filled($to) => $from.' — '.$to,
            filled($from) => 'Desde '.$from,
            filled($to) => 'Hasta '.$to,
            default => 'Periodo abierto',
        };
        $grainLabel = match ($grain) {
            'month' => 'por mes',
            'year' => 'por año',
            default => 'por día',
        };

        return $period.' · líneas '.$grainLabel.' · '.$comunaLabel;
    }

    public function fileName(array $meta): string
    {
        $from = $meta['from'] ?: now()->toDateString();
        $to = $meta['to'] ?: $from;

        return sprintf(
            'Observatorio_%s_%s_%s.pptx',
            Str::slug($meta['scope']) ?: 'tablero',
            $from,
            $to,
        );
    }

    /**
     * @param  array<string, mixed>  $board
     * @param  array{scope: string, caption: string, from: ?string, to: ?string}  $meta
     */
    private function coverSlide(Slide $slide, array $board, array $meta): void
    {
        $this->paintSlide($slide);
        $this->text($slide, 'ZENTRA', 40, 140, 880, 28, 14, self::GOLD, true);
        $this->text($slide, 'Observatorio escolar', 40, 176, 880, 48, 28, self::INK, true);
        $this->text($slide, $meta['scope'], 40, 236, 880, 32, 16, self::CYAN);
        $this->text($slide, $meta['caption'], 40, 280, 880, 28, 14, self::MUTED);
        $this->text(
            $slide,
            ((int) ($board['total'] ?? 0)).' eventos · atención '.((int) ($board['load_rate'] ?? 0)).'% · '.((int) ($board['cerrado'] ?? 0)).' cerrados',
            40,
            330,
            880,
            28,
            14,
            self::INK,
        );
        $this->text($slide, 'Generado el '.now()->format('d/m/Y H:i'), 40, 460, 880, 24, 12, self::MUTED);
    }

    /**
     * @param  array<string, mixed>  $board
     */
    private function kpiSlide(Slide $slide, array $board, string $caption): void
    {
        $this->heading($slide, 'Cifras del periodo', $caption);

        $boxes = [
            [(string) ((int) ($board['total'] ?? 0)), 'Eventos', self::CARD, self::INK],
            [(string) ((int) ($board['nuevo'] ?? 0)), 'Nuevos', self::CARD, 'FFF59E0B'],
            [(string) ((int) ($board['en_atencion'] ?? 0)), 'En atención', self::CARD, 'FF818CF8'],
            [(string) ((int) ($board['cerrado'] ?? 0)), 'Cerrados', self::CARD, 'FF34D399'],
        ];

        foreach ($boxes as $index => [$value, $label, $boxFill, $boxInk]) {
            $shape = $slide->createRichTextShape()
                ->setOffsetX(40 + ($index * 230))
                ->setOffsetY(120)
                ->setWidth(214)
                ->setHeight(130);
            $shape->setFill($this->solid($boxFill));
            $valueRun = $shape->createTextRun($value);
            $valueRun->getFont()->setName('Calibri')->setBold(true)->setSize(28)->setColor(new Color($boxInk));
            $shape->createBreak();
            $labelRun = $shape->createTextRun(mb_strtoupper($label));
            $labelRun->getFont()->setName('Calibri')->setSize(11)->setColor(new Color(self::MUTED));
        }

        $this->text(
            $slide,
            'Atención: '.((int) ($board['load_rate'] ?? 0)).'% (nuevo 1 · trámite 0,4 · cerrado 0). Cerrados: '.((int) ($board['cerrado'] ?? 0)).'. Solo cifras; sin nombres ni teléfonos.',
            40,
            280,
            880,
            50,
            14,
            self::MUTED,
        );
    }

    /**
     * @param  array<string, mixed>  $board
     */
    private function rankingSlide(Slide $slide, array $board, string $caption): void
    {
        $this->heading($slide, 'Instalaciones por riesgo', $caption);

        $rows = $board['top'] ?? [];
        if ($rows === []) {
            $this->text($slide, 'Aún no hay eventos en el periodo.', 40, 120, 880, 40, 16, self::MUTED);

            return;
        }

        $shape = $slide->createRichTextShape()
            ->setOffsetX(40)
            ->setOffsetY(110)
            ->setWidth(880)
            ->setHeight(390);
        $shape->setFill($this->solid(self::CARD));

        foreach ($rows as $index => $row) {
            if ($index > 0) {
                $shape->createBreak();
            }
            $line = ($row['name'] ?? '—')
                .(filled($row['client'] ?? null) ? ' · '.$row['client'] : '')
                .(filled($row['comuna_name'] ?? null) ? ' · '.$row['comuna_name'] : '')
                .'  —  puntaje '.((int) ($row['score'] ?? $row['count'] ?? 0))
                .' / '.((int) ($row['count'] ?? 0)).' eventos';
            $run = $shape->createTextRun($line);
            $run->getFont()->setName('Calibri')->setSize(14)->setColor(new Color(self::INK));
        }
    }

    /**
     * @param  array{labels?: list<string>, series?: list<array{label: string, color?: string, values: list<int>}>}  $trend
     */
    private function trendBar(array $trend): Bar
    {
        $labels = $trend['labels'] ?? [];
        $series = $trend['series'] ?? [];
        $bar = new Bar;
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);

        if ($series === []) {
            $bar->addSeries($this->series('Reportes', ['Sin datos' => 0], self::CYAN));

            return $bar;
        }

        foreach ($series as $row) {
            $bar->addSeries($this->series(
                (string) ($row['label'] ?? 'Tipo'),
                $this->zip($labels, $row['values'] ?? []),
                $this->argb((string) ($row['color'] ?? '')),
            ));
        }

        return $bar;
    }

    /**
     * @param  array<string, int>  $values
     */
    private function singleBar(array $values, string $title, string $color): Bar
    {
        $bar = new Bar;
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);
        $bar->addSeries($this->series($title, $values, $color));

        return $bar;
    }

    private function chartSlide(Slide $slide, string $title, Bar $type): void
    {
        $this->heading($slide, $title, 'Gráfico nativo editable en PowerPoint');

        $chart = $slide->createChartShape();
        $chart->setResizeProportional(false)
            ->setIncludeSpreadsheet(true)
            ->setOffsetX(40)
            ->setOffsetY(100)
            ->setWidth(880)
            ->setHeight(400);
        $chart->getTitle()->setVisible(false);
        $chart->getLegend()->setPosition(Legend::POSITION_BOTTOM);
        $chart->getPlotArea()->setType($type);
    }

    /**
     * @param  list<mixed>  $labels
     * @param  list<mixed>  $values
     * @return array<string, int>
     */
    private function zip(array $labels, array $values): array
    {
        $mapped = [];
        foreach ($labels as $index => $label) {
            $name = trim((string) $label);
            if ($name === '') {
                continue;
            }
            $mapped[$name] = (int) ($values[$index] ?? 0);
        }

        return $mapped;
    }

    /**
     * @param  array<string, int>  $values
     */
    private function series(string $title, array $values, string $color): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowValue(true);
        $series->setFill($this->solid($color));
        $series->getFont()->setName('Calibri')->setSize(9)->setColor(new Color(self::NAVY));

        return $series;
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
            $mapped[$label] = (string) $value;
        }

        return $mapped;
    }

    private function argb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1) {
            return 'FF'.strtoupper($hex);
        }

        return self::CYAN;
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

        $bar = $slide->createRichTextShape()
            ->setOffsetX(0)
            ->setOffsetY(0)
            ->setWidth(960)
            ->setHeight(8);
        $bar->setFill($this->solid(self::GOLD));
    }

    private function heading(Slide $slide, string $title, string $caption): void
    {
        $this->text($slide, $title, 40, 24, 880, 40, 22, self::INK, true);
        $this->text($slide, $caption, 40, 64, 880, 28, 12, self::MUTED);
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
    ): void {
        $shape = $slide->createRichTextShape()
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($width)
            ->setHeight($height);
        $run = $shape->createTextRun($content);
        $run->getFont()
            ->setName('Calibri')
            ->setSize($size)
            ->setBold($bold)
            ->setColor(new Color($color));
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
