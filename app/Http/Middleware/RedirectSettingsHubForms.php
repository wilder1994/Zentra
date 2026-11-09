<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectSettingsHubForms
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $hub = (string) $request->input('_settings_hub', '');
        if ($hub === '' || ! str_contains($hub, ':')) {
            return $response;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        [$group, $catalog] = array_pad(explode(':', $hub, 2), 2, '');
        if ($group === '' || $catalog === '') {
            return $response;
        }

        $response->setTargetUrl(route('company.settings.hub', [
            'group' => $group,
            'catalog' => $catalog,
        ]));

        return $response;
    }
}
