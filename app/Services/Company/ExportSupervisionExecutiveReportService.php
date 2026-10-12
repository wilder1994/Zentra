<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionPeriodSnapshot;
use Illuminate\Support\Str;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Chart\Legend;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\Table\Row;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

final class ExportSupervisionExecutiveReportService
{
    private const NAVY = 'FF0F172A';

    private const CARD = 'FF1E293B';

    private const GOLD = 'FFFBBF24';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FFF8FAFC';

    private const MUTED = 'FF94A3B8';

    private const ROW_RED = 'FF7F1D1D';

    private const ROW_AMBER = 'FF78350F';

    private const ROW_GREEN = 'FF064E3B';

    /**
     * @return array{path: string, filename: string}
     */
    public function execute(SupervisionPeriodSnapshot $snapshot): array
    {
        $ppt = new PhpPresentation;
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);
        $ppt->getDocumentProperties()
            ->setCreator('Zentra')
            ->setLastModifiedBy('Zentra')
            ->setTitle('Informe de Supervisión')
            ->setSubject($snapshot->caption)
            ->setDescription('Informe gerencial de Supervisión de campo');

        $this->coverSlide($ppt->getActiveSlide(), $snapshot);
        $this->kpiSlide($this->slide($ppt), $snapshot);
        $this->activitySlide($this->slide($ppt), $snapshot);
        $this->fieldSlide($this->slide($ppt), $snapshot);
        $this->chartSlide(
            $this->slide($ppt),
            'Actividad por supervisor',
            $this->groupedBar(
                $this->namedValues($snapshot->bySupervisor, 'reviews'),
                $this->namedValues($snapshot->bySupervisor, 'km'),
                'Revistas',
                'Km',
            ),
        );
        $this->alarmsSlide($this->slide($ppt), $snapshot);
        $this->clientSlides($ppt, $snapshot);
        if ($snapshot->alerts !== []) {
            $this->alertsSlide($this->slide($ppt), $snapshot->alerts);
        }

