<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditsoftCrmLaunchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route('integrations.crm.frame');
    }
}
