<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegrationKey;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Platform\ManageApiIntegrationKeysService;
use App\Support\Api\ApiAbilities;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Pestaña «Integraciones API» de la ficha de empresa (súper admin). */
final class ApiIntegrationController extends Controller
{
    private const ROLE_LABELS = [
        'company-admin' => 'Admin empresa',
        'colaborador' => 'Colaborador',
        'client-admin' => 'Admin cliente',
        'client-installation-admin' => 'Admin instalación',
        'supervisor' => 'Supervisor',
        'guardia' => 'Guarda',
        'residente' => 'Residente',
    ];

    public const MANUALS = [
        'api-key' => 'Manual de API Key',
        'integracion' => 'Manual de Integración',
    ];

    public function __construct(
        private readonly ManageApiIntegrationKeysService $keys,
    ) {}

    public function index(SecurityCompany $company): View
    {
        $keys = ApiIntegrationKey::query()
            ->where('security_company_id', $company->id)
            ->with(['user.roles', 'token', 'createdBy', 'revokedBy'])
            ->latest('id')
            ->get();

        $users = User::query()
            ->where('security_company_id', $company->id)
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => $user->hasRole('super-admin'))
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
                'hint' => trim((self::ROLE_LABELS[$user->getRoleNames()->first()] ?? (string) $user->getRoleNames()->first()).' · '.$user->email, ' ·'),
            ])
            ->values();

        return view('modules.admin.companies.integrations', [
            'company' => $company,
            'keys' => $keys,
            'users' => $users,
            'clients' => Client::query()->where('security_company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'modules' => ApiAbilities::modules(),
            'roleLabels' => self::ROLE_LABELS,
            'manuals' => self::MANUALS,
            'manual' => self::manualData($company),
            'canManage' => auth()->user()?->can('platform.companies.manage') ?? false,
        ]);
    }

    public function store(Request $request, SecurityCompany $company): RedirectResponse
    {
        $modules = array_keys(ApiAbilities::modules());
        $levels = [ApiAbilities::LEVEL_NONE, ApiAbilities::LEVEL_READ, ApiAbilities::LEVEL_WRITE, ApiAbilities::LEVEL_CUSTOM];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'user_mode' => ['required', Rule::in(['existing', 'new'])],
            'user_id' => ['nullable', 'required_if:user_mode,existing', 'integer'],
            'new_user_name' => ['nullable', 'required_if:user_mode,new', 'string', 'max:120'],
            'new_user_email' => ['nullable', 'email', 'max:190'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer'],
            'levels' => ['required', 'array'],
            'levels.*' => ['string', Rule::in($levels)],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['string', Rule::in(ApiAbilities::all())],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ], [
            'name.required' => 'Ponle un nombre a la key (p. ej. «Secretaría de Educación»).',
            'user_id.required_if' => 'Elige el usuario de la key.',
            'new_user_name.required_if' => 'Escribe el nombre del usuario de integración.',
            'expires_at.after' => 'El vencimiento debe ser una fecha futura.',
        ]);

        $abilities = ApiAbilities::resolve(
            array_intersect_key($validated['levels'], array_flip($modules)),
            $validated['custom'] ?? [],
        );

        try {
            [$key, $plain] = $this->keys->create($company, $request->user(), [
                'name' => $validated['name'],
                'user_mode' => $validated['user_mode'],
                'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                'new_user_name' => $validated['new_user_name'] ?? null,
                'new_user_email' => $validated['new_user_email'] ?? null,
                'client_ids' => array_map('intval', $validated['client_ids'] ?? []),
                'abilities' => $abilities,
                'expires_at' => isset($validated['expires_at']) ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
            ]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors())->with('integration_modal', 'create');
        }

        return redirect()
            ->route('admin.companies.integrations.index', $company)
            ->with('success', 'API key creada.')
            ->with('api_key_plain', ['name' => $key->name, 'token' => $plain]);
    }

    public function revoke(Request $request, SecurityCompany $company, ApiIntegrationKey $key): RedirectResponse
    {
        $this->assertCompany($company, $key);
        $this->keys->revoke($key, $request->user());

        return back()->with('success', 'API key revocada. Deja de funcionar de inmediato.');
    }

    public function regenerate(SecurityCompany $company, ApiIntegrationKey $key): RedirectResponse
    {
        $this->assertCompany($company, $key);

        try {
            $plain = $this->keys->regenerate($key);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()
            ->with('success', 'API key regenerada. La anterior dejó de funcionar.')
            ->with('api_key_plain', ['name' => $key->name, 'token' => $plain]);
    }

    public function manual(SecurityCompany $company, string $type): Response
    {
        $titles = self::MANUALS;
        abort_unless(isset($titles[$type]), 404);

        return Pdf::loadView('modules.admin.companies.manuals.pdf', array_merge(self::manualData($company), [
            'type' => $type,
            'title' => $titles[$type],
        ]))
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true)
            ->download(($type === 'integracion' ? 'manual-integracion-api' : 'manual-api-key').'-zentra.pdf');
    }

    /** @return array{company: SecurityCompany, baseUrl: string, docsUrl: string, specUrl: string} */
    public static function manualData(SecurityCompany $company): array
    {
        return [
            'company' => $company,
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'docsUrl' => route('observatory.docs'),
            'specUrl' => url('/api/observatory/openapi.json'),
        ];
    }

    private function assertCompany(SecurityCompany $company, ApiIntegrationKey $key): void
    {
        abort_unless((int) $key->security_company_id === (int) $company->id, 404);
    }
}
