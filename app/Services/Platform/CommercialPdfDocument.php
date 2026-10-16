<?php

declare(strict_types=1);

namespace App\Services\Platform;

use setasign\Fpdi\Fpdi;

/**
 * Plantilla visual compartida para propuesta comercial y cotizaciones (FPDF).
 */
final class CommercialPdfDocument extends Fpdi
{
    private string $docTitle;

    private string $footerLeft;

    public function __construct(string $docTitle)
    {
        parent::__construct('P', 'mm', 'Letter');
        $this->docTitle = $docTitle;
        $brand = config('commercial.brand', []);
        $this->footerLeft = trim(($brand['product'] ?? 'Zentra').' · '.($brand['company'] ?? 'W Codex').' · '.($brand['tagline'] ?? ''));
        $this->SetAutoPageBreak(true, 22);
        $this->SetMargins(16, 18, 16);
    }

    public function Header(): void
    {
        $brand = config('commercial.brand', []);
        $logo = public_path('images/branding/logo-zentra.png');
        if (is_file($logo)) {
            $this->Image($logo, 16, 10, 28);
        } else {
            $this->SetFont('Helvetica', 'B', 14);
            $this->SetTextColor(30, 41, 59);
            $this->SetXY(16, 12);
            $this->Cell(40, 8, $this->latin((string) ($brand['product'] ?? 'Zentra')), 0, 0, 'L');
        }

        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(79, 70, 229);
        $this->SetXY(120, 11);
        $this->Cell(74, 5, $this->latin((string) ($brand['company'] ?? 'W Codex')), 0, 2, 'R');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(74, 4, $this->latin((string) ($brand['tagline'] ?? '')), 0, 2, 'R');
        $this->Cell(74, 4, $this->latin((string) ($brand['product'] ?? 'Zentra').' — Software de operaciones'), 0, 1, 'R');

        $this->SetDrawColor(99, 102, 241);
        $this->SetLineWidth(0.6);
        $this->Line(16, 26, 200, 26);
        $this->SetY(30);
    }

    public function Footer(): void
    {
        $this->SetY(-18);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(16, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 116, 139);
        $brand = config('commercial.brand', []);
        $right = trim(($brand['website'] ?? '').'  '.($brand['email'] ?? ''));
        $this->Cell(120, 4, $this->latin($this->footerLeft), 0, 0, 'L');
        $this->Cell(64, 4, $this->latin($right.'  ·  p. '.$this->PageNo()), 0, 0, 'R');
    }

