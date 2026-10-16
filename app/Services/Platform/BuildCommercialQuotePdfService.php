<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Services\Pricing\PlanQuoter;
use Illuminate\Support\Carbon;

final class BuildCommercialQuotePdfService
{
    public function __construct(private readonly PlanQuoter $quoter) {}

    /**
     * @param  array{
     *   date: string, city: string, directed_to: string, subject: string,
     *   introduction: string, highlight: string, conclusions: string,
     *   presenter_name: string, presenter_role: string, presenter_phone: string, presenter_email: string,
     *   cycle: string, commercial_amount: float, commercial_note: ?string,
     *   selection: PlanSelection
     * }  $data
     */
    public function build(array $data): string
    {
        $cycle = BillingCycle::from($data['cycle']);
        $quote = $this->quoter->quote($data['selection'], $cycle);
        $pdf = new CommercialPdfDocument('Cotización comercial');
        $pdf->AddPage();

        $date = Carbon::parse($data['date'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $pdf->titleBlock(
            'Cotización comercial',
            $data['city'],
            $date,
            $data['directed_to'],
            $data['subject'],
        );

        $pdf->section('Introducción', $data['introduction']);
        $pdf->section('Lo que destaca', $data['highlight'], true);

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(178, 7, $pdf->latin('Detalle del plan cotizado'), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(71, 85, 105);
        $cycleLabel = $cycle === BillingCycle::Annual ? 'pago anual' : 'pago mensual';
        $pdf->MultiCell(178, 4.5, $pdf->latin('Ciclo: '.$cycleLabel.'. Valores de catálogo calculados con descuentos de plan y combinación.'), 0, 'L');
        $pdf->Ln(2);

        $widths = [40, 22, 28, 30, 28, 30];
        $pdf->tableHeader(['Producto', 'Cant.', 'Plan', 'Lista / mes', 'Catálogo', 'A pagar'], $widths);

        foreach ($quote['lines'] as $line) {
            $pdf->ensureSpace(10);
            $pdf->Cell($widths[0], 6, $pdf->latin($line['label']), 1, 0, 'L');
            $pdf->Cell($widths[1], 6, $pdf->latin((string) $line['seats']), 1, 0, 'C');
            $pdf->Cell($widths[2], 6, $pdf->latin((string) ($line['metal_label'] ?? '—')), 1, 0, 'C');
            $pdf->Cell($widths[3], 6, $pdf->latin($pdf->money((float) $line['list_monthly'])), 1, 0, 'R');
            $pdf->Cell($widths[4], 6, $pdf->latin($pdf->money((float) $line['plan_monthly'])), 1, 0, 'R');
            $pdf->Cell($widths[5], 6, $pdf->latin($pdf->money((float) $line['amount'])), 1, 1, 'R');
        }

        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(120, 6, $pdf->latin('Total catálogo ('.$cycleLabel.')'), 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(58, 6, $pdf->latin($pdf->money((float) $quote['amount'])), 0, 1, 'R');

        $pdf->Ln(2);
        $pdf->SetFillColor(16, 185, 129);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(120, 9, $pdf->latin('Total comercial de esta cotización'), 1, 0, 'L', true);
        $pdf->Cell(58, 9, $pdf->latin($pdf->money((float) $data['commercial_amount'])), 1, 1, 'R', true);

        if (filled($data['commercial_note'] ?? null)) {
            $pdf->Ln(2);
            $pdf->SetTextColor(71, 85, 105);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->MultiCell(178, 4.5, $pdf->latin('Nota comercial: '.$data['commercial_note']), 0, 'L');
        }

        $pdf->Ln(3);
        $pdf->section('Conclusiones', $data['conclusions']);
        $pdf->presenterBlock(
            $data['presenter_name'],
            $data['presenter_role'],
            $data['presenter_phone'],
            $data['presenter_email'],
        );

        return $pdf->Output('S');
    }
}
