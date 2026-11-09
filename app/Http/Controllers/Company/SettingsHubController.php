<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\BuildSettingsPanelService;
use App\Support\Company\SettingsCatalog;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

final class SettingsHubController extends Controller
{
    public function __construct(
        private readonly BuildSettingsPanelService $panels,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->can('company.settings.view')
            || $request->user()?->can('company.settings.manage')
            || $request->user()?->hasRole('super-admin'),
            403
        );

        $groups = SettingsCatalog::groups();
        $openGroup = (string) $request->query('group', '');
        $openCatalog = (string) $request->query('catalog', '');

        if ($openCatalog !== '' && SettingsCatalog::find($openCatalog) === null) {
            $openCatalog = '';
        }
        if ($openGroup !== '' && ! isset($groups[$openGroup])) {
            $openGroup = SettingsCatalog::groupOf($openCatalog) ?? '';
        }
        if ($openCatalog !== '' && $openGroup === '') {
            $openGroup = (string) SettingsCatalog::groupOf($openCatalog);
        }

        return view('modules.company.settings.hub', [
            'groups' => $groups,
            'openGroup' => $openGroup,
            'openCatalog' => $openCatalog,
            'panelUrl' => route('company.settings.hub.panel', ['catalog' => '__KEY__']),
        ]);
    }

    public function panel(Request $request, string $catalog): Response|View
    {
        abort_unless(
            $request->user()?->can('company.settings.view')
            || $request->user()?->can('company.settings.manage')
            || $request->user()?->hasRole('super-admin'),
            403
        );

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());

        try {
            $panel = $this->panels->execute($catalog, $companyId, $request->user());
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()
            ->view($panel['view'], $panel['data'])
            ->header('Cache-Control', 'no-store');
    }
}
