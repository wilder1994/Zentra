<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PartyType;
use App\Models\Client;
use App\Support\Client\ClientExcelSchema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ClientDataSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly int $companyId) {}

    public function title(): string
    {
        return ClientExcelSchema::DATA_SHEET;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ClientExcelSchema::headers();
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return Client::query()
            ->with('structureType')
            ->where('security_company_id', $this->companyId)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client): array => [
                $client->party_type === PartyType::NaturalPerson ? 'Natural' : 'Jurídica',
                (string) $client->name,
                (string) ($client->legal_name ?? ''),
                (string) ($client->document_type ?? ''),
                (string) ($client->tax_id ?? ''),
                (string) ($client->email ?? ''),
                (string) ($client->phone ?? ''),
                (string) ($client->representative_name ?? ''),
                (string) ($client->representative_email ?? ''),
                (string) ($client->structureType?->name ?? ''),
                (string) ($client->address ?? ''),
                (string) ($client->city ?? ''),
                (string) ($client->department ?? ''),
                $client->has_access ? 'SI' : 'NO',
                $client->has_supervision ? 'SI' : 'NO',
            ])
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
