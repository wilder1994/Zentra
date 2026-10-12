<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\SecurityCompany;
use App\Support\Platform\SupportCompanyContext;
use Illuminate\View\Component;
use Illuminate\View\View;

final class AdminLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        return view('layouts.admin', [
            'resumeSupportCompany' => $this->resolveResumeSupportCompany(),
        ]);
    }

    private function resolveResumeSupportCompany(): ?SecurityCompany
    {
        $user = auth()->user();
        if ($user === null || ! $user->hasRole('super-admin') || SupportCompanyContext::isActive()) {
            return null;
        }

        $companyId = SupportCompanyContext::pullResumeNotice();
        if ($companyId === null) {
            return null;
        }

        return SecurityCompany::query()->find($companyId);
    }
}
