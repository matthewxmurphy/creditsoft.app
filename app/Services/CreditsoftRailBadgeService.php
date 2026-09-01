<?php

namespace App\Services;

use App\Models\User;
use App\Models\ViolationCandidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreditsoftRailBadgeService
{
    public function __construct(
        protected InboxWorkService $inboxWork,
    ) {}

    /**
     * @return array{clients:int,inbox:int,tasks:int,violations:int}
     */
    public function countsFor(User $user): array
    {
        $inboxCounts = $this->inboxWork->counts();

        return [
            'clients' => $inboxCounts['leads'],
            'inbox' => $inboxCounts['total'],
            'tasks' => $inboxCounts['tasks'],
            'violations' => ViolationCandidate::query()
                ->whereIn('status', ['open', 'confirmed'])
                ->count(),
        ];
    }

    public function markRequestSeen(Request $request, User $user): void
    {
        if (! Schema::hasTable('user_rail_badge_states')) {
            return;
        }

        $lane = $this->laneForRequest($request);

        if (! $lane) {
            return;
        }

        DB::table('user_rail_badge_states')->updateOrInsert(
            [
                'user_id' => $user->getKey(),
                'lane' => $lane,
            ],
            [
                'last_seen_at' => now(),
                'metadata' => json_encode([
                    'path' => '/'.trim($request->path(), '/'),
                    'source' => 'inertia_rail_visit',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    protected function laneForRequest(Request $request): ?string
    {
        $path = trim($request->path(), '/');

        if ($path === 'clients' || str_starts_with($path, 'clients/')) {
            return 'clients';
        }

        if ($path === 'inbox') {
            return 'inbox';
        }

        if ($path === 'tasks') {
            return 'tasks';
        }

        if ($path === 'violations') {
            return 'violations';
        }

        return null;
    }
}
