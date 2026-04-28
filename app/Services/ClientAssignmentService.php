<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClientAssignmentService
{
    public const MODE_SINGLE_USER = 'single_user';

    public const MODE_SPLIT_EVENLY = 'split_evenly';

    public const MODE_SOURCE_MATCH = 'source_match';

    protected ?Collection $eligibleUsersCache = null;

    /**
     * @return list<array{id:int,name:string,role:string|null,role_label:string|null,assigned_client_count:int}>
     */
    public function staffOptions(): array
    {
        return $this->eligibleUsers()
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'role' => $user->primaryRole(),
                'role_label' => $user->primaryRoleLabel(),
                'assigned_client_count' => (int) ($user->assigned_clients_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value:string,label:string,description:string}>
     */
    public function createModes(): array
    {
        return [
            [
                'value' => self::MODE_SINGLE_USER,
                'label' => 'Assign to one owner',
                'description' => 'Pick the staff or admin member who should own this dossier.',
            ],
            [
                'value' => self::MODE_SPLIT_EVENLY,
                'label' => 'Auto-balance selected team',
                'description' => 'Choose the team members and CreditSoft will send the next dossier to the lightest queue.',
            ],
        ];
    }

    /**
     * @return list<array{value:string,label:string,description:string}>
     */
    public function importModes(): array
    {
        return [
            [
                'value' => self::MODE_SOURCE_MATCH,
                'label' => 'Use source owner match',
                'description' => 'Match the source agent or sales rep when possible, then fall back to the selected owner.',
            ],
            [
                'value' => self::MODE_SINGLE_USER,
                'label' => 'Assign all to one owner',
                'description' => 'Every imported dossier lands with the same owner.',
            ],
            [
                'value' => self::MODE_SPLIT_EVENLY,
                'label' => 'Split evenly across selected team',
                'description' => 'Distribute imported dossiers across the selected staff and admin members.',
            ],
        ];
    }

    /**
     * @param  list<int|string>  $teamUserIds
     */
    public function resolveForCreate(string $mode, ?int $assignedTo, array $teamUserIds): int
    {
        return match ($mode) {
            self::MODE_SINGLE_USER => $this->requireSingleUser($assignedTo),
            self::MODE_SPLIT_EVENLY => $this->pickFromBalancedQueue($teamUserIds, 0),
            default => throw new InvalidArgumentException('Choose a valid assignment mode.'),
        };
    }

    /**
     * @param  list<int|string>  $teamUserIds
     * @param  list<string>  $sourceCandidates
     */
    public function resolveForBatchRow(
        string $mode,
        ?int $assignedTo,
        array $teamUserIds,
        int $rowIndex,
        array $sourceCandidates = [],
    ): int {
        return match ($mode) {
            self::MODE_SINGLE_USER => $this->requireSingleUser($assignedTo),
            self::MODE_SPLIT_EVENLY => $this->pickFromBalancedQueue($teamUserIds, $rowIndex),
            self::MODE_SOURCE_MATCH => $this->matchUserId($sourceCandidates) ?? $this->requireSingleUser($assignedTo),
            default => throw new InvalidArgumentException('Choose a valid assignment mode.'),
        };
    }

    /**
     * @param  list<int|string>  $teamUserIds
     * @return list<int>
     */
    public function normalizedTeamUserIds(array $teamUserIds): array
    {
        $eligibleIds = array_flip($this->eligibleUserIds());

        return collect($teamUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => isset($eligibleIds[$id]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $candidates
     */
    public function matchUserId(array $candidates): ?int
    {
        $directory = $this->userDirectory();

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePerson($candidate);

            if ($normalized !== '' && isset($directory[$normalized])) {
                return $directory[$normalized];
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function eligibleUserIds(): array
    {
        return $this->eligibleUsers()
            ->map(fn (User $user) => $user->getKey())
            ->values()
            ->all();
    }

    protected function requireSingleUser(?int $assignedTo): int
    {
        if (! $assignedTo || ! in_array($assignedTo, $this->eligibleUserIds(), true)) {
            throw new InvalidArgumentException('Choose a valid staff or admin owner.');
        }

        return $assignedTo;
    }

    /**
     * @param  list<int|string>  $teamUserIds
     */
    protected function pickFromBalancedQueue(array $teamUserIds, int $rowIndex): int
    {
        $queue = $this->balancedQueue($teamUserIds);

        if ($queue === []) {
            throw new InvalidArgumentException('Choose at least one staff or admin member for workload balancing.');
        }

        return $queue[$rowIndex % count($queue)];
    }

    /**
     * @param  list<int|string>  $teamUserIds
     * @return list<int>
     */
    protected function balancedQueue(array $teamUserIds): array
    {
        $allowedIds = array_flip($this->normalizedTeamUserIds($teamUserIds));
        $selected = $this->eligibleUsers()
            ->filter(fn (User $user) => isset($allowedIds[$user->getKey()]))
            ->values()
            ->all();

        usort($selected, function (User $left, User $right): int {
            $leftSort = [
                (int) ($left->assigned_clients_count ?? 0),
                Str::lower($left->name),
                $left->getKey(),
            ];
            $rightSort = [
                (int) ($right->assigned_clients_count ?? 0),
                Str::lower($right->name),
                $right->getKey(),
            ];

            return $leftSort <=> $rightSort;
        });

        return array_map(fn (User $user) => (int) $user->getKey(), $selected);
    }

    /**
     * @return array<string, int>
     */
    protected function userDirectory(): array
    {
        return $this->eligibleUsers()
            ->reduce(function (array $carry, User $user): array {
                $normalized = $this->normalizePerson($user->name);

                if ($normalized !== '' && ! isset($carry[$normalized])) {
                    $carry[$normalized] = $user->getKey();
                }

                return $carry;
            }, []);
    }

    protected function normalizePerson(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    protected function eligibleUsers(): Collection
    {
        if ($this->eligibleUsersCache instanceof Collection) {
            return $this->eligibleUsersCache;
        }

        $this->eligibleUsersCache = User::query()
            ->with('roles')
            ->withCount('assignedClients')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasWorkspaceAccess() && ! $user->isReadOnlyDemo())
            ->values();

        return $this->eligibleUsersCache;
    }
}
