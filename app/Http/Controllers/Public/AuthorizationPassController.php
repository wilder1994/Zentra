<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationGuest;
use App\Models\Scopes\ClientScope;
use App\Models\VisitorPreAuthorization;
use Illuminate\View\View;

/** Pase público que el visitante muestra en portería (sin login, datos mínimos). */
final class AuthorizationPassController extends Controller
{
    public function __invoke(string $token): View
    {
        $authorization = VisitorPreAuthorization::query()
            ->withoutGlobalScope(ClientScope::class)
            ->where('qr_auth_token', $token)
            ->with([
                'client:id,name',
                'structure' => fn ($q) => $q->withoutGlobalScope(ClientScope::class),
                'guests' => fn ($q) => $q->withoutGlobalScope(ClientScope::class)->orderBy('id'),
            ])
            ->first();

        abort_if($authorization === null, 404);

        $state = $authorization->state();
        $valid = in_array($state, [VisitorPreAuthorization::STATE_ACTIVE, VisitorPreAuthorization::STATE_UPCOMING], true);

        return view('modules.public.authorization-pass', [
            'authorization' => $authorization,
            'state' => $state,
            'valid' => $valid,
            'guests' => $authorization->guests->map(fn (AuthorizationGuest $guest) => $guest->publicName())->all(),
        ]);
    }
}