    public function titleBlock(string $title, string $city, string $date, string $directedTo, string $subject): void
    {
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(15, 23, 42);
        $this->MultiCell(178, 8, $this->latin($title), 0, 'L');
        $this->Ln(2);

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(178, 5, $this->latin($city.', '.$date), 0, 1, 'L');
        $this->Ln(1);

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(28, 5, $this->latin('Dirigido a:'), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->MultiCell(150, 5, $this->latin($directedTo), 0, 'L');

        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(28, 5, $this->latin('Asunto:'), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->MultiCell(150, 5, $this->latin($subject), 0, 'L');
        $this->Ln(3);
    }

    public function section(string $heading, string $body, bool $highlight = false): void
    {
        if ($highlight) {
            $this->highlightSection($heading, $body);

            return;
        }

        if ($this->GetY() > 240) {
            $this->AddPage();
        }

        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(178, 6, $this->latin($heading), 0, 1, 'L');
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(178, 5, $this->latin($body), 0, 'J');
        $this->Ln(3);
    }

    /**
     * Bloque destacado: franja de título + cuerpo aparte + acento lateral.
     */
    public function highlightSection(string $heading, string $body): void
    {
        if ($this->GetY() > 220) {
            $this->AddPage();
        }

        $x = 16.0;
        $w = 178.0;
        $inner = 168.0;
        $padX = 7.0;
        $titleH = 9.0;
        $y0 = $this->GetY();
        $latinBody = $this->latin($body);
        $latinHeading = $this->latin(mb_strtoupper($heading, 'UTF-8'));

        $this->SetFont('Helvetica', '', 9.5);
        $bodyH = $this->measureMultiCellHeight($inner, 5, $latinBody) + 6;
        $totalH = $titleH + $bodyH;

        if ($y0 + $totalH > 255) {
            $this->AddPage();
            $y0 = $this->GetY();
        }

        // Contenedor
        $this->SetFillColor(248, 250, 255);
        $this->SetDrawColor(199, 210, 254);
        $this->Rect($x, $y0, $w, $totalH, 'DF');

        // Franja título
        $this->SetFillColor(79, 70, 229);
        $this->Rect($x, $y0, $w, $titleH, 'F');

        // Acento lateral
        $this->SetFillColor(99, 102, 241);
        $this->Rect($x, $y0, 2.2, $totalH, 'F');

        $this->SetXY($x + $padX, $y0 + 2.2);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($inner, 5, $latinHeading, 0, 1, 'L');

        $this->SetXY($x + $padX, $y0 + $titleH + 3);
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell($inner, 5, $latinBody, 0, 'J');

        $this->SetY($y0 + $totalH);
        $this->Ln(3);
    }

    private function measureMultiCellHeight(float $width, float $lineHeight, string $latinText): float
    {
        $lines = 0;
        foreach (preg_split('/\r\n|\r|\n/', $latinText) ?: [''] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines++;
                continue;
            }
            $words = preg_split('/\s+/', $paragraph) ?: [];
            $line = '';
            foreach ($words as $word) {
                $trial = $line === '' ? $word : $line.' '.$word;
                if ($this->GetStringWidth($trial) <= $width) {
                    $line = $trial;
                } else {
                    $lines++;
                    $line = $word;
                }
            }
            if ($line !== '') {
                $lines++;
            }
        }

        return max(1, $lines) * $lineHeight;
    }

    public function money(float $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    public function latin(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }

    public function presenterBlock(string $name, string $role, string $phone, string $email): void
    {
        if ($this->GetY() > 230) {
            $this->AddPage();
        }

        $this->Ln(2);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(178, 5, $this->latin('Atentamente,'), 0, 1, 'L');
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(178, 5, $this->latin($name), 0, 1, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(71, 85, 105);
        if ($role !== '') {
            $this->Cell(178, 4.5, $this->latin($role), 0, 1, 'L');
        }
        if (trim($phone) !== '') {
            $this->Cell(178, 4.5, $this->latin($phone), 0, 1, 'L');
        }
        if (trim($email) !== '') {
            $this->Cell(178, 4.5, $this->latin($email), 0, 1, 'L');
        }
        $brand = config('commercial.brand', []);
        $this->Cell(178, 4.5, $this->latin(($brand['product'] ?? 'Zentra').' · '.($brand['company'] ?? 'W Codex')), 0, 1, 'L');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}|null  $fillRgb
     * @param  array{0: int, 1: int, 2: int}|null  $textRgb
     */
    public function tableHeader(array $cols, array $widths, ?array $fillRgb = null, ?array $textRgb = null): void
    {
        $fill = $fillRgb ?? [79, 70, 229];
        $text = $textRgb ?? [255, 255, 255];
        $this->SetFillColor($fill[0], $fill[1], $fill[2]);
        $this->SetDrawColor(
            max(0, $fill[0] - 25),
            max(0, $fill[1] - 25),
            max(0, $fill[2] - 25),
        );
        $this->SetTextColor($text[0], $text[1], $text[2]);
        $this->SetFont('Helvetica', 'B', 8);
        foreach ($cols as $i => $col) {
            $this->Cell($widths[$i], 7, $this->latin($col), 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Helvetica', '', 8);
    }

    public function ensureSpace(float $mm): void
    {
        if ($this->GetY() + $mm > 255) {
            $this->AddPage();
        }
    }
}
