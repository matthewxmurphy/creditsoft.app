<?php

namespace App\Http\Controllers\Internal;

use App\Creditsoft\Config\YamlConfigLoader;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConfigReloadController extends Controller
{
    public function __invoke(Request $request, YamlConfigLoader $loader): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user
            && method_exists($user, 'canAccessOpsPanel')
            && $user->canAccessOpsPanel()
            && (! method_exists($user, 'isReadOnlyDemo') || ! $user->isReadOnlyDemo()),
            403,
        );

        try {
            $loader->reload();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Creditsoft YAML configuration reloaded.');
    }
}
