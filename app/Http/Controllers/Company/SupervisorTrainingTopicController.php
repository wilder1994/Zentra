<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorTrainingTopic;
use App\Services\Supervision\ResolveTrainingTopicService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SupervisorTrainingTopicController extends Controller
{
    public function __construct(
        private readonly ResolveTrainingTopicService $topics,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupervisorTrainingTopic::class);

        $companyId = $this->companyId($request);
        $filter = in_array($request->query('filter'), ['app', 'inactive'], true) ? (string) $request->query('filter') : 'all';
        $base = SupervisorTrainingTopic::query()->where('security_company_id', $companyId);
        $counts = [
            'all' => (clone $base)->count(),
            'app' => (clone $base)->where('created_from', SupervisorTrainingTopic::FROM_APP)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
        ];

        $topics = $base
            ->when($filter === 'app', fn ($q) => $q->where('created_from', SupervisorTrainingTopic::FROM_APP))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('trainings')
            ->with('createdBy:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('modules.company.supervision-catalogs.training-topics', compact('topics', 'filter', 'counts'));
    }

    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $normalized = SupervisorTrainingTopic::normalize((string) $request->validated('name'));
        if (SupervisorTrainingTopic::query()->where('security_company_id', $companyId)->where('normalized_name', $normalized)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un tema igual (sin contar tildes ni mayúsculas).']);
        }

        $topic = $this->topics->byName($companyId, (string) $request->validated('name'), $request->user(), SupervisorTrainingTopic::FROM_PANEL);
        if (! $request->boolean('is_active', true)) {
            $topic->update(['is_active' => false]);
        }

        return redirect()->route('company.supervision-training-topics.index')->with('success', 'Tema creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorTrainingTopic $trainingTopic): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless((int) $trainingTopic->security_company_id === $companyId, 404);

        $name = SupervisorTrainingTopic::clean((string) $request->validated('name'));
        $normalized = SupervisorTrainingTopic::normalize($name);
        $clash = SupervisorTrainingTopic::query()
            ->where('security_company_id', $companyId)
            ->where('normalized_name', $normalized)
            ->whereKeyNot($trainingTopic->id)
            ->exists();
        if ($clash) {
            throw ValidationException::withMessages(['name' => 'Ya existe un tema igual (sin contar tildes ni mayúsculas).']);
        }

        $trainingTopic->update([
            'name' => $name,
            'normalized_name' => $normalized,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back(fallback: route('company.supervision-training-topics.index'))->with('success', 'Tema actualizado.');
    }

    public function destroy(Request $request, SupervisorTrainingTopic $trainingTopic): RedirectResponse
    {
        abort_unless((int) $trainingTopic->security_company_id === $this->companyId($request), 404);
        if ($trainingTopic->trainings()->exists()) {
            return redirect()->route('company.supervision-training-topics.index')
                ->with('error', 'Este tema ya tiene capacitaciones. Desactívelo para que no salga en la app.');
        }
        $trainingTopic->delete();

        return redirect()->route('company.supervision-training-topics.index')->with('success', 'Tema eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
