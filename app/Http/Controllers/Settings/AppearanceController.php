<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\EnvironmentEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'reviewLabelStyle' => (string) config('creditsoft.ui.review_label_style', '10'),
            'reviewLabelStyles' => config('creditsoft.ui.review_label_styles', []),
            'canEditReviewLabelStyle' => $request->user()?->canEditUsers() ?? false,
            'galleryUrl' => url('/legend-lab-gallery.html'),
        ]);
    }

    public function update(Request $request, EnvironmentEditor $editor): RedirectResponse
    {
        abort_unless($request->user()?->canEditUsers(), 403);

        $allowedStyles = collect(config('creditsoft.ui.review_label_styles', []))
            ->pluck('key')
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        $validated = $request->validate([
            'review_label_style' => ['required', 'string', Rule::in($allowedStyles)],
        ]);

        $editor->setMany([
            'CREDITSOFT_REVIEW_LABEL_STYLE' => (string) $validated['review_label_style'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Review label style updated for this intranet.',
        ]);

        return back();
    }
}
