<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Services\Access\BlocklistGuard;
use App\Support\Access\DataUrlToUploadedFile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->string('tab')->toString() === 'vehiculos' ? 'vehiculos' : 'personas';
        $visitors = Visitor::latest()->paginate(15)->withQueryString();
        $vehicles = Vehicle::query()
            ->with('visitor')
            ->where(function ($q) {
                $q->where('is_visitor_vehicle', true)->orWhereNotNull('visitor_id');
            })
            ->whereNull('structure_id')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.access.visitors.index', compact('visitors', 'vehicles', 'tab'));
    }

    public function create()
    {
        return view('modules.access.visitors.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:CC,NIT,CE,Pasaporte',
            'document_number' => [
                'required', 'string', 'max:50',
                Rule::unique('visitors')
                    ->whereNull('deleted_at')
                    ->where('document_type', $request->document_type),
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'nationality' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:150',
            'visitor_type' => 'required|in:persona,contratista,proveedor,domiciliario,servicio',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'person_photo_data' => 'nullable|string',
        ]);

        $photo = DataUrlToUploadedFile::make($validated['person_photo_data'] ?? null, 'visitante');
        unset($validated['person_photo_data']);

        $existing = Visitor::withTrashed()
            ->where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->update($validated);
            if ($photo !== null) {
                $existing->update(['photo_path' => $photo->store('porteria/personas', 'public')]);
            }

            return redirect()->route('access.visitors.index')
                ->with('success', 'Visitante restaurado exitosamente.');
        }

        $visitor = Visitor::create($validated);
        if ($photo !== null) {
            $visitor->update(['photo_path' => $photo->store('porteria/personas', 'public')]);
        }

        return redirect()->route('access.visitors.index')
            ->with('success', 'Visitante creado exitosamente.');
    }

    public function show(Visitor $visitor)
    {
        $visitor->load(['vehicles', 'accessLogs' => fn ($q) => $q->latest()->take(20), 'documents']);

        return view('modules.access.visitors.show', compact('visitor'));
    }

    public function edit(Visitor $visitor)
    {
        return view('modules.access.visitors.edit', compact('visitor'));
    }

    public function update(Request $request, Visitor $visitor)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:CC,NIT,CE,Pasaporte',
            'document_number' => [
                'required', 'string', 'max:50',
                Rule::unique('visitors')
                    ->ignore($visitor->id)
                    ->whereNull('deleted_at')
                    ->where('document_type', $request->document_type),
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'nationality' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:150',
            'visitor_type' => 'required|in:persona,contratista,proveedor,domiciliario,servicio',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'person_photo_data' => 'nullable|string',
        ]);

        $photo = DataUrlToUploadedFile::make($validated['person_photo_data'] ?? null, 'visitante');
        unset($validated['person_photo_data']);
        $visitor->update($validated);
        if ($photo !== null) {
            $visitor->update(['photo_path' => $photo->store('porteria/personas', 'public')]);
        }

        return redirect()->route('access.visitors.index')
            ->with('success', 'Visitante actualizado exitosamente.');
    }

    public function destroy(Visitor $visitor)
    {
        $visitor->delete();

        return redirect()->route('access.visitors.index')
            ->with('success', 'Visitante eliminado exitosamente.');
    }

    public function searchJson(Request $request)
    {
        $query = $request->get('q');
        $visitors = Visitor::where('document_number', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->take(10)
            ->get(['id', 'document_type', 'document_number', 'first_name', 'last_name', 'company', 'birth_date'])
            ->map(fn (Visitor $visitor) => $visitor->porteriaIdentity());

        return response()->json($visitors);
    }

    public function scanRegister(Request $request)
    {
        $validated = $request->validate([
            'document_number' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
        ]);

        $blocked = function (Visitor $visitor): ?array {
            $entry = app(BlocklistGuard::class)->checkPerson($visitor);

            if ($entry === null) {
                return null;
            }

            return [
                'blocked' => true,
                'reason' => $entry->reason,
                'message' => '🚫 Acceso denegado por lista de bloqueo.',
                'visitor' => $visitor->porteriaIdentity(),
            ];
        };

        $visitor = Visitor::whereNull('deleted_at')
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($visitor) {
            $fields = $blocked($visitor);

            if ($fields !== null) {
                return response()->json($fields);
            }

            return response()->json([
                'found' => true,
                'visitor' => $visitor->porteriaIdentity(),
            ]);
        }

        $existingSoftDeleted = Visitor::withTrashed()
            ->where('document_number', $validated['document_number'])
            ->first();

        if ($existingSoftDeleted) {
            $existingSoftDeleted->restore();
            $existingSoftDeleted->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? '',
                'birth_date' => $validated['birth_date'] ?? null,
            ]);

            $fields = $blocked($existingSoftDeleted);

            if ($fields !== null) {
                return response()->json($fields);
            }

            return response()->json([
                'found' => false,
                'visitor' => $existingSoftDeleted->only(['id', 'document_type', 'document_number', 'first_name', 'last_name', 'company']),
            ]);
        }

        $visitor = Visitor::create([
            'document_type' => 'CC',
            'document_number' => $validated['document_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? '',
            'visitor_type' => 'persona',
            'birth_date' => $validated['birth_date'] ?? null,
        ]);

        $fields = $blocked($visitor);

        if ($fields !== null) {
            return response()->json($fields);
        }

        return response()->json([
            'found' => false,
            'visitor' => $visitor->porteriaIdentity(),
        ]);
    }
}
