<?php

declare(strict_types=1);

namespace App\Support\Platform;

final class SupportCompanyContext
{
    public const SESSION_KEY = 'support.acting_company_id';

    public const LAST_COMPANY_COOKIE = 'support_last_company_id';

    public const EXPIRED_FLASH = 'La sesión de soporte terminó. Entra otra vez desde el expediente de la empresa.';

    /** Empresa recién dejada: el panel ofrece «Entrar de nuevo» una sola vez. */
    public const RESUME_NOTICE_KEY = 'support.resume_notice_company_id';

    public static function enter(int $companyId): void
    {
        session([self::SESSION_KEY => $companyId]);
        session()->forget(self::RESUME_NOTICE_KEY);
        cookie()->queue(cookie(self::LAST_COMPANY_COOKIE, (string) $companyId, 60 * 24 * 30));
    }

    public static function exit(): void
    {
        $companyId = self::companyId();
        session()->forget(self::SESSION_KEY);
        if ($companyId !== null) {
            session([self::RESUME_NOTICE_KEY => $companyId]);
        }
    }

    public static function pullResumeNotice(): ?int
    {
        $id = (int) session()->pull(self::RESUME_NOTICE_KEY, 0);

        return $id > 0 ? $id : null;
    }

    public static function isActive(): bool
    {
        $id = session(self::SESSION_KEY);

        return $id !== null && (int) $id > 0;
    }

    public static function companyId(): ?int
    {
        if (! self::isActive()) {
            return null;
        }

        return (int) session(self::SESSION_KEY);
    }

    public static function lastCompanyId(): ?int
    {
        $id = (int) request()->cookie(self::LAST_COMPANY_COOKIE, 0);

        return $id > 0 ? $id : null;
    }

    public static function isCompanyPanelUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : $url;

        return str_starts_with($path, '/company');
    }
}
