<?php

declare(strict_types=1);

namespace App\Services\Supervision;

use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorFieldSheetKind;
use App\Mail\SupervisionNoveltyMail;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShiftReview;
use App\Services\Company\BuildSupervisorFieldSheetService;
use App\Support\Supervision\ClientResponsibles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Correo a los responsables del cliente solo cuando hay novedad: uno por revista (con todas sus
 * novedades) y uno por alarma o apoyo. Documentos no se envían.
 */
final class NotifySupervisionNoveltyService
{
    private const SHEET_LINK_DAYS = 30;

    public function __construct(
        private readonly ClientResponsibles $responsibles,
        private readonly BuildSupervisorFieldSheetService $sheets,
    ) {}

    public function review(SupervisorShiftReview $review): void
    {
        $review->loadMissing([
            'client:id,name',
            'zone:id,name',
            'supervisorPost:id,name,installation_id',
            'supervisorPost.installation:id,name',
            'employee:id,first_names,last_name_paternal,last_name_maternal',
            'shift.user:id,name',
            'shift.securityCompany',
            'fieldLogs',
        ]);

        $novelties = $review->fieldLogs
            ->filter(fn (SupervisorFieldLog $log) => $log->outcome !== null && $log->outcome !== SupervisorFieldOutcome::Ok)
            ->values();
        $note = $review->has_novelty ? (trim((string) $review->notes) ?: 'Sin observación escrita.') : null;
        if ($note === null && $novelties->isEmpty()) {
            return;
        }

        $place = trim(($review->supervisorPost?->installation?->name ?? '').' · '.($review->supervisorPost?->name ?? ''), ' ·');
        $client = (string) ($review->client?->name ?? 'Cliente');
        $company = $review->shift?->securityCompany;

        $this->send(
            (int) $review->client_id,
            new SupervisionNoveltyMail(
                subjectLine: 'Novedad en revista · '.$client.($place !== '' ? ' · '.$place : ''),
                heading: 'Revista con novedad',
                companyName: (string) ($company?->displayName() ?? 'Zentra'),
                facts: $this->facts([
                    'Cliente' => $client,
                    'Puesto' => $place,
                    'Zona' => $review->zone?->name,
                    'Supervisor' => $review->shift?->user?->name,
                    'Vigilante' => $review->employee?->fullName(),
                    'Fecha' => $review->recorded_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
                ]),
                generalNote: $note,
                sections: $novelties->map(fn (SupervisorFieldLog $log) => $this->sheets->textSection($log))->values()->all(),
                sheetUrl: $this->sheetUrl((int) ($company?->id ?? 0), SupervisorFieldSheetKind::Review, (int) $review->id),
            ),
        );
    }

    public function log(SupervisorFieldLog $log): void
    {
        if (! in_array($log->module, [SupervisorFieldModule::Alarms, SupervisorFieldModule::Supports], true)
            || $log->client_id === null
            || $log->outcome === null
            || $log->outcome === SupervisorFieldOutcome::Ok) {
            return;
        }

        $log->loadMissing(['client:id,name', 'zone:id,name', 'user:id,name', 'company']);
        $kind = SupervisorFieldSheetKind::fromStandaloneModule($log->module);
        $title = $log->module === SupervisorFieldModule::Alarms ? 'Alarma con novedad' : 'Apoyo con novedad';
        $client = (string) ($log->client?->name ?? 'Cliente');
        $section = $this->sheets->textSection($log);

        $this->send(
            (int) $log->client_id,
            new SupervisionNoveltyMail(
                subjectLine: $title.' · '.$client,
                heading: $title,
                companyName: (string) ($log->company?->displayName() ?? 'Zentra'),
                facts: $this->facts([
                    'Cliente' => $client,
                    'Zona' => $log->zone?->name,
                    'Supervisor' => $log->user?->name,
                    'Fecha' => $log->recorded_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
                ]),
                generalNote: trim((string) $log->notes) ?: null,
                sections: [$section],
                sheetUrl: $kind !== null ? $this->sheetUrl((int) $log->security_company_id, $kind, (int) $log->id) : null,
            ),
        );
    }

    private function send(int $clientId, SupervisionNoveltyMail $mail): void
    {
        try {
            foreach ($this->responsibles->emails($clientId) as $email) {
                Mail::to($email)->queue(clone $mail);
            }
        } catch (Throwable $e) {
            Log::warning('Correo de novedad no encolado', ['client_id' => $clientId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, ?string>  $pairs
     * @return list<array{label: string, value: string}>
     */
    private function facts(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $label => $value) {
            if ($value !== null && trim($value) !== '') {
                $out[] = ['label' => $label, 'value' => $value];
            }
        }

        return $out;
    }

    private function sheetUrl(int $companyId, SupervisorFieldSheetKind $kind, int $id): ?string
    {
        if ($companyId < 1) {
            return null;
        }

        return URL::temporarySignedRoute('supervision.sheets.signed', now()->addDays(self::SHEET_LINK_DAYS), [
            'company' => $companyId,
            'kind' => $kind->value,
            'id' => $id,
        ]);
    }
}
