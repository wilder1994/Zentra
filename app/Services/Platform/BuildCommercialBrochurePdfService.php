<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Support\Catalog\CatalogSettings;
use Illuminate\Support\Carbon;

final class BuildCommercialBrochurePdfService
{
    /**
     * @param  array{
     *   date: string, city: string, directed_to: string, subject: string,
     *   introduction: string, highlight: string, conclusions: string,
     *   presenter_name: string, presenter_role: string, presenter_phone: string, presenter_email: string
     * }  $data
     */
    public function build(array $data): string
    {
        $catalog = CatalogSettings::current();
        $pdf = new CommercialPdfDocument('Propuesta comercial');
        $pdf->AddPage();

        $date = Carbon::parse($data['date'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $pdf->titleBlock(
            'Propuesta comercial',
            $data['city'],
            $date,
            $data['directed_to'],
            $data['subject'],
        );

        $pdf->section('Introducción', $data['introduction']);
        $pdf->section('Lo que destaca', $data['highlight'], true);

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(178, 7, $pdf->latin('Planes comerciales vigentes'), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->MultiCell(178, 4.5, $pdf->latin('Referencia de catálogo. Cada producto se cotiza según su cantidad (instalaciones, puestos o empleados). Combinar productos aplica descuento adicional del '.number_format($catalog->comboDiscount() * 100, 0).'% a los de menor valor.'), 0, 'L');
        $pdf->Ln(2);

        foreach (CommercialMetal::cases() as $metal) {
            if (! $catalog->isMetalAvailable($metal)) {
                continue;
            }
            $this->metalCard($pdf, $catalog, $metal);
        }

        $pdf->section('Conclusiones', $data['conclusions']);
        $pdf->presenterBlock(
            $data['presenter_name'],
            $data['presenter_role'],
            $data['presenter_phone'],
            $data['presenter_email'],
        );

        return $pdf->Output('S');
    }

    private function metalCard(CommercialPdfDocument $pdf, CatalogSettings $catalog, CommercialMetal $metal): void
    {
        $pdf->ensureSpace(42);
        $colors = $metal->pdfColors();
        [$hr, $hg, $hb] = $colors['header'];
        [$sr, $sg, $sb] = $colors['soft'];
        [$br, $bg, $bb] = $colors['border'];
        [$ir, $ig, $ib] = $colors['ink'];

        $pdf->SetFillColor($hr, $hg, $hb);
        $pdf->SetDrawColor($hr, $hg, $hb);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $title = $metal->label().'  ·  '.$metal->rangeLabelFor(CommercialProduct::Access);
        $pdf->Cell(178, 7.5, $pdf->latin('  '.$title), 1, 1, 'L', true);

        $pdf->SetFillColor($sr, $sg, $sb);
        $pdf->SetDrawColor($br, $bg, $bb);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor($ir, $ig, $ib);
        $meta = 'Hasta '.number_format($metal->employeeCap(), 0, ',', '.').' empleados · '
            .$metal->postsPerInstallation().' puestos/instalación · '
            .$metal->employeesPerPost().' empleados/puesto';
        $pdf->Cell(178, 5.5, $pdf->latin('  '.$meta), 1, 1, 'L', true);

        $widths = [52, 42, 28, 56];
        $pdf->tableHeader(
            ['Producto', 'Unidad lista', 'Dto. plan', 'Unidad neta / mes'],
            $widths,
            $colors['header'],
        );

        $row = 0;
        foreach (CommercialProduct::cases() as $product) {
            $unitKey = $product === CommercialProduct::Access ? 'access' : $product->value;
            $list = $catalog->unit($unitKey);
            $discount = $catalog->discount($product, $metal);
            $net = round($list * (1 - $discount), 2);
            $this->metalRow($pdf, $widths, $colors, $row % 2 === 0, [
                $product->label(),
                $pdf->money($list),
                number_format($discount * 100, 0).'%',
                $pdf->money($net).' / '.$product->unitLabel(1),
            ]);
            $row++;
        }

        if ($catalog->hardwareAvailable()) {
            $list = $catalog->unit('access_hardware');
            $discount = $catalog->discount(CommercialProduct::Access, $metal);
            $net = round($list * (1 - $discount), 2);
            $this->metalRow($pdf, $widths, $colors, $row % 2 === 0, [
                'Accesos con hardware',
                $pdf->money($list),
                number_format($discount * 100, 0).'%',
                $pdf->money($net).' / instalación',
            ]);
        }

        $pdf->Ln(3);
    }

    /**
     * @param  array{header: array{0: int, 1: int, 2: int}, soft: array{0: int, 1: int, 2: int}, border: array{0: int, 1: int, 2: int}, ink: array{0: int, 1: int, 2: int}}  $colors
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    private function metalRow(CommercialPdfDocument $pdf, array $widths, array $colors, bool $soft, array $cells): void
    {
        [$sr, $sg, $sb] = $colors['soft'];
        [$br, $bg, $bb] = $colors['border'];
        $pdf->SetDrawColor($br, $bg, $bb);
        if ($soft) {
            $pdf->SetFillColor($sr, $sg, $sb);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Helvetica', '', 8);
        $aligns = ['L', 'R', 'C', 'R'];
        foreach ($cells as $i => $cell) {
            $pdf->Cell($widths[$i], 6, $pdf->latin($cell), 1, $i === count($cells) - 1 ? 1 : 0, $aligns[$i], true);
        }
    }
}

