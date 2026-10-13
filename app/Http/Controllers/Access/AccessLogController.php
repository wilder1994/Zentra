<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\Access\LookupPorteriaSubjectService;
use App\Services\Access\PorteriaDoorService;
use App\Services\Access\RegisterPorteriaMovementService;
use App\Support\Access\DataUrlToUploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessLogController extends Controller
{
    public function index(Request $request, LookupPorteriaSubjectService $lookup, PorteriaDoorService $doors): View
    {
        $from = $request->filled('from') ? $request->date('from') : today();
        $to = $request->filled('to') ? $request->date('to') : $from;
        $scope = (string) $request->query('scope', 'all');
        $hostId = $request->integer('host_id');
        $q = trim((string) $request->query('q', ''));
        $tab = (string) $request->query('tab', 'movimiento');

        $logsQuery = AccessLog::query()
            ->with(['visitor', 'structureMember', 'vehicle', 'host', 'destinationStructure', 'authorizedMember', 'location'])
            ->whereDate('entry_time', '>=', $from)
            ->whereDate('entry_time', '<=', $to)
            ->latest('entry_time');

        if ($scope === 'people') {
            $logsQuery->whereNull('vehicle_id');
        } elseif ($scope === 'vehicles') {
            $logsQuery->whereNotNull('vehicle_id');
        }

        if ($hostId > 0) {
            $logsQuery->where('host_id', $hostId);
        }

        if ($q !== '') {
            $logsQuery->where(function ($query) use ($q): void {
                $query->whereHas('visitor', function ($v) use ($q): void {
                    $v->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('document_number', 'like', "%{$q}%");
                })->orWhereHas('structureMember', function ($m) use ($q): void {
                    $m->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('document_number', 'like', "%{$q}%");
                })->orWhereHas('vehicle', fn ($veh) => $veh->where('plate', 'like', "%{$q}%"));
            });
        }

        $guards = User::query()
            ->whereIn('id', AccessLog::query()->whereNotNull('host_id')->select('host_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('modules.access.logs.index', [
            'tab' => in_array($tab, ['movimiento', 'registros'], true) ? $tab : 'movimiento',
            'nodes' => $lookup->nodes(),
            'memberTypes' => $lookup->memberTypes(),
            'door' => $doors->operatingOrFirst($request),
            'logs' => $logsQuery->paginate(30)->withQueryString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $scope,
            'hostId' => $hostId,
            'q' => $q,
            'guards' => $guards,
        ]);
    }

    public function lookup(Request $request, LookupPorteriaSubjectService $lookup): JsonResponse
    {
        return response()->json($lookup->search((string) $request->query('q', '')));
    }

    public function hosts(Request $request, LookupPorteriaSubjectService $lookup): JsonResponse
    {
        return response()->json([
            'hosts' => $lookup->hostsForNode($request->integer('structure_id')),
        ]);
    }

    public function entry(): RedirectResponse
    {
        return redirect()->route('access.logs.index', ['tab' => 'movimiento']);
    }

    public function storeEntry(Request $request, RegisterPorteriaMovementService $register): RedirectResponse
    {
        $validated = $this->movementPayload($request);
        $register->enter(
            $request->user(),
            $validated,
            DataUrlToUploadedFile::make($validated['person_photo_data'] ?? null, 'persona'),
            DataUrlToUploadedFile::make($validated['vehicle_photo_data'] ?? null, 'vehiculo'),
        );

        return redirect()->route('access.logs.index', ['tab' => 'movimiento'])->with('success', 'Ingreso registrado.');
    }

    public function storeMove(Request $request, RegisterPorteriaMovementService $register): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => 'required|in:member,visitor,vehicle',
            'id' => 'required|integer',
            'action' => 'required|in:enter,exit',
            'destination_structure_id' => 'nullable|integer',
            'destination_text' => 'nullable|string|max:255',
            'authorized_member_id' => 'nullable|integer',
            'authorized_text' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'person_photo_data' => 'nullable|string',
        ]);

        $log = $register->move(
            $request->user(),
            $validated,
            DataUrlToUploadedFile::make($validated['person_photo_data'] ?? null, 'ficha'),
        );
        $msg = $validated['action'] === 'exit' ? 'Salida registrada.' : 'Ingreso registrado.';

        return redirect()
            ->route('access.logs.index', ['tab' => 'movimiento'])
            ->with('success', $msg)
            ->with('moved_log_id', $log->id);
    }

    public function storeRegister(Request $request, RegisterPorteriaMovementService $register): RedirectResponse
    {
        $validated = $this->movementPayload($request);
        $alsoEnter = $request->boolean('also_enter', true);
        $created = $register->register(
            $validated,
            DataUrlToUploadedFile::make($validated['person_photo_data'] ?? $validated['vehicle_photo_data'] ?? null, 'ficha'),
            requirePhoto: $validated['subject_kind'] === 'visitor'
                || ($validated['subject_kind'] === 'vehicle' && empty($validated['structure_id'])),
        );

        if ($alsoEnter && $validated['subject_kind'] !== 'member') {
            $register->move($request->user(), [
                'kind' => $created['kind'],
                'id' => $created['id'],
                'action' => 'enter',
                'destination_structure_id' => $validated['destination_structure_id'] ?? null,
                'destination_text' => $validated['destination_text'] ?? null,
                'authorized_member_id' => $validated['authorized_member_id'] ?? null,
                'authorized_text' => $validated['authorized_text'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()
                ->route('access.logs.index', ['tab' => 'movimiento'])
                ->with('success', 'Ficha registrada e ingreso anotado.');
        }

        return redirect()
            ->route('access.logs.index', [
                'tab' => 'movimiento',
                'registered_kind' => $created['kind'],
                'registered_id' => $created['id'],
                'q' => $request->input('document_number') ?: $request->input('plate') ?: $request->input('first_name'),
            ])
            ->with('success', 'Ficha creada. Revisa la tarjeta e ingresa si corresponde.');
    }

    public function exitPage(): RedirectResponse
    {
        return redirect()->route('access.logs.index', ['tab' => 'registros']);
    }

    public function markExit(Request $request, AccessLog $accessLog): RedirectResponse
    {
        if ($accessLog->status !== 'active') {
            return back()->with('error', 'Este registro ya tiene salida.');
        }

        $accessLog->update([
            'exit_time' => now(),
            'status' => 'completed',
            'has_custody' => $request->boolean('has_custody'),
            'custody_description' => $request->input('custody_description'),
            'custody_receiver_name' => $request->input('custody_receiver_name'),
            'custody_received_at' => $request->boolean('has_custody') ? now() : null,
        ]);

        return back()->with('success', 'Salida registrada.');
    }

    public function scanExit(Request $request): JsonResponse
    {
        if ($request->filled('log_id')) {
            $log = AccessLog::query()->whereKey($request->integer('log_id'))->where('status', 'active')->first();
            if ($log === null) {
                return response()->json(['error' => 'Ingreso no encontrado.'], 404);
            }

            $log->update([
                'exit_time' => now(),
                'status' => 'completed',
            ]);

            return response()->json(['found' => true, 'message' => 'Salida registrada.']);
        }

        return response()->json(['error' => 'Usa Ingreso y salida → Movimiento.'], 422);
    }

    public function bulkExit(): RedirectResponse
    {
        AccessLog::query()->where('status', 'active')->update([
            'exit_time' => now(),
            'status' => 'completed',
        ]);

        return redirect()->route('access.logs.index', ['tab' => 'registros'])->with('success', 'Salida masiva registrada.');
    }

    /** @return array<string, mixed> */
    private function movementPayload(Request $request): array
    {
        $validated = $request->validate([
            'subject_kind' => 'required|in:member,visitor,vehicle',
            'with_vehicle' => 'nullable|boolean',
            'member_id' => 'nullable|integer',
            'visitor_id' => 'nullable|integer',
            'vehicle_id' => 'nullable|integer',
            'structure_id' => 'nullable|integer',
            'member_type_id' => 'nullable|integer',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'document_type' => 'nullable|string|max:20',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'company' => 'nullable|string|max:150',
            'visitor_type' => 'nullable|in:persona,contratista,proveedor,domiciliario,servicio',
            'nationality' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'plate' => 'nullable|string|max:20',
            'vehicle_brand' => 'nullable|string|max:80',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_type' => 'nullable|string|max:40',
            'purpose' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'person_photo_data' => 'nullable|string',
            'vehicle_photo_data' => 'nullable|string',
            'destination_structure_id' => 'nullable|integer',
            'destination_text' => 'nullable|string|max:255',
            'authorized_member_id' => 'nullable|integer',
            'authorized_text' => 'nullable|string|max:255',
            'also_enter' => 'nullable|boolean',
        ]);
        $validated['with_vehicle'] = $request->boolean('with_vehicle');

        return $validated;
    }
}
