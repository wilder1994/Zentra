<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Carbon;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\AutoShape;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;
use PhpOffice\PhpPresentation\Slide\Background\Image as BackgroundImage;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

/**
 * Presentación comercial PPTX 16:9 — 11 diapositivas con capturas del panel empresa.
 */
final class BuildCommercialDeckPptxService
{
    /** Fondo suave (slate-50). */
    private const CANVAS = 'FFF8FAFC';

    /** Borde sutil de tarjeta (slate-200). */
    private const CARD_BORDER = 'FFE2E8F0';

    /** Texto principal (slate-900). */
    private const INK = 'FF0F172A';

    /** Texto secundario (slate-600). */
    private const MUTED = 'FF475569';

    /** Acento marca índigo. */
    private const VIOLET = 'FF4F46E5';

    /** Acento marca esmeralda. */
    private const EMERALD = 'FF059669';

    /** Velo suave sobre hero (deja ver la imagen). */
    private const OVERLAY = '66F8FAFC';

    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * @param  array{
     *   date: string, city: string, directed_to: string, subject: string,
     *   context: string, solution: string, why: string, next_steps: string, closing: string,
     *   presenter_name: string, presenter_role: string, presenter_phone: string, presenter_email: string
     * }  $data
     */
    public function build(array $data): string
    {
        $this->tempFiles = [];
        try {
            $brand = config('commercial.brand', []);
            $date = Carbon::parse($data['date'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

            $ppt = new PhpPresentation;
            $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);
            $ppt->getDocumentProperties()
                ->setCreator((string) ($brand['product'] ?? 'Zentra'))
                ->setLastModifiedBy((string) ($brand['company'] ?? 'W Codex'))
                ->setTitle((string) $data['subject'])
                ->setSubject('Presentación comercial')
                ->setDescription('Presentación comercial Zentra · W Codex — panel empresa');

            $this->coverSlide($ppt->getActiveSlide(), $data, $brand, $date);
            $this->introGallerySlide($this->slide($ppt), $data);
            $this->accessSlide($this->slide($ppt));
            $this->moduleShotSlide(
                $this->slide($ppt),
                'Supervisión en campo',
                'Mapa en vivo, turnos, revistas y trazabilidad GPS del supervisor.',
                'Desde el panel de empresa usted ve quién está en ruta, la señal GPS, las revistas del día y el recorrido. La evidencia queda lista para reportar a sus clientes.',
                'co_supervision',
            );
            $this->moduleShotSlide(
                $this->slide($ppt),
                'Observatorio de novedades',
                'Tablero de eventos, calor de riesgo y atención de folios en tiempo real.',
                'Centralice amenazas, hurtos y novedades reportadas desde la comunidad o el puesto. Filtre por cliente, tipología y estado; comparta tableros y exporte cuando lo necesite.',
                'co_observatory',
            );
            $this->moduleShotSlide(
                $this->slide($ppt),
                'Indexación de empleados',
                'Maestro de personal, expedientes y carga masiva ordenada.',
                'Mantenga el censo laboral al día: fichas, documentación y parafiscales en un solo lugar. Menos Excel sueltos, más control de cumplimiento ante auditorías.',
                'co_employees',
            );
            $this->benefitsHighlightSlide($this->slide($ppt));
            $this->benefitsMoreSlide($this->slide($ppt), $data);
            $this->extraModulesSlide($this->slide($ppt));
            $this->closingSlide($this->slide($ppt), $data, $brand);
            $this->thanksSlide($this->slide($ppt), $data, $brand);

            return $this->saveBinary($ppt);
        } finally {
            foreach ($this->tempFiles as $tmp) {
                @unlink($tmp);
            }
            $this->tempFiles = [];
        }
    }

    /** @param  array<string, mixed>  $data  @param  array<string, mixed>  $brand */
    private function coverSlide(Slide $slide, array $data, array $brand, string $date): void
    {
        // Imagen welcome sin velo oscuro pre-bakeado; el contraste va en overlays claros.
        $hero = public_path('images/welcome/hero-dashboard.png');
        if (! is_file($hero)) {
            $hero = $this->shotPath('hero_cover');
        }
        if (is_string($hero) && is_file($hero)) {
            $bg = new BackgroundImage;
            $bg->setPath($hero);
            $slide->setBackground($bg);
        } else {
            $this->paintSlide($slide);
        }

        $this->box($slide, 0, 0, 960, 540, self::OVERLAY);
        // Panel semitransparente + radio bajo (no el roundRect por defecto).
        $this->frostedPanel($slide, 160, 80, 640, 380, 10);

        $logo = public_path('images/branding/logo-zentra.png');
        if (is_file($logo)) {
            $this->image($slide, $logo, 400, 108, 160, 52, 0);
        }

        $this->text($slide, (string) ($brand['company'] ?? 'W Codex'), 160, 168, 640, 28, 16, self::VIOLET, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, (string) ($brand['tagline'] ?? 'Innovative Software Solutions'), 160, 196, 640, 22, 12, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        $this->text($slide, 'PRESENTACIÓN COMERCIAL', 160, 240, 640, 26, 13, self::EMERALD, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, (string) ($brand['product'] ?? 'Zentra'), 160, 272, 640, 48, 36, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, (string) $data['subject'], 180, 328, 600, 36, 15, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        $this->text($slide, 'Dirigido a: '.$data['directed_to'], 160, 380, 640, 26, 14, self::INK, false, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, $data['city'].' · '.$date, 160, 410, 640, 24, 12, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        // Línea de marca encima del panel (última capa).
        $this->box($slide, 180, 80, 600, 5, self::VIOLET);
    }

    /** @param  array<string, mixed>  $data */
    private function introGallerySlide(Slide $slide, array $data): void
    {
        $this->paintSlide($slide);
        $this->text($slide, 'Una plataforma. Toda su operación.', 40, 18, 880, 44, 28, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $intro = trim((string) $data['context']) !== ''
            ? (string) $data['context']
            : (string) $data['solution'];
        $this->text($slide, $intro, 80, 66, 800, 48, 13, self::MUTED, false, Alignment::HORIZONTAL_CENTER);

        $shots = [
            ['key' => 'co_access_admin', 'fallback' => 'co_dashboard', 'label' => 'Accesos'],
            ['key' => 'co_supervision', 'fallback' => null, 'label' => 'Supervisión'],
            ['key' => 'co_observatory', 'fallback' => null, 'label' => 'Observatorio'],
            ['key' => 'co_employees', 'fallback' => null, 'label' => 'Indexación'],
        ];
        $positions = [
            [40, 128, 440, 185],
            [500, 128, 420, 185],
            [40, 330, 440, 185],
            [500, 330, 420, 185],
        ];

        foreach ($shots as $i => $shot) {
            [$x, $y, $w, $h] = $positions[$i];
            $this->card($slide, $x, $y, $w, $h);
            $path = $this->shotPath($shot['key'])
                ?? ($shot['fallback'] ? $this->shotPath($shot['fallback']) : null);
            if ($path !== null) {
                $this->image($slide, $path, $x + 10, $y + 10, $w - 20, $h - 38);
            }
            $this->text($slide, $shot['label'], $x + 14, $y + $h - 28, $w - 28, 22, 12, self::EMERALD, true);
        }
    }

    private function accessSlide(Slide $slide): void
    {
        $this->paintSlide($slide);
        $this->heading($slide, 'Accesos', 'Panel de administrador de instalaciones y panel del vigilante');

        $this->card($slide, 40, 100, 440, 400);
        $path = $this->shotPath('co_access_admin') ?? $this->shotPath('co_dashboard');
        if ($path !== null) {
            $this->image($slide, $path, 52, 112, 416, 220);
        }
        $this->text($slide, 'Administrador de instalaciones', 56, 348, 408, 28, 14, self::EMERALD, true);
        $this->text(
            $slide,
            'Gestione clientes, instalaciones, puestos y módulos. Active portería o el panel del cliente según el rol operativo.',
            56,
            380,
            408,
            100,
            12,
            self::INK,
        );

        $this->card($slide, 500, 100, 420, 400);
        $guard = $this->shotPath('co_access_guard') ?? $this->shotPath('co_access_admin');
        if ($guard !== null) {
            $this->image($slide, $guard, 512, 112, 396, 220);
        }
        $this->text($slide, 'Panel del vigilante (portería)', 516, 348, 388, 28, 14, self::EMERALD, true);
        $this->text(
            $slide,
            'Ingresos, salidas, minuta y correspondencia en el puesto. Operación clara para el personal de campo, sin fricción.',
            516,
            380,
            388,
            100,
            12,
            self::INK,
        );
    }

    private function moduleShotSlide(Slide $slide, string $title, string $caption, string $copy, string $shotKey): void
    {
        $this->paintSlide($slide);
        $this->heading($slide, $title, $caption);

        $this->card($slide, 40, 105, 360, 390);
        $this->text($slide, $copy, 60, 130, 320, 340, 15, self::INK);

        $this->card($slide, 420, 105, 500, 390);
        $path = $this->shotPath($shotKey);
        if ($path !== null) {
            $this->image($slide, $path, 436, 121, 468, 358);
        }
    }

    private function benefitsHighlightSlide(Slide $slide): void
    {
        $this->paintSlide($slide);
        $this->heading($slide, 'Beneficios que marcan la diferencia', 'Control de usuarios y georreferenciación en tiempo real');

        $this->card($slide, 40, 110, 440, 380);
        $users = $this->shotPath('co_users');
        if ($users !== null) {
            $this->image($slide, $users, 56, 126, 408, 220);
        }
        $this->text($slide, 'Control de usuarios', 56, 360, 408, 28, 15, self::EMERALD, true);
        $this->text(
            $slide,
            'Asigne roles, active o desactive accesos y vincule empleados a cuentas. Quién entra al sistema queda bajo su control.',
            56,
            396,
            408,
            80,
            12,
            self::INK,
        );

        $this->card($slide, 500, 110, 420, 380);
        $geo = $this->shotPath('co_georef') ?? $this->shotPath('co_supervision') ?? $this->shotPath('co_dashboard');
        if ($geo !== null) {
            $this->image($slide, $geo, 516, 126, 388, 220);
        }
        $this->text($slide, 'Georreferenciación en tiempo real', 516, 360, 388, 28, 15, self::EMERALD, true);
        $this->text(
            $slide,
            'Ubicación de supervisores e instalaciones con GPS. Apruebe puntos enviados desde la app y mida revistas con precisión.',
            516,
            396,
            388,
            80,
            12,
            self::INK,
        );
    }

    /** @param  array<string, mixed>  $data */
    private function benefitsMoreSlide(Slide $slide, array $data): void
    {
        $this->paintSlide($slide);
        $this->heading($slide, 'Más valor acumulado', 'Operación unificada, evidencia y escala comercial');

        $items = [
            ['title' => 'Operación en un solo panel', 'copy' => 'Accesos, supervisión, indexación y observatorio sin saltar entre herramientas.'],
            ['title' => 'Evidencia lista para reportar', 'copy' => 'Revistas, novedades y documentación con trazabilidad para sus clientes.'],
            ['title' => 'Escalabilidad por cupos', 'copy' => 'Crezca instalaciones, puestos y empleados según su cartera real.'],
            ['title' => 'Imagen profesional', 'copy' => 'Una plataforma moderna refuerza la confianza ante gerencia y contratantes.'],
        ];

        $why = trim((string) $data['why']);
        if ($why !== '') {
            $this->card($slide, 40, 105, 880, 70);
            $this->text($slide, $why, 56, 118, 848, 48, 13, self::MUTED);
        }

        $y = $why !== '' ? 190 : 110;
        $x = 40;
        foreach ($items as $i => $item) {
            if ($i === 2) {
                $x = 40;
                $y += 160;
            }
            $this->card($slide, $x, $y, 430, 140);
            $this->text($slide, $item['title'], $x + 18, $y + 18, 394, 32, 15, self::EMERALD, true);
            $this->text($slide, $item['copy'], $x + 18, $y + 56, 394, 70, 13, self::INK);
            $x += 450;
        }
    }

    private function extraModulesSlide(Slide $slide): void
    {
        $this->paintSlide($slide);
        $this->heading(
            $slide,
            'Módulos extra con georreferenciación',
            'Cobertura territorial, solicitudes GPS y mapa operativo de la empresa',
        );

        $this->card($slide, 40, 105, 560, 390);
        $this->text(
            $slide,
            'Además del mapa de supervisión, el panel de empresa integra georreferenciación en la cobertura de clientes e instalaciones: pines en el mapa, solicitudes de ubicación desde la app de campo y aprobación centralizada.',
            60,
            125,
            520,
            120,
            14,
            self::INK,
        );
        $this->text(
            $slide,
            'Así mide distancia a puesto, valida revistas en sitio y mantiene actualizado el territorio que opera su empresa. Cada pin cuenta: menos dudas, más control.',
            60,
            260,
            520,
            100,
            14,
            self::MUTED,
        );
        $this->text($slide, '• Mapa de cobertura en Mi empresa', 60, 380, 520, 28, 13, self::INK);
        $this->text($slide, '• Georreferenciaciones pendientes / historial', 60, 412, 520, 28, 13, self::INK);
        $this->text($slide, '• Señal GPS del supervisor en vivo', 60, 444, 520, 28, 13, self::INK);

        $this->card($slide, 620, 105, 300, 185);
        $dash = $this->shotPath('co_dashboard');
        if ($dash !== null) {
            $this->image($slide, $dash, 632, 117, 276, 160);
        }
        $this->card($slide, 620, 310, 300, 185);
        $geo = $this->shotPath('co_georef') ?? $this->shotPath('co_supervision');
        if ($geo !== null) {
            $this->image($slide, $geo, 632, 322, 276, 160);
        }
    }

    /** @param  array<string, mixed>  $data  @param  array<string, mixed>  $brand */
    private function closingSlide(Slide $slide, array $data, array $brand): void
    {
        $this->paintSlide($slide);
        $this->heading($slide, 'La plataforma Zentra', 'Lista para digitalizar la operación de su empresa');

        $dash = $this->shotPath('co_dashboard');
        if ($dash !== null) {
            $this->card($slide, 40, 105, 520, 300);
            $this->image($slide, $dash, 52, 117, 496, 276);
        }

        $this->card($slide, 580, 105, 340, 300);
        $this->text($slide, 'Próximos pasos', 600, 125, 300, 28, 15, self::EMERALD, true);
        $this->text($slide, (string) $data['next_steps'], 600, 165, 300, 140, 13, self::INK);
        $this->text($slide, $data['presenter_name'], 600, 320, 300, 24, 14, self::INK, true);
        if ($data['presenter_role'] !== '') {
            $this->text($slide, $data['presenter_role'], 600, 348, 300, 22, 12, self::MUTED);
        }

        $this->text($slide, (string) $data['closing'], 40, 420, 880, 50, 14, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
        $contact = trim(implode(' · ', array_filter([
            (string) $data['presenter_phone'],
            (string) $data['presenter_email'],
            (string) ($brand['website'] ?? ''),
        ])));
        $this->text(
            $slide,
            ($brand['product'] ?? 'Zentra').' · '.($brand['company'] ?? 'W Codex').($contact !== '' ? ' · '.$contact : ''),
            40,
            480,
            880,
            28,
            13,
            self::VIOLET,
            true,
            Alignment::HORIZONTAL_CENTER,
        );
    }

    /** @param  array<string, mixed>  $data  @param  array<string, mixed>  $brand */
    private function thanksSlide(Slide $slide, array $data, array $brand): void
    {
        $hero = public_path('images/welcome/hero-dashboard.png');
        if (! is_file($hero)) {
            $hero = $this->shotPath('hero_cover');
        }
        if (is_string($hero) && is_file($hero)) {
            $bg = new BackgroundImage;
            $bg->setPath($hero);
            $slide->setBackground($bg);
        } else {
            $this->paintSlide($slide);
        }

        $this->box($slide, 0, 0, 960, 540, self::OVERLAY);
        $this->frostedPanel($slide, 180, 100, 600, 340, 10);
        $this->box($slide, 200, 100, 560, 5, self::VIOLET);

        $logo = public_path('images/branding/logo-zentra.png');
        if (is_file($logo)) {
            $this->image($slide, $logo, 400, 130, 160, 52, 0);
        }

        $this->text($slide, 'Gracias', 200, 210, 560, 56, 40, self::INK, true, Alignment::HORIZONTAL_CENTER);
        $this->text($slide, (string) $data['closing'], 220, 280, 520, 70, 15, self::MUTED, false, Alignment::HORIZONTAL_CENTER);
        $this->text(
            $slide,
            ($brand['product'] ?? 'Zentra').' · '.($brand['company'] ?? 'W Codex'),
            200,
            370,
            560,
            28,
            14,
            self::VIOLET,
            true,
            Alignment::HORIZONTAL_CENTER,
        );
    }

    private function shotPath(string $key): ?string
    {
        $relative = (string) config("commercial.deck_shots.{$key}", '');
        if ($relative === '') {
            return null;
        }

        // Local: junction public/images → resources/images. VPS: mismo enlace o fallback a resources/.
        foreach ([public_path($relative), resource_path($relative)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
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
        $this->box($slide, 0, 0, 960, 8, self::VIOLET);
    }

    private function heading(Slide $slide, string $title, string $caption): void
    {
        $this->text($slide, $title, 40, 22, 880, 36, 22, self::INK, true);
        $this->text($slide, $caption, 40, 58, 880, 28, 12, self::MUTED);
    }

    /** Tarjeta redondeada moderada con sombra suave y degradado claro. */
    private function card(Slide $slide, int $x, int $y, int $w, int $h): void
    {
        $shadow = $slide->createAutoShape();
        $shadow->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x + 3)
            ->setOffsetY($y + 5)
            ->setWidth($w)
            ->setHeight($h);
        $shadow->setFill($this->solid('220F172A'));
        $shadow->getOutline()->setWidth(0);

        $card = $slide->createAutoShape();
        $card->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($w)
            ->setHeight($h);

        $fill = new Fill;
        $fill->setFillType(Fill::FILL_GRADIENT_LINEAR)
            ->setRotation(270)
            ->setStartColor(new Color('FFFFFFFF'))
            ->setEndColor(new Color('FFF1F5F9'));
        $card->setFill($fill);

        $card->getOutline()->setWidth(1);
        $card->getOutline()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color(self::CARD_BORDER));
    }

    /** Panel frosted semitransparente con radio bajo (portada / gracias). */
    private function frostedPanel(Slide $slide, int $x, int $y, int $w, int $h, int $radius): void
    {
        $path = $this->makeFrostedPanelPng($w, $h, $radius);
        $slide->createDrawingShape()
            ->setPath($path)
            ->setResizeProportional(false)
            ->setWidth($w)
            ->setHeight($h)
            ->setOffsetX($x)
            ->setOffsetY($y);
    }

    private function box(Slide $slide, int $x, int $y, int $w, int $h, string $argb, ?string $borderArgb = null): void
    {
        $shape = $slide->createRichTextShape()
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($w)
            ->setHeight($h);
        $shape->setFill($this->solid($argb));
        $shape->getBorder()->setLineStyle(\PhpOffice\PhpPresentation\Style\Border::LINE_NONE);
        if ($borderArgb !== null) {
            $shape->getBorder()
                ->setLineStyle(\PhpOffice\PhpPresentation\Style\Border::LINE_SINGLE)
                ->setColor(new Color($borderArgb));
        }
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

    /**
     * Inserta captura recortada al tamaño del hueco y con esquinas redondeadas
     * para que no se salga del contenedor.
     */
    private function image(Slide $slide, string $path, int $x, int $y, int $maxWidth, int $maxHeight, int $radius = 12): void
    {
        if (! is_file($path)) {
            return;
        }

        $clipped = $radius > 0
            ? $this->clipImageToRoundedBox($path, $maxWidth, $maxHeight, $radius)
            : $path;

        $size = @getimagesize($clipped);
        if ($size === false || ($size[0] ?? 0) === 0 || ($size[1] ?? 0) === 0) {
            return;
        }

        $slide->createDrawingShape()
            ->setPath($clipped)
            ->setResizeProportional(false)
            ->setWidth($maxWidth)
            ->setHeight($maxHeight)
            ->setOffsetX($x)
            ->setOffsetY($y);
    }

    private function solid(string $argb): Fill
    {
        $fill = new Fill;
        $fill->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($argb));

        return $fill;
    }

    private function makeFrostedPanelPng(int $w, int $h, int $radius): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $w, $h, $clear);
        imagealphablending($img, true);

        // Sombra interior suave (derecha/abajo)
        for ($i = 6; $i >= 1; $i--) {
            $a = (int) min(118, 20 + $i * 6);
            $c = imagecolorallocatealpha($img, 15, 23, 42, $a);
            $this->filledRoundRect($img, 2 + $i, 3 + $i, $w - 4 - $i, $h - 5 - $i, $radius, $c);
        }

        // Panel blanco semitransparente (~50% opaco → alpha GD 64)
        $fill = imagecolorallocatealpha($img, 255, 255, 255, 64);
        $border = imagecolorallocatealpha($img, 199, 210, 254, 50);
        $this->filledRoundRect($img, 0, 0, $w, $h, $radius, $fill);
        $this->strokeRoundRect($img, 0, 0, $w - 1, $h - 1, $radius, $border);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zentra_frost_'.uniqid('', true).'.png';
        imagepng($img, $path, 6);
        imagedestroy($img);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function clipImageToRoundedBox(string $srcPath, int $boxW, int $boxH, int $radius): string
    {
        $src = @imagecreatefrompng($srcPath);
        if ($src === false) {
            $src = @imagecreatefromjpeg($srcPath);
        }
        if ($src === false) {
            return $srcPath;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            imagedestroy($src);

            return $srcPath;
        }

        // Cover: llena el hueco y recorta el exceso.
        $scale = max($boxW / $sw, $boxH / $sh);
        $nw = (int) max(1, round($sw * $scale));
        $nh = (int) max(1, round($sh * $scale));
        $ox = (int) (($nw - $boxW) / 2);
        $oy = (int) (($nh - $boxH) / 2);

        $scaled = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
        imagedestroy($src);

        $out = imagecreatetruecolor($boxW, $boxH);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $boxW, $boxH, $clear);

        $mask = imagecreatetruecolor($boxW, $boxH);
        imagesavealpha($mask, true);
        imagealphablending($mask, false);
        imagefilledrectangle($mask, 0, 0, $boxW, $boxH, $clear);
        imagealphablending($mask, true);
        $white = imagecolorallocate($mask, 255, 255, 255);
        $this->filledRoundRect($mask, 0, 0, $boxW, $boxH, $radius, $white);

        imagealphablending($out, true);
        for ($y = 0; $y < $boxH; $y++) {
            for ($x = 0; $x < $boxW; $x++) {
                $m = imagecolorat($mask, $x, $y);
                $ma = ($m >> 24) & 0x7F;
                if ($ma > 100) {
                    continue;
                }
                $sx = $x + $ox;
                $sy = $y + $oy;
                if ($sx < 0 || $sy < 0 || $sx >= $nw || $sy >= $nh) {
                    continue;
                }
                $rgb = imagecolorat($scaled, $sx, $sy);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, 0));
            }
        }

        imagedestroy($scaled);
        imagedestroy($mask);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zentra_clip_'.uniqid('', true).'.png';
        imagepng($out, $path, 6);
        imagedestroy($out);
        $this->tempFiles[] = $path;

        return $path;
    }

    /** @param  \GdImage  $img */
    private function filledRoundRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = max(0, min($r, (int) floor(min($w, $h) / 2)));
        if ($r < 1) {
            imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $color);

            return;
        }
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }

