<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionPeriodSnapshot;
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

final class ExportSupervisionExecutiveReportService
{
    private const NAVY = 'FF0F172A';

    private const CARD = 'FF1E293B';

    private const SLATE = 'FF334155';

    private const GOLD = 'FFFBBF24';

    private const CYAN = 'FF38BDF8';

    private const INK = 'FFF8FAFC';

    private const MUTED = 'FF94A3B8';

    private const GREEN = 'FF34D399';

    private const RED = 'FFF87171';

    private const ORANGE = 'FFFB923C';

    private const WHITE = 'FFFFFFFF';

    private const ROW_RED = 'FF7F1D1D';

    private const ROW_AMBER = 'FF78350F';

    private const ROW_GREEN = 'FF064E3B';

    private const CHART_Y = 100;

    private const CHART_H = 285;

    private const TEXT_Y = 398;

    private const TEXT_H = 124;

    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * @return array{path: string, filename: string}
     */
    public function execute(SupervisionPeriodSnapshot $snapshot, ?string $companyLogoPath = null): array
    {
        $this->tempFiles = [];

        $ppt = new PhpPresentation;
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);
        $ppt->getDocumentProperties()
            ->setCreator('Zentra')
            ->setLastModifiedBy('Zentra')
            ->setTitle('Informe de Supervisión')
            ->setSubject($snapshot->caption)
            ->setDescription('Informe gerencial de Supervisión de campo');

