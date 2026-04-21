<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftCrmLaunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditsoftCrmLaunchController extends Controller
{
    public function __invoke(Request $request, CreditsoftCrmLaunchService $crm): RedirectResponse
    {
        return redirect()->away($crm->launchUrl($request->user()));
    }
}