    /** @param  \GdImage  $img */
    private function strokeRoundRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = max(0, min($r, (int) floor(min($w, $h) / 2)));
        imageline($img, $x + $r, $y, $x + $w - $r, $y, $color);
        imageline($img, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
        imageline($img, $x, $y + $r, $x, $y + $h - $r, $color);
        imageline($img, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
        if ($r < 1) {
            return;
        }
        imagearc($img, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $color);
        imagearc($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $color);
        imagearc($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $color);
    }

    private function saveBinary(PhpPresentation $ppt): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zentra_deck_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal de la presentación.');
        }

        unlink($path);
        $path .= '.pptx';
        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);

        // Radio moderado en roundRect (adj por defecto es demasiado alto).
        $this->softenRoundRectCorners($path, 9000);

        $binary = file_get_contents($path);
        @unlink($path);

        if ($binary === false) {
            throw new \RuntimeException('No se pudo leer el PowerPoint generado.');
        }

        return $binary;
    }

    private function softenRoundRectCorners(string $pptxPath, int $adj): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($pptxPath) !== true) {
            return;
        }

        $replacement = 'prst="roundRect"><a:avLst><a:gd name="adj" fmla="val '.$adj.'"/></a:avLst>';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) {
                continue;
            }
            $xml = (string) $zip->getFromIndex($i);
            $updated = preg_replace(
                '#prst="roundRect">\s*<a:avLst\s*/>#',
                $replacement,
                $xml,
            );
            $updated = preg_replace(
                '#prst="roundRect">\s*<a:avLst>\s*</a:avLst>#',
                $replacement,
                (string) $updated,
            );
            if (is_string($updated) && $updated !== $xml) {
                $zip->deleteName($name);
                $zip->addFromString($name, $updated);
            }
        }
        $zip->close();
    }
}
