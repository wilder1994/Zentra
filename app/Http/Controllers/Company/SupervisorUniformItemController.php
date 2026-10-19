<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorUniformItem;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SupervisorUniformItemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupervisorUniformItem::class);

        $items = SupervisorUniformItem::query()
            ->where('security_company_id', $this->companyId($request))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('modules.company.supervision-catalogs.uniform-items', compact('items'));
    }

    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $name = trim((string) $request->validated('name'));
        $this->assertUnique($companyId, $name);

        SupervisorUniformItem::query()->create([
            'security_company_id' => $companyId,
            'name' => $name,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) SupervisorUniformItem::query()->where('security_company_id', $companyId)->max('sort_order') + 10,
        ]);

        return redirect()->route('company.supervision-uniform-items.index')->with('success', 'Prenda creada.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorUniformItem $uniformItem): RedirectResponse
    {
        $companyId = $this->companyId($request);
        abort_unless((int) $uniformItem->security_company_id === $companyId, 404);
        $name = trim((string) $request->validated('name'));
        $this->assertUnique($companyId, $name, (int) $uniformItem->id);

        $uniformItem->update([
            'name' => $name,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-uniform-items.index')->with('success', 'Prenda actualizada.');
    }

    public function destroy(Request $request, SupervisorUniformItem $uniformItem): RedirectResponse
    {
        abort_unless((int) $uniformItem->security_company_id === $this->companyId($request), 404);
        $uniformItem->delete();

        return redirect()->route('company.supervision-uniform-items.index')->with('success', 'Prenda eliminada. Las revistas guardadas conservan su nombre.');
    }

    private function assertUnique(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = SupervisorUniformItem::query()
            ->where('security_company_id', $companyId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Ya existe una prenda con ese nombre.']);
        }
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
