<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftCrmLaunchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CreditsoftCrmFrameController extends Controller
{
    public function __invoke(Request $request, CreditsoftCrmLaunchService $crm): Response
    {
        $fallback = false;

        try {
            $launchUrl = $crm->launchUrl($request->user());
        } catch (Throwable $exception) {
            report($exception);

            $launchUrl = $crm->fallbackUrl($request->user(), $exception);
            $fallback = true;
        }

        return Inertia::render('Crm', [
            'launchUrl' => $launchUrl,
            'fallback' => $fallback,
        ]);
    }
}
