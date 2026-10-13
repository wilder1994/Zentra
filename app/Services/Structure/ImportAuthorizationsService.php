<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Enums\AuthorizationType;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class ImportAuthorizationsService implements ToCollection, WithHeadingRow
{
    private int $clientId;

    private ?User $actor = null;

    /** @var array<int, string> */
    private array $errors = [];

    private int $imported = 0;

    public function __construct(
        private readonly ManageAuthorizationService $authorizations,
    ) {}

    public function forClient(int $clientId, ?User $actor = null): self
    {
        $this->clientId = $clientId;
        $this->actor = $actor;

        return $this;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $visitorName = trim((string) ($row['visitante'] ?? $row['visitor_name'] ?? ''));
            $structureRef = trim((string) ($row['estructura'] ?? $row['structure'] ?? $row['apartamento'] ?? $row['unidad'] ?? ''));
            $from = $this->date($row['fecha'] ?? $row['valid_for_date'] ?? $row['desde'] ?? null);
            $until = $this->date($row['hasta'] ?? $row['fecha_fin'] ?? null) ?? $from;

            if ($visitorName === '' || $structureRef === '' || $from === null) {
                $this->errors[] = "Fila {$line}: faltan campos obligatorios (visitante, unidad, fecha).";

                continue;
            }

            $structure = Structure::query()
                ->where('client_id', $this->clientId)
                ->where(fn ($q) => $q->where('code', $structureRef)->orWhere('name', $structureRef))
                ->first();
            if ($structure === null) {
                $this->errors[] = "Fila {$line}: unidad «{$structureRef}» no encontrada.";

                continue;
            }

            $hostDoc = trim((string) ($row['anfitrion_documento'] ?? $row['host_document'] ?? ''));
            $memberId = $hostDoc === '' ? null : StructureMember::query()
                ->where('client_id', $this->clientId)
                ->where('structure_id', $structure->id)
                ->where('document_number', $hostDoc)
                ->value('id');

            try {
                $this->authorizations->create($this->actor, [
                    'type' => $this->type((string) ($row['tipo'] ?? $row['categoria'] ?? $row['category'] ?? '')),
                    'structure_id' => $structure->id,
                    'member_id' => $memberId,
                    'valid_from' => $from,
                    'valid_until' => $until,
                    'notes' => trim((string) ($row['notas'] ?? $row['notes'] ?? '')) ?: null,
                    'origin' => 'import',
                    'guests' => [[
                        'first_name' => $visitorName,
                        'document_number' => trim((string) ($row['documento'] ?? $row['visitor_document'] ?? '')) ?: null,
                        'phone' => trim((string) ($row['telefono'] ?? $row['phone'] ?? '')) ?: null,
                    ]],
                ]);
                $this->imported++;
            } catch (ValidationException $e) {
                $this->errors[] = "Fila {$line}: ".collect($e->errors())->flatten()->first();
            }
        }
    }

    public function importedCount(): int
    {
        return $this->imported;
    }

    /** @return array<int, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function type(string $raw): string
    {
        return match (mb_strtolower(trim($raw))) {
            'trasteo', 'mudanza', 'move' => AuthorizationType::Move->value,
            'evento', 'event' => AuthorizationType::Event->value,
            'entrevista', 'interview' => AuthorizationType::Interview->value,
            'obra', 'contratista', 'contractor', 'work' => AuthorizationType::Work->value,
            'otro', 'other' => AuthorizationType::Other->value,
            default => AuthorizationType::Visit->value,
        };
    }
}