        return [
            'path' => $this->save($ppt),
            'filename' => $this->fileName($snapshot),
        ];
    }

    public function fileName(SupervisionPeriodSnapshot $snapshot): string
    {
        $empresa = Str::slug($snapshot->companyName) ?: 'empresa';

        return sprintf(
            'Informe_Supervision_%s_%s_%s.pptx',
            $empresa,
            $snapshot->from,
            $snapshot->to,
        );
    }

    private function coverSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $this->paintSlide($slide);
        $this->text($slide, 'ZENTRA', 40, 140, 880, 28, 14, self::GOLD, true);
        $this->text($slide, 'Informe gerencial de Supervisión', 40, 176, 880, 48, 28, self::INK, true);
        $this->text($slide, $snapshot->companyName, 40, 236, 880, 32, 16, self::CYAN);
        $this->text($slide, $snapshot->caption, 40, 280, 880, 28, 14, self::MUTED);
        $this->text($slide, 'Generado el '.now()->format('d/m/Y H:i'), 40, 460, 880, 24, 12, self::MUTED);
    }

    private function kpiSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $this->heading($slide, 'Cobertura y semáforo', $snapshot->caption);

        $coverage = $snapshot->coveragePercent !== null
            ? number_format($snapshot->coveragePercent, 1).'%'
            : '—';
        $fill = match ($snapshot->semaphore) {
            'green' => 'FF34D399',
            'yellow' => 'FFFBBF24',
            'red' => 'FFF87171',
            default => self::CARD,
        };
        $ink = $snapshot->semaphore === 'neutral' ? self::INK : self::NAVY;

        $boxes = [
            [$coverage, 'Cumplimiento por puesto', $snapshot->sitesVisited.' de '.$snapshot->sitesContracted.' puesto-día', $fill, $ink],
            [$snapshot->postsDone.' de '.$snapshot->postsTotal, 'Puestos cumplidos', ($snapshot->postsTotal - $snapshot->postsDone).' con pendientes', self::CARD, self::INK],
            [(string) $snapshot->reviews, 'Revistas', $snapshot->reviewsWithNovelty.' con novedad', self::CARD, self::INK],
            [(string) $snapshot->kmTraveled, 'Km recorridos', '', self::CARD, self::GOLD],
            [
                (string) $snapshot->recommendations['total'],
                'Recomendaciones',
                'B '.$snapshot->recommendations['low'].' · M '.$snapshot->recommendations['medium']
                    .' · A '.$snapshot->recommendations['high'].' · E '.$snapshot->recommendations['extreme'],
                self::CARD,
                self::INK,
            ],
        ];

        foreach ($boxes as $index => [$value, $label, $detail, $boxFill, $boxInk]) {
            $shape = $slide->createRichTextShape()
                ->setOffsetX(40 + ($index * 178))
                ->setOffsetY(120)
                ->setWidth(168)
                ->setHeight(130);
            $shape->setFill($this->solid($boxFill));
            $valueRun = $shape->createTextRun($value);
            $valueRun->getFont()->setName('Calibri')->setBold(true)->setSize(20)->setColor(new Color($boxInk));
            $shape->createBreak();
            $labelRun = $shape->createTextRun(mb_strtoupper($label));
            $labelRun->getFont()->setName('Calibri')->setSize(9)->setColor(new Color($boxInk));
            if ($detail !== '') {
                $shape->createBreak();
                $detailRun = $shape->createTextRun($detail);
                $detailRun->getFont()->setName('Calibri')->setSize(9)->setColor(new Color($boxInk === self::NAVY ? self::NAVY : self::MUTED));
            }
        }

        $detail = $snapshot->sitesContracted > 0
            ? 'Revistas cumplidas por puesto y día operativo (06:00–06:00): '.$snapshot->sitesVisited.' de '.$snapshot->sitesContracted.'. Umbral verde ≥ 90 %, amarillo ≥ 70 %.'
            : 'No hay puestos con servicio programado en este periodo.';
        $this->text($slide, $detail, 40, 280, 880, 50, 14, self::MUTED);
        $this->text(
            $slide,
            'Revistas de portería (código Accesos) no entran en este informe. Solo captura de campo.',
            40,
            340,
            880,
            40,
            12,
            self::MUTED,
        );
    }

    private function activitySlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $grain = ($charts['grain'] ?? 'day') === 'month' ? 'mensual' : 'diaria';
        $this->heading($slide, 'Actividad '.$grain, $snapshot->caption);

        $reviews = array_sum($charts['reviews'] ?? []);
        $novelty = array_sum($charts['novelty_yes'] ?? []);
        $this->text($slide, 'Revistas '.$reviews.' · con novedad '.$novelty.' · km '.$snapshot->kmTraveled.'.', 40, 110, 880, 40, 14, self::CYAN);

        $weapons = $charts['weapons'] ?? [];
        $this->text(
            $slide,
            'Armamento: '.(int) ($weapons['total'] ?? 0).' inspecciones, '
            .(int) ($weapons['cleaned'] ?? 0).' con aseo, '
            .(int) ($weapons['inspection_only'] ?? 0).' solo revista, '
            .(int) ($weapons['novelty'] ?? 0).' con novedad, '
            .(int) ($weapons['expired'] ?? 0).' permiso vencido.',
            40,
            170,
            880,
            70,
            14,
            self::INK,
        );

        $recs = $charts['recs_by_level'] ?? [];
        $this->text(
            $slide,
            'Recomendaciones '.$snapshot->recommendations['total']
            .' — bajo '.(int) ($recs['low'] ?? 0)
            .', medio '.(int) ($recs['medium'] ?? 0)
            .', alto '.(int) ($recs['high'] ?? 0)
            .', extremo '.(int) ($recs['extreme'] ?? 0).'.',
            40,
            260,
            880,
            50,
            14,
            self::GOLD,
        );
    }

    private function fieldSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $this->heading($slide, 'Puesto: inventario, libros y carpetas', $snapshot->caption);

        $inv = $charts['inventory'] ?? [];
        $books = $charts['books'] ?? [];
        $folders = $charts['folders'] ?? [];
        $docs = $charts['documents'] ?? [];

        $this->text($slide, 'Inventario — bueno '.(int) ($inv['good'] ?? 0).', regular '.(int) ($inv['regular'] ?? 0).', malo '.(int) ($inv['bad'] ?? 0).'.', 40, 110, 880, 40, 14, self::INK);
        $this->text($slide, 'Libros — sin novedad '.(int) ($books['no'] ?? 0).', con novedad '.(int) ($books['yes'] ?? 0).'.', 40, 160, 880, 40, 14, self::INK);
        $this->text($slide, 'Carpetas — completas '.(int) ($folders['complete'] ?? 0).', con faltantes '.(int) ($folders['missing'] ?? 0).'.', 40, 210, 880, 40, 14, self::INK);
        $this->text($slide, 'Documentos del turno — entregados '.(int) ($docs['delivered'] ?? 0).', pendientes '.(int) ($docs['pending'] ?? 0).'.', 40, 260, 880, 40, 14, self::CYAN);
    }

    private function alarmsSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $kind = $charts['alarms_kind'] ?? [];
        $result = $charts['alarms_result'] ?? [];
        $place = $charts['supports_place'] ?? [];
        $this->heading($slide, 'Alarmas y apoyos', $snapshot->caption);

        $this->text(
            $slide,
            'Alarmas: '.(int) ($kind['test'] ?? 0).' pruebas y '.(int) ($kind['response'] ?? 0).' atenciones. '
            .'OK '.(int) ($result['ok'] ?? 0)
            .', falla '.(int) ($result['fail'] ?? 0)
            .', real '.(int) ($result['real'] ?? 0)
            .', falsa '.(int) ($result['false_alarm'] ?? 0)
            .', no ubicada '.(int) ($result['not_found'] ?? 0).'.',
            40,
            110,
            880,
            80,
            14,
            self::INK,
        );

        $types = [];
        foreach (array_slice($charts['alarms_by_type'] ?? [], 0, 8) as $row) {
            $types[] = $row['name'].' (prueba '.$row['test'].', atención '.$row['response'].')';
        }
        $this->text($slide, $types === [] ? 'Sin alarmas en el periodo.' : implode(' · ', $types), 40, 210, 880, 80, 13, self::CYAN);

        $supports = [];
        foreach (array_slice($charts['supports_by_type'] ?? [], 0, 8) as $row) {
            $supports[] = $row['name'].' ('.$row['total'].')';
        }
        $this->text(
            $slide,
            ($supports === [] ? 'Sin apoyos.' : 'Apoyos: '.implode(', ', $supports))
            .' · '.(int) ($place['site'] ?? 0).' en sitio, '.(int) ($place['road'] ?? 0).' en vía.',
            40,
            310,
            880,
            80,
            13,
            self::GOLD,
        );
    }

    /** Tabla de clientes con semáforo (rojo → ámbar → verde), 14 filas por diapositiva. */
    private function clientSlides(PhpPresentation $ppt, SupervisionPeriodSnapshot $snapshot): void
    {
        $rows = $snapshot->byClient;
        $pages = $rows === [] ? [[]] : array_chunk($rows, 14);
        $total = count($pages);

        foreach ($pages as $page => $chunk) {
            $slide = $this->slide($ppt);
            $title = 'Cumplimiento por cliente'.($total > 1 ? ' ('.($page + 1).'/'.$total.')' : '');
            $this->heading($slide, $title, $snapshot->caption.' · rojo sin revista · ámbar con pendientes · verde cumplido');

            if ($chunk === []) {
                $this->text($slide, 'No hay puestos con servicio programado en este periodo.', 40, 110, 880, 40, 14, self::MUTED);

                continue;
            }

            $widths = [340, 100, 80, 90, 90, 90, 90];
            $table = $slide->createTableShape(count($widths));
            $table->setOffsetX(40)->setOffsetY(100)->setWidth(array_sum($widths));

            $this->tableRow($table->createRow(), ['Cliente', 'Instalaciones', 'Puestos', 'Cumplidos', 'Revistas', 'Novedad', '%'], $widths, self::CARD, self::MUTED, true);
            foreach ($chunk as $row) {
                $fill = match ($row['status']) {
                    'red' => self::ROW_RED,
                    'amber' => self::ROW_AMBER,
                    default => self::ROW_GREEN,
                };
                $this->tableRow($table->createRow(), [
                    $row['name'],
                    (string) $row['installations'],
                    (string) $row['posts'],
                    (string) $row['posts_done'],
                    (string) $row['reviews'],
                    (string) $row['novelty'],
                    $row['percent'] !== null ? number_format($row['percent'], 0).'%' : '—',
                ], $widths, $fill, self::INK);
            }
        }
    }

    /**
     * @param  list<string>  $values
     * @param  list<int>  $widths
     */
    private function tableRow(Row $row, array $values, array $widths, string $fill, string $ink, bool $header = false): void
    {
        $row->setHeight(24);
        foreach ($values as $index => $value) {
            $cell = $row->nextCell();
            $cell->setWidth($widths[$index]);
            $cell->setFill($this->solid($fill));
            $cell->getActiveParagraph()->getAlignment()->setHorizontal($index === 0 ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_RIGHT);
            $run = $cell->createTextRun($value);
            $run->getFont()->setName('Calibri')->setSize($header ? 9 : 10)->setBold($header)->setColor(new Color($ink));
            foreach ([$cell->getBorders()->getTop(), $cell->getBorders()->getBottom(), $cell->getBorders()->getLeft(), $cell->getBorders()->getRight()] as $border) {
                $border->setColor(new Color(self::NAVY));
            }
        }
    }

    /**
     * @param  list<array{level: string, text: string}>  $alerts
     */
    private function alertsSlide(Slide $slide, array $alerts): void
    {
        $this->heading($slide, 'Atención', 'Alarmas, recomendaciones extremas, turnos largos y documentos pendientes');

        $shape = $slide->createRichTextShape()
            ->setOffsetX(40)
            ->setOffsetY(110)
            ->setWidth(880)
            ->setHeight(380);
        $shape->setFill($this->solid(self::CARD));

        foreach ($alerts as $index => $alert) {
            if ($index > 0) {
                $shape->createBreak();
                $shape->createBreak();
            }
            $run = $shape->createTextRun('•  '.$alert['text']);
            $run->getFont()->setName('Calibri')->setSize(15)
                ->setColor(new Color($alert['level'] === 'red' ? 'FFF87171' : self::GOLD));
        }
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
     * @param  array<string, int>  $executed
     * @param  array<string, int>  $target
     */
    private function groupedBar(array $executed, array $target, string $executedTitle, string $targetTitle): Bar
    {
        $bar = new Bar;
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setBarGrouping(Bar::GROUPING_CLUSTERED);
        $bar->addSeries($this->series($executedTitle, $executed, self::CYAN));
        $bar->addSeries($this->series($targetTitle, $target, self::GOLD));

        return $bar;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function namedValues(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $values[$name] = (int) ($row[$key] ?? 0);
        }

        return $values;
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
        $path = tempnam(sys_get_temp_dir(), 'zentra_sup_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del informe.');
        }

        unlink($path);
        $path .= '.pptx';
        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);

        return $path;
    }
}
