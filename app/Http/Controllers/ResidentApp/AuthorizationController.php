<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Enums\AuthorizationType;
use App\Http\Controllers\Controller;
use App\Models\StructureAppUser;
use App\Models\VisitorPreAuthorization;
use App\Services\Structure\ManageAuthorizationService;
use App\Support\Resident\ResidentTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AuthorizationController extends Controller
{
    public function __construct(
        private readonly ManageAuthorizationService $authorizations,
        private readonly TenantContext $tenant,
    ) {}

    public function index(): View
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);
        $rows = VisitorPreAuthorization::query()
            ->with(['guests'])
            ->where('client_id', (int) $appUser->client_id)
            ->where('structure_id', $structureId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return view('resident-app.authorizations', [
            'appUser' => $appUser,
            'authorizations' => $rows,
        ]);
    }

    public function create(): View
    {
        $appUser = $this->appUser();

        return view('resident-app.authorizations-create', [
            'appUser' => $appUser,
            'types' => AuthorizationType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);
        abort_unless($structureId > 0, 403);

        $validated = $request->validate([
            'subject' => ['required', 'in:person,vehicle'],
            'type' => ['required', Rule::enum(AuthorizationType::class)],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'entry_time_from' => ['nullable', 'date_format:H:i', 'required_with:entry_time_until'],
            'entry_time_until' => ['nullable', 'date_format:H:i', 'required_with:entry_time_from', 'after:entry_time_from'],
            'vehicle_plate' => ['nullable', 'string', 'max:15', 'required_if:subject,vehicle'],
            'vehicle_brand' => ['nullable', 'string', 'max:80', 'required_if:subject,vehicle'],
            'vehicle_model' => ['nullable', 'string', 'max:80', 'required_if:subject,vehicle'],
            'vehicle_color' => ['nullable', 'string', 'max:40', 'required_if:subject,vehicle'],
            'vehicle_type' => ['nullable', 'string', 'max:30', 'required_if:subject,vehicle'],
            'notes' => ['nullable', 'string', 'max:500'],
            'guest_document_type' => ['nullable', 'string', 'max:10'],
            'guest_document_number' => ['required', 'string', 'max:30'],
            'guest_first_name' => ['required', 'string', 'max:100'],
            'guest_last_name' => ['required', 'string', 'max:100'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
        ], [
            'guest_document_number.required' => 'Indica el documento del visitante.',
            'guest_first_name.required' => 'Indica el nombre del visitante.',
            'guest_last_name.required' => 'Indica el apellido del visitante.',
            'vehicle_plate.required_if' => 'Indica la placa del vehículo.',
            'vehicle_brand.required_if' => 'Indica la marca del vehículo.',
            'vehicle_model.required_if' => 'Indica el modelo del vehículo.',
            'vehicle_color.required_if' => 'Indica el color del vehículo.',
            'vehicle_type.required_if' => 'Indica el tipo de vehículo.',
        ]);

        $isVehicle = $validated['subject'] === 'vehicle';

        $this->authorizations->create(null, [
            'type' => $validated['type'],
            'structure_id' => $structureId,
            'member_id' => $appUser->member_id,
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
            'entry_time_from' => $validated['entry_time_from'] ?? null,
            'entry_time_until' => $validated['entry_time_until'] ?? null,
            'vehicle_plate' => $isVehicle ? ($validated['vehicle_plate'] ?? null) : null,
            'vehicle' => $isVehicle ? [
                'plate' => $validated['vehicle_plate'] ?? null,
                'brand' => $validated['vehicle_brand'] ?? null,
                'model' => $validated['vehicle_model'] ?? null,
                'color' => $validated['vehicle_color'] ?? null,
                'type' => $validated['vehicle_type'] ?? 'carro',
            ] : null,
            'notes' => $validated['notes'] ?? null,
            'origin' => 'resident_app',
            'guests' => [[
                'document_type' => $validated['guest_document_type'] ?? 'CC',
                'document_number' => $validated['guest_document_number'],
                'first_name' => $validated['guest_first_name'],
                'last_name' => $validated['guest_last_name'],
                'phone' => $validated['guest_phone'] ?? null,
            ]],
        ]);

        return redirect()
            ->route('resident-app.authorizations')
            ->with('success', 'Autorización creada. Portería podrá verla al consultar.');
    }

    public function destroy(VisitorPreAuthorization $authorization): RedirectResponse
    {
        $appUser = $this->appUser();
        abort_unless((int) $authorization->client_id === (int) $appUser->client_id, 404);
        abort_unless(
            (int) $authorization->structure_id === (int) $appUser->member?->structure_id
            || (int) $authorization->member_id === (int) $appUser->member_id,
            403,
        );

        ResidentTenant::bind($appUser, $this->tenant);
        $this->authorizations->cancel(null, $authorization);

        return redirect()
            ->route('resident-app.authorizations')
            ->with('success', 'Autorización cancelada.');
    }

    private function appUser(): StructureAppUser
    {
        /** @var StructureAppUser $user */
        $user = Auth::guard('resident')->user();
        $user->loadMissing(['member.structure.installation', 'client']);

        return $user;
    }
}
