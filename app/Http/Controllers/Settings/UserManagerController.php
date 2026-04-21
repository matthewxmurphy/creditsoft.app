<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserManagerController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor?->canViewUserDirectory(), 403);

        $users = User::query()
            ->with(['roles', 'manager'])
            ->withCount([
                'apiKeys as active_api_keys_count' => fn ($query) => $query->active(),
                'directReports',
            ])
            ->when(
                ! $actor->canManageUsers(),
                fn ($query) => $query->where(function ($scope) use ($actor): void {
                    $scope->whereKey($actor->getKey())
                        ->orWhere('manager_id', $actor->getKey());
                }),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->userPayload($user, $actor))
            ->values()
            ->all();

        return Inertia::render('settings/Users', [
            'roles' => $this->roleMeta(),
            'manager_options' => $this->managerOptions(),
            'users' => $users,
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->canEditUsers(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $roles = $this->normalizeRoles($validated['roles']);
        abort_unless($roles !== [], 422);
        abort_unless($this->rolesAreAllowed($roles), 422);
        abort_unless($this->managerIdIsAllowed($validated['manager_id'] ?? null), 422);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'manager_id' => $validated['manager_id'] ?? null,
        ]);

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        $user->syncRoles($roles);

        $auditTrail->record(
            $actor,
            'user.created',
            "Created user {$user->email}.",
            $user,
            [
                'roles' => $roles,
                'manager_id' => $validated['manager_id'] ?? null,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created.',
        ]);

        return redirect()->route('users.index');
    }

    public function update(Request $request, User $user, AuditTrail $auditTrail): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->canEditUsers(), 403);
        abort_if($actor?->is($user), 422, 'You cannot change your own role here.');

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $roles = $this->normalizeRoles($validated['roles']);
        abort_unless($roles !== [], 422);
        abort_unless($this->rolesAreAllowed($roles), 422);
        abort_if(
            isset($validated['manager_id']) && (int) $validated['manager_id'] === $user->getKey(),
            422,
            'A user cannot report to themselves.',
        );
        abort_unless($this->managerIdIsAllowed($validated['manager_id'] ?? null), 422);

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        $user->forceFill([
            'manager_id' => $validated['manager_id'] ?? null,
        ])->save();

        $user->syncRoles($roles);

        $auditTrail->record(
            $actor,
            'user.role_updated',
            "Updated access for {$user->email}.",
            $user,
            [
                'roles' => $roles,
                'manager_id' => $validated['manager_id'] ?? null,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User access updated.',
        ]);

        return redirect()->route('users.index');
    }

    public function destroy(Request $request, User $user, AuditTrail $auditTrail): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->canEditUsers(), 403);
        abort_if($actor?->is($user), 422, 'You cannot delete your own account here.');

        $email = $user->email;
        $user->delete();

        $auditTrail->record(
            $actor,
            'user.deleted',
            "Deleted user {$email}.",
            null,
            [
                'deleted_email' => $email,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User removed.',
        ]);

        return redirect()->route('users.index');
    }

    /**
     * @return list<array{value:string,label:string,description:string|null,readonly:bool,workspace:bool,ops:bool,areas:list<string>}>
     */
    protected function roleMeta(): array
    {
        return collect(config('creditsoft.access.roles', []))
            ->map(fn (array $meta, string $key) => [
                'value' => $key,
                'label' => $meta['label'] ?? str($key)->replace('_', ' ')->title()->value(),
                'description' => $meta['description'] ?? null,
                'readonly' => (bool) ($meta['readonly'] ?? false),
                'workspace' => (bool) ($meta['workspace'] ?? false),
                'ops' => (bool) ($meta['ops'] ?? false),
                'areas' => collect($meta['areas'] ?? [])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,email:string,role_label:string|null}>
     */
    protected function managerOptions(): array
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->canViewUserDirectory())
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->primaryRoleLabel(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roles
     */
    protected function rolesAreAllowed(array $roles): bool
    {
        return count(array_diff($roles, $this->allowedRoles())) === 0;
    }

    /**
     * @param  mixed  $managerId
     */
    protected function managerIdIsAllowed($managerId): bool
    {
        if (! $managerId) {
            return true;
        }

        $manager = User::query()->with('roles')->find((int) $managerId);

        return $manager?->canViewUserDirectory() ?? false;
    }

    /**
     * @param  mixed  $roles
     * @return list<string>
     */
    protected function normalizeRoles($roles): array
    {
        return collect(is_array($roles) ? $roles : [])
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function allowedRoles(): array
    {
        return array_keys(config('creditsoft.access.roles', []));
    }

    /**
     * @return array{
     *     id:int,
     *     name:string,
     *     email:string,
     *     roles:list<string>,
     *     role_labels:list<string>,
     *     primary_role:string|null,
     *     primary_role_label:string|null,
     *     manager_id:int|null,
     *     manager_name:string|null,
     *     direct_reports_count:int,
     *     gravatar_url:string,
     *     last_login_at:string|null,
     *     last_seen_at:string|null,
     *     active_api_keys_count:int,
     *     read_only_demo:bool,
     *     is_current_user:bool
     * }
     */
    protected function userPayload(User $user, User $actor): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->assignedRoleNames(),
            'role_labels' => $user->assignedRoleLabels(),
            'primary_role' => $user->primaryRole(),
            'primary_role_label' => $user->primaryRoleLabel(),
            'manager_id' => $user->manager_id ? (int) $user->manager_id : null,
            'manager_name' => $user->manager?->name,
            'direct_reports_count' => (int) ($user->direct_reports_count ?? 0),
            'gravatar_url' => $user->gravatar_url,
            'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
            'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
            'active_api_keys_count' => (int) $user->active_api_keys_count,
            'read_only_demo' => $user->isReadOnlyDemo(),
            'is_current_user' => $actor->is($user),
        ];
    }
}
