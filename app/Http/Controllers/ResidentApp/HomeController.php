<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $appUser = Auth::guard('resident')->user();
        $appUser?->loadMissing(['member.structure.installation', 'client']);

        return view('resident-app.home', [
            'appUser' => $appUser,
        ]);
    }
}
