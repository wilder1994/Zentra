<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Blocklist;
use App\Models\Resident;
use App\Models\StructureMember;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Services\Access\AuditLogger;
use App\Services\Access\BlocklistGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlocklistController extends Controller
{
    public function __construct(
        private readonly BlocklistGuard $blocklistGuard,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $entries = Blocklist::query()
            ->with(['blocker', 'blockable'])
            ->active()
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('document_number', 'like', $like)
                        ->orWhere('plate', 'like', $like)
                        ->orWhere('display_name', 'like', $like)
                        ->orWhere('reason', 'like', $like);
                });
            })
            ->latest('blocked_at')
            ->paginate(20)
            ->withQueryString();

        return view('modules.access.blocklist.index', compact('entries', 'q'));
    }

    public function create(): View
    {
        return view('modules.access.blocklist.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => 'required|in:person,vehicle',
            'document_number' => 'nullable|string|max:40',
            'plate' => 'nullable|string|max:20',
            'reason' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'blockable_type' => 'nullable|in:visitor,vehicle,resident',
            'blockable_id' => 'nullable|integer',
            'display_name' => 'nullable|string|max:160',
        ]);

        $kind = $validated['kind'];
        $document = $kind === 'person'
            ? $this->blocklistGuard->normalizeDocument($validated['document_number'] ?? null)
            : null;
        $plate = $kind === 'vehicle'
            ? $this->blocklistGuard->normalizePlate($validated['plate'] ?? null)
            : null;

        if ($kind === 'person' && $document === null) {
            throw ValidationException::withMessages(['document_number' => 'Indica la cédula o documento a bloquear.']);
        }
        if ($kind === 'vehicle' && $plate === null) {
            throw ValidationException::withMessages(['plate' => 'Indica la placa a bloquear.']);
        }

        $duplicate = $kind === 'person'
            ? $this->blocklistGuard->checkPerson(documentNumber: $document)
            : $this->blocklistGuard->checkVehicle(plate: $plate);

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                $kind === 'person' ? 'document_number' : 'plate' => 'Ya existe un bloqueo activo para este identificador.',
            ]);
        }

        $resolved = $kind === 'person'
            ? $this->resolvePerson($document, $validated)
            : $this->resolveVehicle($plate, $validated);

        $entry = Blocklist::create([
            'client_id' => session('active_client_id'),
            'document_number' => $document,
            'plate' => $plate,
            'display_name' => $resolved['display_name'],
            'blockable_type' => $resolved['blockable_type'],
            'blockable_id' => $resolved['blockable_id'],
            'reason' => $validated['reason'],
            'blocked_by' => auth()->id(),
            'blocked_at' => now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        app(AuditLogger::class)->record($entry, 'blocklist.create', null, [
            'kind' => $kind,
            'document_number' => $entry->document_number,
            'plate' => $entry->plate,
            'reason' => $entry->reason,
        ]);

        return redirect()->route('access.blocklist.index')
            ->with('success', 'Bloqueo registrado.');
    }

    public function searchJson(Request $request): JsonResponse
    {
        $type = $request->get('type', 'person');
        $query = trim((string) $request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clientId = (int) session('active_client_id');

        if ($type === 'vehicle') {
            $plate = strtoupper($query);

            return response()->json(
                Vehicle::query()
                    ->where('client_id', $clientId)
                    ->where('plate', 'like', "%{$plate}%")
                    ->limit(10)
                    ->get(['id', 'plate', 'brand', 'model', 'color'])
                    ->map(fn (Vehicle $v): array => [
                        'id' => $v->id,
                        'blockable_type' => Blocklist::TYPE_VEHICLE,
                        'plate' => $v->plate,
                        'label' => trim($v->plate.' · '.($v->brand ?? '').' '.($v->model ?? '')),
                        'display_name' => $v->plate,
                    ])
            );
        }

        $members = StructureMember::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->where(function ($q) use ($query): void {
                $q->where('document_number', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->limit(8)
            ->get(['id', 'document_type', 'document_number', 'first_name', 'last_name'])
            ->map(fn (StructureMember $m): array => [
                'id' => null,
                'blockable_type' => null,
                'document_number' => $m->document_number,
                'label' => trim($m->first_name.' '.$m->last_name).' · '.$m->document_number.' (censo)',
                'display_name' => trim($m->first_name.' '.$m->last_name),
            ]);

        $visitors = Visitor::query()
            ->where('client_id', $clientId)
            ->where(function ($q) use ($query): void {
                $q->where('document_number', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->limit(6)
            ->get(['id', 'document_type', 'document_number', 'first_name', 'last_name'])
            ->map(fn (Visitor $v): array => [
                'id' => $v->id,
                'blockable_type' => Blocklist::TYPE_VISITOR,
                'document_number' => $v->document_number,
                'label' => trim($v->first_name.' '.$v->last_name).' · '.$v->document_number.' (visitante)',
                'display_name' => trim($v->first_name.' '.$v->last_name),
            ]);

        $residents = Resident::query()
            ->where('client_id', $clientId)
            ->where(function ($q) use ($query): void {
                $q->where('document_number', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->limit(4)
            ->get(['id', 'document_type', 'document_number', 'first_name', 'last_name'])
            ->map(fn (Resident $r): array => [
                'id' => $r->id,
                'blockable_type' => Blocklist::TYPE_RESIDENT,
                'document_number' => $r->document_number,
                'label' => trim($r->first_name.' '.$r->last_name).' · '.$r->document_number.' (residente)',
                'display_name' => trim($r->first_name.' '.$r->last_name),
            ]);

        return response()->json($members->concat($visitors)->concat($residents)->values());
    }

    public function destroy(Blocklist $blocklist): RedirectResponse
    {
        $blocklist->update(['is_active' => false]);

        app(AuditLogger::class)->record($blocklist, 'blocklist.remove', [
            'is_active' => true,
        ], [
            'is_active' => false,
            'reason' => $blocklist->reason,
        ]);

        return redirect()->route('access.blocklist.index')
            ->with('success', 'Bloqueo removido.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{display_name: ?string, blockable_type: ?string, blockable_id: ?int}
     */
    private function resolvePerson(string $document, array $validated): array
    {
        $display = filled($validated['display_name'] ?? null) ? trim((string) $validated['display_name']) : null;
        $type = $validated['blockable_type'] ?? null;
        $id = isset($validated['blockable_id']) ? (int) $validated['blockable_id'] : null;

        if (in_array($type, [Blocklist::TYPE_VISITOR, Blocklist::TYPE_RESIDENT], true) && $id > 0) {
            $model = $type === Blocklist::TYPE_VISITOR
                ? Visitor::query()->find($id)
                : Resident::query()->find($id);
            if ($model !== null) {
                return [
                    'display_name' => $display ?: trim(($model->first_name ?? '').' '.($model->last_name ?? '')),
                    'blockable_type' => $type,
                    'blockable_id' => $id,
                ];
            }
        }

        $member = StructureMember::query()
            ->where('document_number', $document)
            ->where('is_active', true)
            ->first();
        if ($member !== null) {
            return [
                'display_name' => $display ?: $member->full_name,
                'blockable_type' => null,
                'blockable_id' => null,
            ];
        }

        $visitor = Visitor::query()->where('document_number', $document)->first();
        if ($visitor !== null) {
            return [
                'display_name' => $display ?: $visitor->full_name,
                'blockable_type' => Blocklist::TYPE_VISITOR,
                'blockable_id' => (int) $visitor->id,
            ];
        }

        $resident = Resident::query()->where('document_number', $document)->first();
        if ($resident !== null) {
            return [
                'display_name' => $display ?: trim($resident->first_name.' '.$resident->last_name),
                'blockable_type' => Blocklist::TYPE_RESIDENT,
                'blockable_id' => (int) $resident->id,
            ];
        }

        return [
            'display_name' => $display,
            'blockable_type' => null,
            'blockable_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{display_name: ?string, blockable_type: ?string, blockable_id: ?int}
     */
    private function resolveVehicle(string $plate, array $validated): array
    {
        $display = filled($validated['display_name'] ?? null) ? trim((string) $validated['display_name']) : null;
        $id = isset($validated['blockable_id']) ? (int) $validated['blockable_id'] : null;

        if (($validated['blockable_type'] ?? null) === Blocklist::TYPE_VEHICLE && $id > 0) {
            $vehicle = Vehicle::query()->find($id);
            if ($vehicle !== null) {
                return [
                    'display_name' => $display ?: $vehicle->plate,
                    'blockable_type' => Blocklist::TYPE_VEHICLE,
                    'blockable_id' => $id,
                ];
            }
        }

        $vehicle = Vehicle::query()->where('plate', $plate)->first();
        if ($vehicle !== null) {
            return [
                'display_name' => $display ?: $vehicle->plate,
                'blockable_type' => Blocklist::TYPE_VEHICLE,
                'blockable_id' => (int) $vehicle->id,
            ];
        }

        return [
            'display_name' => $display ?: $plate,
            'blockable_type' => null,
            'blockable_id' => null,
        ];
    }
}
