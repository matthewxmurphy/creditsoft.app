<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftSelfUpdateService;
use App\Services\LicenseStateService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CreditsoftUpdateController extends Controller
{
    public function check(Request $request, CreditsoftSelfUpdateService $updater): RedirectResponse
    {
        $this->ensureAllowed($request);

        try {
            $status = $updater->check();

            $request->session()->flash('toast', [
                'type' => ! empty($status['update_available']) ? 'success' : 'info',
                'message' => ! empty($status['update_available'])
                    ? sprintf('Update %s is ready for this office.', $status['latest_version'] ?? 'package')
                    : 'This office is already on the newest available build.',
            ]);
        } catch (RuntimeException $exception) {
            $request->session()->flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->updatesPage();
    }

    public function apply(Request $request, CreditsoftSelfUpdateService $updater): RedirectResponse
    {
        $this->ensureAllowed($request);

        try {
            $result = $updater->applyLatest();

            $request->session()->flash('toast', [
                'type' => 'success',
                'message' => sprintf(
                    'CreditSoft updated to %s (%s). Refresh any open app windows if they still show the older shell.',
                    $result['version'] ?? 'the latest build',
                    $result['build'] ?? 'new build'
                ),
            ]);
        } catch (RuntimeException $exception) {
            $request->session()->flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->updatesPage();
    }

    public function recover(Request $request): RedirectResponse
    {
        $this->ensureAllowed($request);

        $request->session()->flash('toast', [
            'type' => 'info',
            'message' => 'Office updates run from the update buttons. The update lane is ready again.',
        ]);

        return $this->updatesPage();
    }

    /**
     * @throws FileNotFoundException
     */
    public function download(Request $request, CreditsoftSelfUpdateService $updater): BinaryFileResponse
    {
        $this->ensureAllowed($request);

        $packagePath = $updater->latestPackagePath();

        abort_unless(is_file($packagePath), 404);

        return response()->download($packagePath, basename($packagePath));
    }

    protected function ensureAllowed(Request $request): void
    {
        $user = $request->user();
        $license = app(LicenseStateService::class)->current();

        abort_unless(
            $user
            && method_exists($user, 'canAccessOpsPanel')
            && $user->canAccessOpsPanel()
            && (! method_exists($user, 'isReadOnlyDemo') || ! $user->isReadOnlyDemo()),
            403,
        );

        abort_unless(
            (bool) ($license['valid'] ?? false)
            && (string) ($license['access_state'] ?? '') === 'active',
            403,
            'Office updates require an active CreditSoft license.',
        );
    }

    protected function updatesPage(): RedirectResponse
    {
        return redirect()->route('settings.license');
    }
}
