<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OfficeGrowthSettingsService;
use App\Services\OfficeSocialSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request, OfficeGrowthSettingsService $growthSettings): Response
    {
        /** @var User $user */
        $user = $request->user();
        $current = $growthSettings->load();
        $visibleUsers = $this->visibleCalendarUsers($user);
        $scope = $this->calendarScope($user, $visibleUsers);

        return Inertia::render('Calendar', [
            'calendar' => [
                'portal_booking_name' => data_get($current, 'appointments.portal_booking_name', 'Detailed Credit Analysis Consultation'),
                'calendar_email' => data_get($current, 'appointments.calendar_email'),
                'booking_links' => array_values((array) data_get($current, 'appointments.links', [])),
                'scope_label' => $scope['label'],
                'scope_summary' => $scope['summary'],
                'can_view_everyone' => $scope['can_view_everyone'],
                'visible_users' => $visibleUsers
                    ->map(fn (User $candidate) => [
                        'id' => $candidate->getKey(),
                        'name' => $candidate->name,
                        'role_label' => $candidate->primaryRoleLabel(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function social(OfficeSocialSettingsService $settings): Response
    {
        $current = $settings->load();

        $selectedPage = collect((array) data_get($current, 'meta.available_pages', []))
            ->firstWhere('id', (string) data_get($current, 'meta.page_id', ''));

        $selectedAdAccount = collect((array) data_get($current, 'meta.available_ad_accounts', []))
            ->firstWhere('id', (string) data_get($current, 'meta.default_ad_account_id', ''));

        return Inertia::render('SocialCalendar', [
            'calendar' => [
                'page_name' => data_get($selectedPage, 'name'),
                'ad_account_name' => data_get($selectedAdAccount, 'name') ?: data_get($selectedAdAccount, 'id'),
                'default_cta' => data_get($current, 'publishing.default_cta', 'learn_more'),
                'default_destination' => data_get($current, 'ads.default_destination', 'website'),
                'auto_publish_releases' => (bool) data_get($current, 'publishing.auto_publish_releases', false),
                'auto_publish_features' => (bool) data_get($current, 'publishing.auto_publish_features', false),
                'auto_publish_reviews' => (bool) data_get($current, 'publishing.auto_publish_reviews', false),
                'facebook_publishing_ready' => data_get($current, 'meta.connection_status') === 'connected'
                    && trim((string) data_get($current, 'meta.page_access_token', '')) !== ''
                    && (bool) data_get($current, 'publishing.enabled', false)
                    && (bool) data_get($current, 'publishing.facebook_page_posts', true),
                'instagram_business_id' => trim((string) data_get($current, 'meta.instagram_business_id', ''))
                    ?: trim((string) data_get($current, 'website_signals.instagram_business_id', '')),
                'instagram_username' => trim((string) data_get($current, 'website_signals.instagram_username', '')),
                'instagram_publishing_ready' => trim((string) data_get($current, 'meta.instagram_business_id', '')) !== ''
                    && (bool) data_get($current, 'publishing.instagram_posts', false),
                'threads_username' => trim((string) data_get($current, 'website_signals.threads_username', '')),
                'whatsapp_ready' => (bool) data_get($current, 'whatsapp.enabled', false)
                    && (
                        trim((string) data_get($current, 'whatsapp.phone_number_id', '')) !== ''
                        || trim((string) data_get($current, 'whatsapp.display_number', '')) !== ''
                    ),
            ],
            'workspace' => [
                'connection_status' => data_get($current, 'meta.connection_status', 'disconnected'),
                'publishing_enabled' => (bool) data_get($current, 'publishing.enabled', false),
                'approval_required' => (bool) data_get($current, 'publishing.approval_required', true),
                'lead_destination' => data_get($current, 'ads.default_destination', 'website'),
                'creator_challenge_enabled' => (bool) data_get($current, 'creator_challenge.enabled', false),
                'creator_challenge_sync_status' => data_get($current, 'creator_challenge.live_sync.status', 'preview'),
                'creator_challenge_name' => data_get($current, 'creator_challenge.challenge_name', 'Weekly creator challenge'),
                'whatsapp_ready' => (bool) data_get($current, 'whatsapp.enabled', false)
                    && (
                        trim((string) data_get($current, 'whatsapp.phone_number_id', '')) !== ''
                        || trim((string) data_get($current, 'whatsapp.display_number', '')) !== ''
                    ),
            ],
        ]);
    }

    protected function visibleCalendarUsers(User $user): Collection
    {
        $user->loadMissing(['roles', 'directReports.roles']);

        if ($user->hasAnyRole(['owner_admin', 'admin', 'demo_admin'])) {
            return User::query()
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->filter(fn (User $candidate) => $candidate->hasWorkspaceAccess())
                ->values();
        }

        if ($user->hasRole('manager')) {
            return collect([$user])
                ->merge(
                    $user->directReports
                        ->filter(fn (User $candidate) => $candidate->hasWorkspaceAccess())
                        ->values(),
                )
                ->unique(fn (User $candidate) => $candidate->getKey())
                ->values();
        }

        return collect([$user]);
    }

    /**
     * @param  Collection<int, User>  $visibleUsers
     * @return array{label:string, summary:string, can_view_everyone:bool}
     */
    protected function calendarScope(User $user, Collection $visibleUsers): array
    {
        if ($user->hasAnyRole(['owner_admin', 'admin', 'demo_admin'])) {
            $count = $visibleUsers->count();

            return [
                'label' => 'Whole office',
                'summary' => $count === 1
                    ? 'This office view currently has one visible workspace account.'
                    : "Owner and admin lanes can see all {$count} workspace calendars in one office schedule.",
                'can_view_everyone' => true,
            ];
        }

        if ($user->hasRole('manager')) {
            $directReports = max(0, $visibleUsers->count() - 1);

            return [
                'label' => 'Manager team view',
                'summary' => $directReports > 0
                    ? "Managers can see their own calendar plus {$directReports} direct-report calendar lanes."
                    : 'Manager scope is active, but no direct-report calendars are assigned yet.',
                'can_view_everyone' => false,
            ];
        }

        return [
            'label' => 'Personal view',
            'summary' => 'Staff-level calendar access stays on the signed-in user until a manager or admin opens the wider office view.',
            'can_view_everyone' => false,
        ];
    }
}
