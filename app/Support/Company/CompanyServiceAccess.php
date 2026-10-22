<?php

declare(strict_types=1);

namespace App\Support\Company;

use App\Models\SecurityCompany;
use App\Models\User;
use Illuminate\Http\Request;

final class CompanyServiceAccess
{
    public const DENIED = 'Este usuario no tiene acceso al sistema. Comuníquese con el administrador.';

    public const BANNER = 'Servicio suspendido';

    public static function isCut(?SecurityCompany $company): bool
    {
        if ($company === null) {
            return false;
        }

        return ! $company->is_active || $company->archived_at !== null;
    }

    public static function mayBrowseWhileCut(User $user): bool
    {
        return $user->hasRole('company-admin') && $user->security_company_id !== null;
    }

    public static function allowsWebRequest(User $user, Request $request): bool
    {
        if (! self::mayBrowseWhileCut($user)) {
            return false;
        }

        if ($request->routeIs('logout')) {
            return true;
        }

        return $request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS');
    }

    public static function revokeOperationalTokens(int $companyId): void
    {
        User::query()
            ->where('security_company_id', $companyId)
            ->each(static function (User $user): void {
                $user->tokens()->where('is_integration', false)->delete();
            });
    }
}
