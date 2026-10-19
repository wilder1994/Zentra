<?php

use App\Http\Middleware\DisableTenantScoping;
use App\Http\Middleware\EnsureClientAdmin;
use App\Http\Middleware\EnsureClientPanelModule;
use App\Http\Middleware\EnsureCompanyUser;
use App\Http\Middleware\EnsureOpenShift;
use App\Http\Middleware\EnsurePorteriaDoor;
use App\Http\Middleware\EnsurePorteriaReadOnly;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSupervisionUnlocked;
use App\Http\Middleware\EnsureSupervisorProApi;
use App\Http\Middleware\EnsureSupervisorUsesFieldApp;
use App\Http\Middleware\EnsureCompanyServiceAccess;
use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InitializeAccessTenancy;
use App\Support\Platform\SupportCompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            SupportCompanyContext::LAST_COMPANY_COOKIE,
        ]);
        $middleware->web(append: [
            EnsureSupervisorUsesFieldApp::class,
            EnsureSingleSession::class,
        ]);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'company.service' => EnsureCompanyServiceAccess::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'tenancy.access' => InitializeAccessTenancy::class,
            'tenant.unscoped' => DisableTenantScoping::class,
            'company' => EnsureCompanyUser::class,
            'client.admin' => EnsureClientAdmin::class,
            'client.module' => EnsureClientPanelModule::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'supervision.unlocked' => EnsureSupervisionUnlocked::class,
            'shift.open' => EnsureOpenShift::class,
            'porteria.door' => EnsurePorteriaDoor::class,
            'porteria.readonly' => EnsurePorteriaReadOnly::class,
            'supervisor.pro' => EnsureSupervisorProApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $redirectExpiredPage = function (Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'La sesión expiró. Recarga la página e intenta de nuevo.'], 419);
            }

            if ($request->user()) {
                return redirect()
                    ->back()
                    ->with('error', 'La página expiró. Recarga e intenta guardar de nuevo.');
            }

            return redirect()->route('login')
                ->with('status', 'Tu sesión expiró. Vuelve a iniciar sesión.');
        };

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($redirectExpiredPage) {
            return $redirectExpiredPage($request);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($redirectExpiredPage) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return $redirectExpiredPage($request);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Revise los datos e inténtelo de nuevo.',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            $message = 'No tienes permiso para esta acción.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();
            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = 'No tienes permiso para esta acción.';
            }

            return response()->json(['message' => $message], 403);
        });
    })->create();
