<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PrivacyPolicyController extends Controller
{
    public function __invoke(): View
    {
        return view('modules.public.privacy', [
            'title' => 'Política de privacidad',
        ]);
    }
}
