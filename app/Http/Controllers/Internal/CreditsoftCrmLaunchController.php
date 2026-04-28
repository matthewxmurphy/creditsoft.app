<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftCrmLaunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CreditsoftCrmLaunchController extends Controller
{
    public function __invoke(Request $request, CreditsoftCrmLaunchService $crm): RedirectResponse
    {
        try {
            return redirect()->away($crm->launchUrl($request->user()));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($crm->fallbackUrl($request->user(), $exception));
        }
    }
}
