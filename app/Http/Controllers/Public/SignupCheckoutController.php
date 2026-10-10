<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\SignupIntentStatus;
use App\Http\Controllers\Controller;
use App\Models\CommercialSignupIntent;
use App\Services\Public\CompletePublicSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class SignupCheckoutController extends Controller
{
    public function __construct(
        private readonly CompletePublicSignupService $completeSignupService,
    ) {}

    public function show(CommercialSignupIntent $intent): View|RedirectResponse
    {
        if ($intent->isExpired()) {
            $intent->update(['status' => SignupIntentStatus::Expired]);

            return redirect()
                ->route('planes.index')
                ->with('warning', 'El proceso expiró. Selecciona un plan e intenta de nuevo.');
        }

        if ($intent->status === SignupIntentStatus::Completed) {
            return redirect()->route('login')->with('success', 'Contratación completada. Inicia sesión.');
        }

        if ($intent->status === SignupIntentStatus::Rejected) {
            return redirect()
                ->route('planes.index')
                ->with('warning', 'El pago no se completó. Selecciona un plan e intenta de nuevo.');
        }

        if ($intent->status !== SignupIntentStatus::AwaitingPayment) {
            return redirect()->route('signup.summary', $intent);
        }

        return view('modules.public.signup.checkout', compact('intent'));
    }

    public function approve(CommercialSignupIntent $intent): RedirectResponse
    {
        if (! $this->canProcessCheckout($intent)) {
            return redirect()->route('planes.index')->with('warning', 'No se puede completar este pago.');
        }

        try {
            $user = $this->completeSignupService->execute($intent);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('signup.checkout.show', $intent)
                ->with('warning', $e->getMessage());
        }

        Auth::login($user);

        return redirect()
            ->route('company.dashboard')
            ->with('success', 'Cuenta activada. Bienvenido a Zentra.');
    }

    public function reject(CommercialSignupIntent $intent): RedirectResponse
    {
        if ($intent->status === SignupIntentStatus::AwaitingPayment && ! $intent->isExpired()) {
            $intent->update([
                'status' => SignupIntentStatus::Rejected,
                'password' => null,
            ]);
        }

        return redirect()
            ->route('planes.index')
            ->with('warning', 'Pago no completado. No se creó tu cuenta. Selecciona un plan e intenta de nuevo.');
    }

    private function canProcessCheckout(CommercialSignupIntent $intent): bool
    {
        return $intent->status === SignupIntentStatus::AwaitingPayment
            && ! $intent->isExpired()
            && $intent->isCheckoutReady();
    }
}
