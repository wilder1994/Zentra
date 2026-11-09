<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorTrainingTopic;
use App\Services\Supervision\ResolveTrainingTopicService;
use App\Support\Company\SettingsCatalog;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SupervisorTrainingTopicController extends Controller
{
    public function __construct(
        private readonly ResolveTrainingTopicService $topics,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorTrainingTopic::class);

        return SettingsCatalog::hubRedirect('temas');
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