        try {
            $companyLogo = $this->companyLogo($companyLogoPath);

            $this->coverSlide($ppt->getActiveSlide(), $snapshot, $companyLogo);
            $this->kpiSlide($this->slide($ppt), $snapshot);
            $this->activitySlide($this->slide($ppt), $snapshot);
            $this->fieldSlide($this->slide($ppt), $snapshot);
            $this->weaponsSlide($this->slide($ppt), $snapshot);
            $this->trainingsSlide($this->slide($ppt), $snapshot);
            $this->trainingTopicsSlide($this->slide($ppt), $snapshot);
            $this->trainingPeopleSlides($ppt, $snapshot);
            $this->uniformSlide($this->slide($ppt), $snapshot);
            $this->uniformRowsSlides($ppt, $snapshot);
            $this->supervisorSlide($this->slide($ppt), $snapshot);
            $this->alarmsSlide($this->slide($ppt), $snapshot);
            $this->clientSlides($ppt, $snapshot);
            $this->closingSlide($this->slide($ppt), $snapshot, $companyLogo);

            $path = $this->save($ppt);
        } finally {
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }
            $this->tempFiles = [];
        }

        return [
            'path' => $path,
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

    private function coverSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot, ?string $companyLogo): void
    {
        $this->paintSlide($slide);

        $this->image($slide, $this->zentraLogo(), 60, 48, 280, 104);

        $this->box($slide, 640, 48, 260, 104, self::WHITE);
        if ($companyLogo !== null) {
            $this->image($slide, $companyLogo, 652, 56, 236, 88);
        } else {
            $this->text($slide, $snapshot->companyName, 652, 82, 236, 40, 16, self::NAVY, true, Alignment::HORIZONTAL_CENTER);
        }

        $this->box($slide, 60, 180, 840, 2, self::SLATE);

        $this->text($slide, 'INFORME GERENCIAL', 60, 204, 840, 26, 14, self::GOLD, true);
        $this->text($slide, 'Supervisión de campo', 60, 232, 840, 60, 40, self::INK, true);
        $this->text($slide, $snapshot->companyName, 60, 300, 840, 34, 22, self::CYAN, true);
        $this->text($slide, $snapshot->caption, 60, 340, 840, 28, 15, self::MUTED);

        $this->box($slide, 0, 470, 960, 70, self::CARD);
        $this->box($slide, 0, 470, 8, 70, self::GOLD);
        $this->text($slide, 'Generado el '.now()->format('d/m/Y H:i'), 60, 492, 420, 26, 12, self::MUTED);
        $this->text($slide, 'Zentra · Supervisión', 480, 492, 420, 26, 12, self::MUTED, false, Alignment::HORIZONTAL_RIGHT);
    }

    private function kpiSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $this->heading($slide, 'Indicadores del periodo', $snapshot->caption);

        $coverage = $snapshot->coveragePercent !== null ? $this->decimal($snapshot->coveragePercent).' %' : '—';
        $fill = match ($snapshot->semaphore) {
            'green' => self::GREEN,
            'yellow' => self::GOLD,
            'red' => self::RED,
            default => self::CARD,
        };
        $ink = $snapshot->semaphore === 'neutral' ? self::INK : self::NAVY;
        $recs = $snapshot->recommendations;

        $boxes = [
            [$coverage, 'Cumplimiento', $this->int($snapshot->sitesVisited).' de '.$this->int($snapshot->sitesContracted).' revistas esperadas', $fill, $ink],
            [$this->int($snapshot->postsDone).' de '.$this->int($snapshot->postsTotal), 'Puestos cumplidos', $this->int($snapshot->postsTotal - $snapshot->postsDone).' con pendientes', self::CARD, self::INK],
            [$this->int($snapshot->reviews), 'Revistas', $this->int($snapshot->reviewsWithNovelty).' con novedad', self::CARD, self::INK],
            [$this->int($snapshot->kmTraveled), 'Km recorridos', $this->int(count($snapshot->bySupervisor)).' supervisores', self::CARD, self::GOLD],
            [
                $this->int($recs['total']),
                'Recomendaciones',
                'B '.$recs['low'].' · M '.$recs['medium'].' · A '.$recs['high'].' · E '.$recs['extreme'],
                self::CARD,
                self::INK,
            ],
        ];

        foreach ($boxes as $index => [$value, $label, $detail, $boxFill, $boxInk]) {
            $shape = $slide->createRichTextShape()
                ->setOffsetX(40 + ($index * 178))
                ->setOffsetY(104)
                ->setWidth(168)
                ->setHeight(124);
            $shape->setFill($this->solid($boxFill));
            $shape->setInsetLeft(12)->setInsetTop(12);
            $valueRun = $shape->createTextRun($value);
            $valueRun->getFont()->setName('Calibri')->setBold(true)->setSize(24)->setColor(new Color($boxInk));
            $shape->createBreak();
            $labelRun = $shape->createTextRun(mb_strtoupper($label));
            $labelRun->getFont()->setName('Calibri')->setBold(true)->setSize(9)->setColor(new Color($boxInk));
            $shape->createBreak();
            $detailRun = $shape->createTextRun($detail);
            $detailRun->getFont()->setName('Calibri')->setSize(9)->setColor(new Color($boxInk === self::NAVY ? self::NAVY : self::MUTED));
        }

        $paragraphs = [[$this->kpiNarrative($snapshot), self::INK]];
        if ($snapshot->alerts !== []) {
            $items = array_map(fn (array $alert) => str_replace('> 12 h', 'de más de 12 h', $alert['text']), $snapshot->alerts);
            $paragraphs[] = ['Puntos de atención: '.implode('; ', $items).'.', self::GOLD];
        }
        $paragraphs[] = ['Revistas esperadas: cada puesto espera las revistas contratadas con el cliente (por defecto 1) en cada día de servicio, de 06:00 a 06:00, a cualquier hora. Los días sin servicio y los festivos sin servicio no cuentan.', self::MUTED];
        $paragraphs[] = ['Revistas de portería (código Accesos) no entran en este informe; solo la captura de campo de los supervisores.', self::MUTED];

        $this->paragraphs($slide, $paragraphs, 40, 248, 880, 220, 13);
    }

    private function kpiNarrative(SupervisionPeriodSnapshot $s): string
    {
        if ($s->sitesContracted === 0) {
            $text = 'En el periodo no hubo puestos con servicio programado, por lo que no se mide cumplimiento.';
        } else {
            $level = match ($s->semaphore) {
                'green' => 'en verde, dentro de la meta del 90 %',
                'yellow' => 'en amarillo, por debajo de la meta del 90 %',
                default => 'en rojo, lejos de la meta del 90 %',
            };
            $pending = $s->postsTotal - $s->postsDone;
            $text = 'Se esperaban '.$this->int($s->sitesContracted).' revistas en '.$this->int($s->postsTotal)
                .' puestos y se cumplieron '.$this->int($s->sitesVisited).' ('.$this->decimal((float) $s->coveragePercent).' %), un resultado '.$level.'. '
                .($pending === 0
                    ? 'Todos los puestos completaron sus días de servicio.'
                    : $this->int($s->postsDone).' puestos completaron todos sus días de servicio y '.$this->int($pending).' quedan con revistas pendientes.');
        }

        $text .= ' Los supervisores hicieron '.$this->int($s->reviews).' revistas';
        if ($s->reviews > 0) {
            $text .= $s->reviewsWithNovelty > 0
                ? ', '.$this->int($s->reviewsWithNovelty).' con novedad ('.$this->pct($s->reviewsWithNovelty, $s->reviews).')'
                : ', ninguna con novedad';
        }
        $text .= ', y recorrieron '.$this->int($s->kmTraveled).' km.';

        if ($s->sitesContracted > 0 && $s->reviews > $s->sitesVisited) {
            $text .= ' Hay más revistas que cumplimientos porque un puesto puede recibir más visitas de las contratadas; el cumplimiento cuenta hasta las revistas contratadas por puesto en cada día operativo.';
        }

        $recs = $s->recommendations;
        $critical = $recs['high'] + $recs['extreme'];
        $text .= $recs['total'] === 0
            ? ' No se emitieron recomendaciones.'
            : ' Se emitieron '.$this->int($recs['total']).' recomendaciones'.($critical > 0
                ? ', '.$this->int($critical).' de riesgo alto o extremo que requieren seguimiento.'
                : ', ninguna de riesgo alto o extremo.');

        return $text;
    }

    private function activitySlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $monthly = ($charts['grain'] ?? 'day') === 'month';
        $this->heading($slide, $monthly ? 'Actividad mensual' : 'Actividad diaria', $snapshot->caption);

        $labels = array_values($charts['labels'] ?? []);
        $reviews = array_values($charts['reviews'] ?? []);
        $km = array_values($charts['km'] ?? []);
        $noveltyYes = array_values($charts['novelty_yes'] ?? []);
        $noveltyNo = array_values($charts['novelty_no'] ?? []);
        $showValues = count($labels) <= 16;

        $stack = new Bar;
        $stack->setBarDirection(Bar::DIRECTION_VERTICAL);
        $stack->setBarGrouping(Bar::GROUPING_STACKED);
        $stack->setOverlapWidthPercent(100);
        $stack->addSeries($this->barSeries('Sin novedad', $this->combine($labels, $noveltyNo), self::CYAN, $showValues, true));
        $stack->addSeries($this->barSeries('Con novedad', $this->combine($labels, $noveltyYes), self::RED, $showValues, true));
        $this->chart($slide, $stack, 40, self::CHART_Y, 434, self::CHART_H, $monthly ? 'Revistas por mes' : 'Revistas por día');

        $line = new Line;
        $line->addSeries($this->lineSeries('Km', $this->combine($labels, $km), self::GOLD, $showValues));
        $this->chart($slide, $line, 486, self::CHART_Y, 434, self::CHART_H, $monthly ? 'Km por mes' : 'Km por día', false);

        $this->paragraphs($slide, [[$this->activityNarrative($labels, $reviews, $km, array_sum($noveltyYes), $monthly), self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $reviews
     * @param  list<int>  $km
     */
    private function activityNarrative(array $labels, array $reviews, array $km, int $novelty, bool $monthly): string
    {
        $total = array_sum($reviews);
        $kmTotal = array_sum($km);
        if ($total === 0 && $kmTotal === 0) {
            return 'No se registraron revistas ni recorridos en el periodo seleccionado.';
        }

        $unit = $monthly ? 'mes' : 'día';
        $units = $monthly ? 'meses' : 'días';
        $active = count(array_filter($reviews, fn (int $v) => $v > 0));
        $text = 'Se registraron '.$this->int($total).' revistas en '.$active.' de '.count($labels).' '.$units.' con actividad';
        if ($active > 0) {
            $text .= ', un promedio de '.$this->decimal($total / $active).' por '.$unit.' activo';
        }
        $text .= '.';

        if ($total > 0) {
            $peak = array_keys($reviews, max($reviews))[0];
            $text .= ' El '.$unit.' de mayor actividad fue el '.$labels[$peak].' con '.$this->int($reviews[$peak]).' revistas';
            if ($kmTotal > 0) {
                $peakKm = array_keys($km, max($km))[0];
                $text .= ' y el de mayor recorrido el '.$labels[$peakKm].' con '.$this->int($km[$peakKm]).' km';
            }
            $text .= '.';
            $text .= $novelty > 0
                ? ' Del total, '.$this->count($novelty, 'revista reportó', 'revistas reportaron').' novedad ('.$this->pct($novelty, $total).'), marcadas en rojo en el gráfico.'
                : ' Ninguna revista reportó novedad.';
        } else {
            $text .= ' Se recorrieron '.$this->int($kmTotal).' km sin revistas registradas.';
        }

        $idle = count($labels) - $active;
        if (! $monthly && $idle > 0) {
            $text .= ' Hubo '.$idle.' '.($idle === 1 ? 'día' : 'días').' sin revistas.';
        }

        return $text;
    }

    private function fieldSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $this->heading($slide, 'Puesto: inventario, libros y carpetas', $snapshot->caption);

        $inv = $charts['inventory'] ?? [];
        $books = $charts['books'] ?? [];
        $folders = $charts['folders'] ?? [];
        $docs = $charts['documents'] ?? [];

        $good = (int) ($inv['good'] ?? 0);
        $regular = (int) ($inv['regular'] ?? 0);
        $bad = (int) ($inv['bad'] ?? 0);
        $booksYes = (int) ($books['yes'] ?? 0);
        $booksNo = (int) ($books['no'] ?? 0);
        $complete = (int) ($folders['complete'] ?? 0);
        $missing = (int) ($folders['missing'] ?? 0);
        $delivered = (int) ($docs['delivered'] ?? 0);
        $pending = (int) ($docs['pending'] ?? 0);

        $this->donut($slide, 'Inventario', ['Bueno' => $good, 'Regular' => $regular, 'Malo' => $bad], [self::GREEN, self::GOLD, self::RED], 40, self::CHART_Y, 284, self::CHART_H);
        $this->donut($slide, 'Libros de control', ['Sin novedad' => $booksNo, 'Con novedad' => $booksYes], [self::GREEN, self::RED], 338, self::CHART_Y, 284, self::CHART_H);
        $this->donut($slide, 'Carpetas', ['Completas' => $complete, 'Con faltantes' => $missing], [self::GREEN, self::ORANGE], 636, self::CHART_Y, 284, self::CHART_H);

        $sentences = [];
        $invTotal = $good + $regular + $bad;
        $sentences[] = $invTotal === 0
            ? 'No se registró inventario de puesto.'
            : 'Se revisaron '.$this->int($invTotal).' elementos de inventario: '.$this->int($good).' en buen estado, '
                .$this->int($regular).' regular y '.$this->int($bad).' en mal estado ('.$this->pct($bad, $invTotal).').';
        $booksTotal = $booksYes + $booksNo;
        $sentences[] = $booksTotal === 0
            ? 'No se revisaron libros de control.'
            : 'En libros de control se '.($booksTotal === 1 ? 'revisó 1' : 'revisaron '.$this->int($booksTotal)).', '.($booksYes > 0 ? $this->int($booksYes).' con novedad' : 'todos sin novedad').'.';
        $foldersTotal = $complete + $missing;
        $sentences[] = $foldersTotal === 0
            ? 'No se revisaron carpetas.'
            : 'De '.$this->int($foldersTotal).' carpetas revisadas, '.$this->int($complete).' están completas y '.$this->int($missing).' con faltantes.';
        if ($delivered + $pending > 0) {
            $sentences[] = 'Documentos del turno: '.$this->count($delivered, 'entregado', 'entregados').' y '.$this->count($pending, 'pendiente', 'pendientes').'.';
        }
        $follow = array_filter([
            $bad > 0 ? 'elementos en mal estado' : null,
            $booksYes > 0 ? 'libros con novedad' : null,
            $missing > 0 ? 'carpetas con faltantes' : null,
            $pending > 0 ? 'documentos pendientes' : null,
        ]);
        if ($follow !== []) {
            $sentences[] = 'Requieren seguimiento: '.implode(', ', $follow).'.';
        }

        $this->paragraphs($slide, [[implode(' ', $sentences), self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    private function weaponsSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $this->heading($slide, 'Armamento y recomendaciones', $snapshot->caption);

        $weapons = $charts['weapons'] ?? [];
        $total = (int) ($weapons['total'] ?? 0);
        $cleaned = (int) ($weapons['cleaned'] ?? 0);
        $inspection = (int) ($weapons['inspection_only'] ?? 0);
        $novelty = (int) ($weapons['novelty'] ?? 0);
        $expired = (int) ($weapons['expired'] ?? 0);

        if ($total === 0) {
            $this->emptyChart($slide, 'Inspección de armamento', 40, self::CHART_Y, 434, self::CHART_H);
        } else {
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $series = $this->barSeries('Armas', [
                'Solo revista' => $inspection,
                'Con aseo' => $cleaned,
                'Con novedad' => $novelty,
                'Permiso vencido' => $expired,
            ], self::CYAN);
            foreach ([self::CYAN, self::GREEN, self::RED, self::ORANGE] as $index => $color) {
                $series->getDataPointFill($index)->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
            }
            $bar->addSeries($series);
            $this->chart($slide, $bar, 40, self::CHART_Y, 434, self::CHART_H, 'Inspección de armamento', false);
        }

        $levels = $charts['recs_by_level'] ?? [];
        $this->donut($slide, 'Recomendaciones por riesgo', [
            'Bajo' => (int) ($levels['low'] ?? 0),
            'Medio' => (int) ($levels['medium'] ?? 0),
            'Alto' => (int) ($levels['high'] ?? 0),
            'Extremo' => (int) ($levels['extreme'] ?? 0),
        ], [self::GREEN, self::GOLD, self::ORANGE, self::RED], 486, self::CHART_Y, 434, self::CHART_H);

        $text = $total === 0
            ? 'No se registraron inspecciones de armamento.'
            : 'Se '.($total === 1 ? 'inspeccionó 1 arma' : 'inspeccionaron '.$this->int($total).' armas').': '
                .$this->int($cleaned).' con aseo y '.$this->int($inspection).' solo con revista. '
                .($novelty > 0 ? $this->count($novelty, 'reportó', 'reportaron').' novedad' : 'Ninguna reportó novedad')
                .($expired > 0 ? ' y '.$this->count($expired, 'tiene', 'tienen').' el permiso vencido, que debe renovarse.' : '.');

        $recs = $snapshot->recommendations;
        if ($recs['total'] === 0) {
            $text .= ' No se emitieron recomendaciones en el periodo.';
        } else {
            $text .= ' Se emitieron '.$this->int($recs['total']).' recomendaciones: '.$recs['low'].' de riesgo bajo, '.$recs['medium'].' medio, '
                .$recs['high'].' alto y '.$recs['extreme'].' extremo.';
            $top = $charts['recs_by_type'][0] ?? null;
            if (is_array($top)) {
                $text .= ' El tipo más frecuente fue «'.$top['name'].'» ('.$this->int((int) $top['total']).').';
            }
        }

        $this->paragraphs($slide, [[$text, self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    private function trainingsSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $t = $charts['trainings'] ?? [];
        $monthlyGrain = ($charts['grain'] ?? 'day') === 'month';
        $this->heading($slide, 'Capacitaciones', $snapshot->caption);

        $total = (int) ($t['total'] ?? 0);
        $labels = array_values($charts['labels'] ?? []);
        $series = $t['series'] ?? [];
        if ($total === 0) {
            $this->emptyChart($slide, $monthlyGrain ? 'Capacitaciones por mes' : 'Capacitaciones por día', 40, self::CHART_Y, 560, self::CHART_H);
        } else {
            $showValues = count($labels) <= 16;
            $stack = new Bar;
            $stack->setBarDirection(Bar::DIRECTION_VERTICAL);
            $stack->setBarGrouping(Bar::GROUPING_STACKED);
            $stack->setOverlapWidthPercent(100);
            foreach ([
                ['Mensual · virtual', 'monthly_virtual', 'FF38BDF8'],
                ['Mensual · en puesto', 'monthly_onsite', 'FF0EA5E9'],
                ['Personalizada · virtual', 'custom_virtual', 'FFC4B5FD'],
                ['Personalizada · en puesto', 'custom_onsite', 'FF8B5CF6'],
            ] as [$name, $key, $color]) {
                $stack->addSeries($this->barSeries($name, $this->combine($labels, array_values($series[$key] ?? [])), $color, $showValues, true));
            }
            $this->chart($slide, $stack, 40, self::CHART_Y, 560, self::CHART_H, $monthlyGrain ? 'Capacitaciones por mes' : 'Capacitaciones por día');
        }

        $coverage = $t['coverage'] ?? ['reviewed' => 0, 'covered' => 0];
        $reviewed = (int) ($coverage['reviewed'] ?? 0);
        $covered = (int) ($coverage['covered'] ?? 0);
        $this->donut($slide, 'Cobertura mensual', [
            'Con capacitación mensual' => $covered,
            'Sin capacitación mensual' => max(0, $reviewed - $covered),
        ], ['FF2DD4BF', self::SLATE], 612, self::CHART_Y, 308, self::CHART_H);

        if ($total === 0) {
            $text = 'No se registraron capacitaciones en el periodo.';
        } else {
            $minutes = (int) ($t['minutes'] ?? 0);
            $text = 'Se dieron '.$this->count($total, 'capacitación', 'capacitaciones').' a '.$this->count((int) ($t['people'] ?? 0), 'vigilante', 'vigilantes').': '
                .$this->count((int) ($t['monthly'] ?? 0), 'mensual', 'mensuales').' y '.$this->count((int) ($t['custom'] ?? 0), 'personalizada', 'personalizadas').'; '
                .$this->int((int) ($t['virtual'] ?? 0)).' virtuales y '.$this->int((int) ($t['onsite'] ?? 0)).' en puesto'
                .($minutes > 0 ? ' ('.$this->int($minutes).' minutos de capacitación presencial)' : '').'. '
                .($reviewed > 0
                    ? 'De los '.$this->int($reviewed).' vigilantes con revista en el periodo, '.$this->int($covered).' recibieron la capacitación mensual ('.$this->pct($covered, $reviewed).').'
                    : '');
        }

        $this->paragraphs($slide, [[$text, self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    private function trainingTopicsSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $t = $snapshot->charts['trainings'] ?? [];
        $this->heading($slide, 'Temas de capacitación', $snapshot->caption);

        $topics = array_slice($t['by_topic'] ?? [], 0, 10);
        if ($topics === []) {
            $this->emptyChart($slide, 'Capacitaciones por tema', 40, self::CHART_Y, 880, self::CHART_H);
        } else {
            $monthly = [];
            $custom = [];
            foreach ($topics as $row) {
                $name = Str::limit((string) $row['name'], 40);
                $monthly[$name] = (int) $row['monthly'];
                $custom[$name] = (int) $row['custom'];
            }
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $bar->setBarGrouping(Bar::GROUPING_STACKED);
            $bar->setOverlapWidthPercent(100);
            $bar->addSeries($this->barSeries('Mensual', array_reverse($monthly, true), 'FF38BDF8', true, true));
            $bar->addSeries($this->barSeries('Personalizada', array_reverse($custom, true), 'FF8B5CF6', true, true));
            $this->chart($slide, $bar, 40, self::CHART_Y, 880, self::CHART_H, 'Capacitaciones por tema');
        }

        $top = $topics[0] ?? null;
        $text = $topics === []
            ? 'No se registraron temas de capacitación en el periodo.'
            : 'Se dieron '.$this->count(count($t['by_topic'] ?? []), 'tema', 'temas').' distintos. El más dado fue «'.$top['name'].'» ('.$this->int((int) $top['total']).': '
                .$this->int((int) $top['monthly']).' mensual y '.$this->int((int) $top['custom']).' personalizada). '
                .'Las capacitaciones personalizadas ('.$this->int((int) ($t['custom'] ?? 0)).') responden a falencias identificadas en la revista.';
        $this->paragraphs($slide, [[$text, self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    /** Personal capacitado: un renglón por capacitación, 14 por diapositiva. */
    private function trainingPeopleSlides(PhpPresentation $ppt, SupervisionPeriodSnapshot $snapshot): void
    {
        $t = $snapshot->charts['trainings'] ?? [];
        $rows = $t['people_rows'] ?? [];
        $pages = $rows === [] ? [[]] : array_chunk($rows, 14);
        $total = count($pages);
        $legend = $this->count((int) ($t['people'] ?? 0), 'vigilante capacitado', 'vigilantes capacitados').' · '
            .$this->count((int) ($t['monthly'] ?? 0), 'mensual', 'mensuales').' · '.$this->count((int) ($t['custom'] ?? 0), 'personalizada', 'personalizadas');

        foreach ($pages as $page => $chunk) {
            $slide = $this->slide($ppt);
            $this->heading($slide, 'Personal capacitado'.($total > 1 ? ' ('.($page + 1).'/'.$total.')' : ''), $snapshot->caption.' · '.$legend);
            if ($chunk === []) {
                $this->text($slide, 'No se registraron capacitaciones en el periodo.', 40, 110, 880, 40, 14, self::MUTED);

                continue;
            }

            $widths = [250, 250, 110, 100, 90, 80];
            $table = $slide->createTableShape(count($widths));
            $table->setOffsetX(40)->setOffsetY(100)->setWidth(array_sum($widths));
            $this->tableRow($table->createRow(), ['Vigilante', 'Tema', 'Tipo', 'Modalidad', 'Duración', 'Fecha'], $widths, self::CARD, self::MUTED, true);
            foreach ($chunk as $row) {
                $this->tableRow($table->createRow(), [
                    Str::limit((string) $row['employee'], 38),
                    Str::limit((string) $row['topic'], 38),
                    (string) $row['kind_label'],
                    (string) $row['modality_label'],
                    $row['minutes'] ? $row['minutes'].' min' : '—',
                    (string) $row['date'],
                ], $widths, $row['kind'] === 'custom' ? 'FF2E1065' : self::NAVY, self::INK);
            }
        }
    }

    private function uniformSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $u = $snapshot->charts['uniform'] ?? [];
        $this->heading($slide, 'Presentación personal con novedad', $snapshot->caption);

        foreach ([
            ['by_item', 'Prendas con más novedades', self::ORANGE, 40],
            ['by_people', 'Vigilantes con más novedades', self::RED, 486],
        ] as [$key, $title, $color, $x]) {
            $values = [];
            foreach (array_slice($u[$key] ?? [], 0, 8) as $row) {
                $values[Str::limit((string) $row['name'], 28)] = (int) $row['total'];
            }
            if ($values === []) {
                $this->emptyChart($slide, $title, $x, self::CHART_Y, 434, self::CHART_H);

                continue;
            }
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $bar->addSeries($this->barSeries('Novedades', array_reverse($values, true), $color));
            $this->chart($slide, $bar, $x, self::CHART_Y, 434, self::CHART_H, $title, false);
        }

        $inspected = (int) ($u['inspected'] ?? 0);
        $novelty = (int) ($u['novelty'] ?? 0);
        if ($inspected === 0) {
            $text = 'No se registró presentación personal en el periodo.';
        } elseif ($novelty === 0) {
            $text = 'Se revisó la presentación personal en '.$this->count($inspected, 'revista', 'revistas').' y ninguna tuvo novedad.';
        } else {
            $topItem = $u['by_item'][0] ?? null;
            $topPerson = $u['by_people'][0] ?? null;
            $text = $this->count($novelty, 'revista tuvo', 'revistas tuvieron').' novedad en la presentación personal ('.$this->pct($novelty, $inspected).' de las revisadas), '
                .'con '.$this->count((int) ($u['people_count'] ?? 0), 'vigilante', 'vigilantes').' involucrados.'
                .(is_array($topItem) ? ' La prenda con más novedades fue «'.$topItem['name'].'» ('.$this->int((int) $topItem['total']).').' : '')
                .(is_array($topPerson) && (int) $topPerson['total'] > 1 ? ' '.$topPerson['name'].' acumuló '.$this->int((int) $topPerson['total']).' novedades.' : '');
        }
        $this->paragraphs($slide, [[$text, self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    /** Novedades de presentación personal con la observación del supervisor, 10 por diapositiva. */
    private function uniformRowsSlides(PhpPresentation $ppt, SupervisionPeriodSnapshot $snapshot): void
    {
        $rows = $snapshot->charts['uniform']['rows'] ?? [];
        if ($rows === []) {
            return;
        }
        $pages = array_chunk($rows, 10);
        $total = count($pages);
        foreach ($pages as $page => $chunk) {
            $slide = $this->slide($ppt);
            $this->heading($slide, 'Novedades de presentación personal'.($total > 1 ? ' ('.($page + 1).'/'.$total.')' : ''), $snapshot->caption);
            $widths = [200, 180, 420, 80];
            $table = $slide->createTableShape(count($widths));
            $table->setOffsetX(40)->setOffsetY(100)->setWidth(array_sum($widths));
            $this->tableRow($table->createRow(), ['Vigilante', 'Prendas con novedad', 'Observación del supervisor', 'Fecha'], $widths, self::CARD, self::MUTED, true);
            foreach ($chunk as $row) {
                $this->tableRow($table->createRow(), [
                    Str::limit((string) $row['employee'], 30),
                    Str::limit((string) $row['items'], 30),
                    Str::limit((string) ($row['note'] ?: '—'), 80),
                    (string) $row['date'],
                ], $widths, self::NAVY, self::INK);
            }
        }
    }

    private function supervisorSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $this->heading($slide, 'Actividad por supervisor', $snapshot->caption);

        $rows = $snapshot->bySupervisor;
        $reviews = $this->namedValues($rows, 'reviews');
        $km = $this->namedValues($rows, 'km');

        foreach ([[$reviews, 'Revistas por supervisor', self::CYAN, 40], [$km, 'Km por supervisor', self::GOLD, 486]] as [$values, $title, $color, $x]) {
            if ($values === []) {
                $this->emptyChart($slide, $title, $x, self::CHART_Y, 434, self::CHART_H);

                continue;
            }
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $bar->addSeries($this->barSeries($title, array_reverse($values, true), $color));
            $this->chart($slide, $bar, $x, self::CHART_Y, 434, self::CHART_H, $title, false);
        }

        $this->paragraphs($slide, [[$this->supervisorNarrative($rows), self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    /**
     * @param  list<array{name: string, shifts: int, reviews: int, km: int, logs: int, alarms: int, supports: int}>  $rows
     */
    private function supervisorNarrative(array $rows): string
    {
        if ($rows === []) {
            return 'No hubo actividad de supervisores en el periodo seleccionado.';
        }

        $count = count($rows);
        $reviews = array_sum(array_column($rows, 'reviews'));
        $km = array_sum(array_column($rows, 'km'));
        $text = 'Estas son las mediciones de los '.$count.' supervisores con actividad en el periodo seleccionado. '
            .'En total hicieron '.$this->int($reviews).' revistas y recorrieron '.$this->int($km).' km, un promedio de '
            .$this->decimal($reviews / $count).' revistas y '.$this->decimal($km / $count).' km por supervisor.';

        $topReviews = $rows[array_keys(array_column($rows, 'reviews'), max(array_column($rows, 'reviews')))[0]];
        $topKm = $rows[array_keys(array_column($rows, 'km'), max(array_column($rows, 'km')))[0]];
        if ($reviews > 0 && $km > 0 && $topReviews['name'] === $topKm['name']) {
            $text .= ' '.$topReviews['name'].' lideró en revistas ('.$this->int($topReviews['reviews']).') y en kilómetros ('.$this->int($topKm['km']).' km).';
        } else {
            if ($reviews > 0) {
                $text .= ' '.$topReviews['name'].' hizo más revistas ('.$this->int($topReviews['reviews']).', '.$this->pct($topReviews['reviews'], $reviews).' del total).';
            }
            if ($km > 0) {
                $text .= ' '.$topKm['name'].' recorrió más kilómetros ('.$this->int($topKm['km']).' km).';
            }
        }

        $idle = count(array_filter($rows, fn (array $row) => $row['reviews'] === 0));
        if ($idle > 0) {
            $text .= ' '.$idle.' '.($idle === 1 ? 'supervisor tuvo' : 'supervisores tuvieron').' actividad sin registrar revistas.';
        }

        return $text;
    }

    private function alarmsSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot): void
    {
        $charts = $snapshot->charts;
        $this->heading($slide, 'Alarmas y apoyos', $snapshot->caption);

        $types = array_slice($charts['alarms_by_type'] ?? [], 0, 6);
        if ($types === []) {
            $this->emptyChart($slide, 'Alarmas por tipo', 40, self::CHART_Y, 284, self::CHART_H);
        } else {
            $test = [];
            $response = [];
            foreach ($types as $row) {
                $test[$row['name']] = (int) $row['test'];
                $response[$row['name']] = (int) $row['response'];
            }
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $bar->setBarGrouping(Bar::GROUPING_STACKED);
            $bar->setOverlapWidthPercent(100);
            $bar->addSeries($this->barSeries('Prueba', array_reverse($test, true), self::CYAN, true, true));
            $bar->addSeries($this->barSeries('Atención', array_reverse($response, true), self::GOLD, true, true));
            $this->chart($slide, $bar, 40, self::CHART_Y, 284, self::CHART_H, 'Alarmas por tipo');
        }

        $result = $charts['alarms_result'] ?? [];
        $this->donut($slide, 'Resultado de alarmas', [
            'OK' => (int) ($result['ok'] ?? 0),
            'Falla' => (int) ($result['fail'] ?? 0),
            'Real' => (int) ($result['real'] ?? 0),
            'Falsa' => (int) ($result['false_alarm'] ?? 0),
            'No ubicada' => (int) ($result['not_found'] ?? 0),
        ], [self::GREEN, self::RED, self::ORANGE, self::GOLD, self::MUTED], 338, self::CHART_Y, 284, self::CHART_H);

        $supports = [];
        foreach (array_slice($charts['supports_by_type'] ?? [], 0, 6) as $row) {
            $supports[$row['name']] = (int) $row['total'];
        }
        if ($supports === []) {
            $this->emptyChart($slide, 'Apoyos por tipo', 636, self::CHART_Y, 284, self::CHART_H);
        } else {
            $bar = new Bar;
            $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
            $bar->addSeries($this->barSeries('Apoyos', array_reverse($supports, true), self::GREEN));
            $this->chart($slide, $bar, 636, self::CHART_Y, 284, self::CHART_H, 'Apoyos por tipo', false);
        }

        $kind = $charts['alarms_kind'] ?? [];
        $place = $charts['supports_place'] ?? [];
        $tests = (int) ($kind['test'] ?? 0);
        $responses = (int) ($kind['response'] ?? 0);
        $alarms = $tests + $responses;
        $fail = (int) ($result['fail'] ?? 0);
        $real = (int) ($result['real'] ?? 0);

        if ($alarms === 0) {
            $text = 'No se registraron alarmas en el periodo.';
        } else {
            $text = 'Se '.($alarms === 1 ? 'registró 1 alarma' : 'registraron '.$this->int($alarms).' alarmas').': '
                .$this->count($tests, 'prueba', 'pruebas').' y '.$this->count($responses, 'atención', 'atenciones').'. '
                .'Resultado: '.$this->int((int) ($result['ok'] ?? 0)).' OK, '.$this->int($fail).' en falla, '
                .$this->count($real, 'real', 'reales').', '.$this->count((int) ($result['false_alarm'] ?? 0), 'falsa', 'falsas').' y '
                .$this->count((int) ($result['not_found'] ?? 0), 'no ubicada', 'no ubicadas').'.';
            if ($fail > 0) {
                $text .= ' Las alarmas en falla deben escalarse a mantenimiento.';
            }
        }

        $supportTotal = array_sum(array_column($charts['supports_by_type'] ?? [], 'total'));
        if ($supportTotal === 0) {
            $text .= ' No se registraron apoyos.';
        } else {
            $first = array_key_first($supports);
            $text .= ' Los supervisores dieron '.$this->count($supportTotal, 'apoyo', 'apoyos').' ('.$this->int((int) ($place['site'] ?? 0)).' en sitio y '
                .$this->int((int) ($place['road'] ?? 0)).' en vía), principalmente «'.$first.'» ('.$this->int($supports[$first]).').';
        }

        $this->paragraphs($slide, [[$text, self::INK]], 40, self::TEXT_Y, 880, self::TEXT_H);
    }

    /** Tabla de clientes con semáforo (rojo → ámbar → verde), 14 filas por diapositiva. */
    private function clientSlides(PhpPresentation $ppt, SupervisionPeriodSnapshot $snapshot): void
    {
        $rows = $snapshot->byClient;
        $pages = $rows === [] ? [[]] : array_chunk($rows, 14);
        $total = count($pages);

        $counts = array_count_values(array_column($rows, 'status'));
        $legend = $this->int($counts['red'] ?? 0).' en rojo (sin revista) · '
            .$this->int($counts['amber'] ?? 0).' en ámbar (con pendientes) · '
            .$this->int($counts['green'] ?? 0).' en verde (cumplido)';

        foreach ($pages as $page => $chunk) {
            $slide = $this->slide($ppt);
            $title = 'Cumplimiento por cliente'.($total > 1 ? ' ('.($page + 1).'/'.$total.')' : '');
            $this->heading($slide, $title, $snapshot->caption.' · '.$legend);

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

    private function closingSlide(Slide $slide, SupervisionPeriodSnapshot $snapshot, ?string $companyLogo): void
    {
        $this->text($slide, 'Gracias', 0, 150, 960, 110, 80, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->box($slide, 400, 270, 160, 4, self::GOLD);
        $this->text($slide, $snapshot->companyName, 0, 290, 960, 34, 20, self::CYAN, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, $snapshot->caption, 0, 326, 960, 26, 13, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        if ($companyLogo !== null) {
            $this->image($slide, $this->zentraLogo(), 300, 400, 170, 64);
            $this->box($slide, 490, 400, 170, 64, self::WHITE);
            $this->image($slide, $companyLogo, 498, 406, 154, 52);
        } else {
            $this->image($slide, $this->zentraLogo(), 395, 400, 170, 64);
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

    private function emptyChart(Slide $slide, string $title, int $x, int $y, int $width, int $height): void
    {
        $this->box($slide, $x, $y, $width, $height, self::CARD);
        $this->text($slide, $title, $x, $y + 10, $width, 24, 12, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, 'Sin registros en el periodo', $x, $y + (int) ($height / 2) - 12, $width, 24, 12, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
    }

    /**
     * @param  array<string, int>  $values
     */
    private function barSeries(string $title, array $values, string $color, bool $showValues = true, bool $stacked = false): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue($showValues);
        $series->setFill($this->solid($color));
        $series->setLabelPosition($stacked ? Series::LABEL_CENTER : Series::LABEL_OUTSIDEEND);
        if ($stacked) {
            $series->setDlblNumFormat('0;-0;;');
        }
        $series->getFont()->setName('Calibri')->setSize(9)->setBold($stacked)->setColor(new Color($stacked ? self::NAVY : self::INK));

        return $series;
    }

    /**
     * @param  array<string, int>  $values
     */
    private function lineSeries(string $title, array $values, string $color, bool $showValues): Series
    {
        $series = new Series($title, $this->chartValues($values));
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->setShowLegendKey(false);
        $series->setShowValue($showValues);
        $series->setLabelPosition(Series::LABEL_TOP);
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
     * @param  list<string>  $labels
     * @param  list<int>  $values
     * @return array<string, int>
     */
    private function combine(array $labels, array $values): array
    {
        $out = [];
        foreach ($labels as $index => $label) {
            $out[$label] = (int) ($values[$index] ?? 0);
        }

        return $out;
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
        arsort($values);

        return array_slice($values, 0, 12, true);
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
        $this->text($slide, $caption, 40, 62, 880, 28, 11, self::MUTED);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $paragraphs  Texto y color de cada párrafo.
     */
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
            if ($index > 0) {
                $paragraph->setSpacingBefore(6);
            }
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

    private function decimal(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    private function pct(int $part, int $total): string
    {
        return $total > 0 ? $this->decimal($part * 100 / $total).' %' : '0 %';
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
