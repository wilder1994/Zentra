<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Services\Access\LookupPorteriaSubjectService;
use App\Services\Access\ManageCorrespondenceService;
use App\Support\Access\DataUrlToUploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CorrespondenceController extends Controller
{
    public function index(Request $request, LookupPorteriaSubjectService $lookup): View
    {
        $tab = (string) $request->query('tab', 'pendientes');
        if (! in_array($tab, ['pendientes', 'entregados', 'anulados'], true)) {
            $tab = 'pendientes';
        }
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : today();

        $status = match ($tab) {
            'entregados' => 'delivered',
            'anulados' => 'voided',
            default => 'pending',
        };

        $query = Correspondence::query()
            ->with(['structure', 'structureMember', 'housingUnit', 'resident', 'host', 'receiver', 'deliverer', 'deliveredToMember', 'location'])
            ->where('status', $status);

        if ($status !== 'pending') {
            $dateColumn = $status === 'delivered' ? 'delivered_at' : 'voided_at';
            $query->whereDate($dateColumn, '>=', $from)->whereDate($dateColumn, '<=', $to)->latest($dateColumn);
        } else {
            $query->oldest('received_at');
        }

        if (array_key_exists($type, Correspondence::TYPES)) {
            $query->where('package_type', $type);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('courier_guide', 'like', "%{$q}%")
                    ->orWhere('carrier', 'like', "%{$q}%")
                    ->orWhere('recipient_text', 'like', "%{$q}%")
                    ->orWhereHas('structure', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('structureMember', fn ($m) => $m->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('document_number', 'like', "%{$q}%"));
            });
        }

        $pendingByUnit = Correspondence::query()
            ->where('status', 'pending')
            ->whereNotNull('structure_id')
            ->selectRaw('structure_id, COUNT(*) as c')
            ->groupBy('structure_id')
            ->pluck('c', 'structure_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        return view('modules.access.correspondence.index', [
            'items' => $query->paginate(32)->withQueryString(),
            'tab' => $tab,
            'q' => $q,
            'type' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'pendingByUnit' => $pendingByUnit,
            'pendingTotal' => Correspondence::query()->where('status', 'pending')->count(),
            'canVoid' => (bool) $request->user()?->can('access.view.audit'),
        ]);
    }

    public function create(LookupPorteriaSubjectService $lookup): View
    {
        $carriers = Correspondence::query()
            ->whereNotNull('carrier')
            ->select('carrier')
            ->distinct()
            ->orderBy('carrier')
            ->limit(50)
            ->pluck('carrier');

        return view('modules.access.correspondence.create', [
            'nodes' => $lookup->nodes(),
            'carriers' => $carriers,
        ]);
    }

    public function members(Request $request, LookupPorteriaSubjectService $lookup): JsonResponse
    {
        $structureId = $request->integer('structure_id');

        return response()->json(['members' => $structureId > 0 ? $lookup->hostsForNode($structureId) : []]);
    }

    public function store(Request $request, ManageCorrespondenceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'structure_id' => 'nullable|integer',
            'structure_member_id' => 'nullable|integer',
            'recipient_text' => 'nullable|string|max:150',
            'package_type' => ['required', Rule::in(array_keys(Correspondence::TYPES))],
            'carrier' => 'nullable|string|max:100',
            'courier_guide' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'quantity' => 'nullable|integer|min:1|max:20',
            'package_photo_data' => 'nullable|string',
        ]);

        $photo = DataUrlToUploadedFile::make($validated['package_photo_data'] ?? null, 'paquete');
        $created = $service->register($request->user(), $validated, $photo);

        return redirect()->route('access.correspondence.index')
            ->with('success', $created->count() > 1 ? $created->count().' paquetes registrados.' : 'Paquete registrado.');
    }

    public function show(Correspondence $correspondence): View
    {
        $correspondence->load(['structure', 'structureMember', 'housingUnit', 'resident', 'host', 'receiver', 'deliverer', 'deliveredToMember', 'voider', 'location']);

        return view('modules.access.correspondence.show', [
            'item' => $correspondence,
            'canVoid' => (bool) auth()->user()?->can('access.view.audit'),
        ]);
    }

    public function markDelivered(Request $request, Correspondence $correspondence, ManageCorrespondenceService $service): RedirectResponse
    {
        $validated = $this->deliveryInput($request);
        $photo = DataUrlToUploadedFile::make($validated['delivery_photo_data'] ?? null, 'entrega');
        $service->deliver($request->user(), $correspondence, $validated, $photo);

        return back()->with('success', 'Paquete entregado.');
    }

    public function deliverUnit(Request $request, ManageCorrespondenceService $service): RedirectResponse
    {
        $validated = $this->deliveryInput($request, true);
        $photo = DataUrlToUploadedFile::make($validated['delivery_photo_data'] ?? null, 'entrega');
        $count = $service->deliverUnit($request->user(), (int) $validated['structure_id'], $validated, $photo);

        return back()->with('success', $count.' paquetes entregados.');
    }

    public function void(Request $request, Correspondence $correspondence, ManageCorrespondenceService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('access.view.audit'), 403);
        $validated = $request->validate(['void_reason' => 'required|string|max:255'], [
            'void_reason.required' => 'Escribe el motivo de la anulación.',
        ]);
        $service->void($request->user(), $correspondence, $validated['void_reason']);

        return redirect()->route('access.correspondence.index')->with('success', 'Paquete anulado.');
    }

    /** @return array<string, mixed> */
    private function deliveryInput(Request $request, bool $withUnit = false): array
    {
        return $request->validate(array_filter([
            'structure_id' => $withUnit ? 'required|integer' : null,
            'delivered_to_member_id' => 'nullable|integer',
            'delivered_to_name' => 'nullable|string|max:150',
            'delivered_to_document' => 'nullable|string|max:50',
            'delivery_photo_data' => 'nullable|string',
        ]));
    }
}
