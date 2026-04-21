<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\OfficeCrmUserLink;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Connection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftCrmLaunchService
{
    public function __construct(
        protected CreditsoftCrmRosterBridgeService $rosterBridge,
    ) {}

    public function launchUrl(User $user): string
    {
        abort_unless((bool) config('creditsoft.integrations.crm.enabled', false), 404);

        $baseUrl = $this->baseUrl();
        abort_unless($baseUrl !== '', 404);

        $link = $this->userLink($user);

        try {
            $tokenPair = $this->tokensFromCredentials($link);
        } catch (Throwable $previous) {
            if ($this->syncExistingCrmPassword($link, $previous)) {
                try {
                    $tokenPair = $this->tokensFromCredentials($link);
                } catch (Throwable $retryFailure) {
                    $previous = $retryFailure;
                }
            }

            if (! isset($tokenPair)) {
                $tokenPair = $this->bootstrapUserAndWorkspace($user, $link, $previous);
            }
        }

        $this->provisionWorkspaceFromHr($user, $link);
        $this->syncCreditsoftRoster($link);

        $link->forceFill([
            'last_launched_at' => now(),
            'last_error' => null,
        ])->save();

        return $this->welcomeUrl($tokenPair, $link->crm_email);
    }

    protected function userLink(User $user): OfficeCrmUserLink
    {
        $email = Str::of((string) $user->email)->lower()->trim()->value();

        if ($email === '') {
            throw new RuntimeException('CreditSoft cannot open the CRM because this user does not have an email address.');
        }

        $link = OfficeCrmUserLink::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (! $link->exists) {
            $link->crm_email = $email;
            $link->crm_password = Str::password(40);
            $link->save();

            return $link;
        }

        if ($link->crm_email !== $email) {
            return $this->rotateCrmLinkPassword(
                $link,
                $email,
                'CreditSoft aligned the CRM sidecar email with this intranet user.',
            );
        }

        if (! filled($this->crmPassword($link))) {
            return $this->rotateCrmLinkPassword(
                $link,
                $email,
                'CreditSoft could not decrypt the saved CRM sidecar password.',
            );
        }

        return $link;
    }

    protected function rotateCrmLinkPassword(OfficeCrmUserLink $link, string $email, string $reason): OfficeCrmUserLink
    {
        $metadata = array_filter([
            ...($link->metadata ?? []),
            'password_rotated_at' => now()->toIso8601String(),
            'password_rotation_reason' => $reason,
        ]);

        DB::table($link->getTable())
            ->where('id', $link->getKey())
            ->update([
                'crm_email' => $email,
                'crm_password' => $this->encryptCrmPassword(Str::password(40)),
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        return OfficeCrmUserLink::query()->findOrFail($link->getKey());
    }

    protected function encryptCrmPassword(string $password): string
    {
        return app('encrypter')->encrypt($password, false);
    }

    protected function crmPassword(OfficeCrmUserLink $link): ?string
    {
        try {
            $password = (string) $link->crm_password;

            return filled($password) ? $password : null;
        } catch (DecryptException) {
            return null;
        }
    }

    protected function syncExistingCrmPassword(OfficeCrmUserLink $link, Throwable $reason): bool
    {
        $email = trim((string) $link->crm_email);
        $password = $this->crmPassword($link);

        if ($email === '' || ! filled($password)) {
            return false;
        }

        try {
            $updated = $this->withCrmConnection(function (Connection $connection) use ($email, $password): int {
                return $connection->table('core.user')
                    ->where('email', $email)
                    ->update([
                        'passwordHash' => $this->crmPasswordHash($password),
                        'isEmailVerified' => true,
                        'disabled' => false,
                        'updatedAt' => now(),
                    ]);
            });

            if ($updated < 1) {
                return false;
            }

            $link->forceFill([
                'last_error' => null,
                'metadata' => array_filter([
                    ...($link->metadata ?? []),
                    'password_synced_at' => now()->toIso8601String(),
                    'password_sync_reason' => Str::limit($reason->getMessage(), 240),
                ]),
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $link->forceFill([
                'metadata' => array_filter([
                    ...($link->metadata ?? []),
                    'password_sync_last_error' => Str::limit($exception->getMessage(), 240),
                    'password_sync_failed_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return false;
        }
    }

    protected function crmPasswordHash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        return str_starts_with($hash, '$2y$') ? '$2b$'.substr($hash, 4) : $hash;
    }

    protected function tokensFromCredentials(OfficeCrmUserLink $link): array
    {
        $loginToken = $this->graphql(
            <<<'GRAPHQL'
            mutation CreditsoftCrmLoginToken($email: String!, $password: String!, $origin: String!) {
              getLoginTokenFromCredentials(email: $email, password: $password, origin: $origin) {
                loginToken {
                  token
                  expiresAt
                }
              }
            }
            GRAPHQL,
            [
                'email' => $link->crm_email,
                'password' => $link->crm_password,
                'origin' => $this->baseUrl(),
            ],
            path: 'getLoginTokenFromCredentials.loginToken.token',
        );

        return $this->tokensFromLoginToken($loginToken);
    }

    protected function bootstrapUserAndWorkspace(User $user, OfficeCrmUserLink $link, Throwable $previous): array
    {
        $signUp = $this->graphql(
            <<<'GRAPHQL'
            mutation CreditsoftCrmSignUp($email: String!, $password: String!, $locale: String) {
              signUp(email: $email, password: $password, locale: $locale) {
                tokens {
                  accessOrWorkspaceAgnosticToken {
                    token
                    expiresAt
                  }
                  refreshToken {
                    token
                    expiresAt
                  }
                }
                availableWorkspaces {
                  availableWorkspacesForSignIn {
                    id
                    displayName
                    loginToken
                    workspaceUrls {
                      customUrl
                      subdomainUrl
                    }
                  }
                  availableWorkspacesForSignUp {
                    id
                    displayName
                    loginToken
                    workspaceUrls {
                      customUrl
                      subdomainUrl
                    }
                  }
                }
              }
            }
            GRAPHQL,
            [
                'email' => $link->crm_email,
                'password' => $link->crm_password,
                'locale' => 'en',
            ],
            path: 'signUp',
        );

        $workspaceAgnosticToken = (string) data_get($signUp, 'tokens.accessOrWorkspaceAgnosticToken.token');

        if ($workspaceAgnosticToken === '') {
            throw $this->recordError($link, 'CreditSoft could not create a CRM workspace token.', $previous);
        }

        $newWorkspace = $this->graphql(
            <<<'GRAPHQL'
            mutation CreditsoftCrmNewWorkspace {
              signUpInNewWorkspace {
                loginToken {
                  token
                  expiresAt
                }
                workspace {
                  id
                  workspaceUrls {
                    customUrl
                    subdomainUrl
                  }
                }
              }
            }
            GRAPHQL,
            [],
            bearerToken: $workspaceAgnosticToken,
            path: 'signUpInNewWorkspace',
        );

        $workspaceId = (string) data_get($newWorkspace, 'workspace.id');
        $loginToken = (string) data_get($newWorkspace, 'loginToken.token');

        if ($workspaceId === '' || $loginToken === '') {
            throw $this->recordError($link, 'CreditSoft created the CRM user, but the CRM did not return a workspace login token.', $previous);
        }

        $tokenPair = $this->tokensFromLoginToken($loginToken);

        $this->activateWorkspace($tokenPair, $this->workspaceDisplayName($user));

        $workspaceUrl = $this->workspaceUrl(data_get($newWorkspace, 'workspace.workspaceUrls', []));

        $link->forceFill([
            'crm_workspace_id' => $workspaceId,
            'crm_workspace_url' => $workspaceUrl,
            'metadata' => array_filter([
                ...($link->metadata ?? []),
                'bootstrapped_at' => now()->toIso8601String(),
                'bootstrap_reason' => Str::limit($previous->getMessage(), 240),
            ]),
        ])->save();

        return $tokenPair;
    }

    protected function tokensFromLoginToken(string $loginToken): array
    {
        if ($loginToken === '') {
            throw new RuntimeException('CreditSoft could not open the CRM because the CRM did not return a login token.');
        }

        $tokens = $this->graphql(
            <<<'GRAPHQL'
            mutation CreditsoftCrmTokenPair($loginToken: String!, $origin: String!) {
              getAuthTokensFromLoginToken(loginToken: $loginToken, origin: $origin) {
                tokens {
                  accessOrWorkspaceAgnosticToken {
                    token
                    expiresAt
                  }
                  refreshToken {
                    token
                    expiresAt
                  }
                }
              }
            }
            GRAPHQL,
            [
                'loginToken' => $loginToken,
                'origin' => $this->baseUrl(),
            ],
            path: 'getAuthTokensFromLoginToken.tokens',
        );

        if (! is_array($tokens) || ! filled((string) data_get($tokens, 'accessOrWorkspaceAgnosticToken.token'))) {
            throw new RuntimeException('CreditSoft could not open the CRM because the CRM did not return an access token.');
        }

        return $tokens;
    }

    protected function activateWorkspace(array $tokenPair, string $displayName): void
    {
        $accessToken = (string) data_get($tokenPair, 'accessOrWorkspaceAgnosticToken.token');

        if ($accessToken === '') {
            return;
        }

        $this->graphql(
            <<<'GRAPHQL'
            mutation CreditsoftCrmActivateWorkspace($data: ActivateWorkspaceInput!) {
              activateWorkspace(data: $data) {
                id
                displayName
                activationStatus
              }
            }
            GRAPHQL,
            [
                'data' => [
                    'displayName' => $displayName,
                ],
            ],
            bearerToken: $accessToken,
            path: 'activateWorkspace',
        );
    }

    protected function provisionWorkspaceFromHr(User $user, OfficeCrmUserLink $link): void
    {
        try {
            $this->withCrmConnection(function (Connection $connection) use ($user, $link): void {
                $workspaceId = (string) $link->crm_workspace_id;

                if ($workspaceId === '') {
                    return;
                }

                $workspace = $connection->table('core.workspace')
                    ->where('id', $workspaceId)
                    ->first(['id', 'databaseSchema']);

                $schema = (string) ($workspace?->databaseSchema ?? '');

                if (! preg_match('/^workspace_[a-z0-9_]+$/', $schema)) {
                    return;
                }

                $identity = $this->crmIdentity($user);
                $now = now();

                $connection->table('core.user')
                    ->where('email', $link->crm_email)
                    ->update([
                        'firstName' => $identity['first_name'],
                        'lastName' => $identity['last_name'],
                        'defaultAvatarUrl' => $identity['avatar_url'],
                        'isEmailVerified' => true,
                        'updatedAt' => $now,
                    ]);

                $connection->update(
                    'update '.$this->qualifiedTable($schema, 'workspaceMember').'
                     set "nameFirstName" = ?, "nameLastName" = ?, "userEmail" = ?, "avatarUrl" = ?, "timeZone" = ?, "updatedAt" = ?, "updatedByContext" = ?::jsonb
                     where "userEmail" = ?
                        or "userId" = (select id from core."user" where email = ? limit 1)',
                    [
                        $identity['first_name'],
                        $identity['last_name'],
                        $link->crm_email,
                        $identity['avatar_url'],
                        $identity['timezone'],
                        $now,
                        json_encode([
                            'source' => 'creditsoft_hr',
                            'department' => $identity['department'],
                            'title' => $identity['title'],
                            'synced_at' => now()->toIso8601String(),
                        ], JSON_THROW_ON_ERROR),
                        $link->crm_email,
                        $link->crm_email,
                    ],
                );

                $this->clearCrmOnboardingPrompts($connection, $workspaceId, $link->crm_email);
                $this->applyCreditRepairLabels($connection, $workspaceId);
            });
        } catch (Throwable $exception) {
            $link->forceFill([
                'metadata' => array_filter([
                    ...($link->metadata ?? []),
                    'hr_provision_last_error' => Str::limit($exception->getMessage(), 240),
                    'hr_provision_failed_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }
    }

    protected function syncCreditsoftRoster(OfficeCrmUserLink $link): void
    {
        try {
            $summary = $this->rosterBridge->sync();

            $link->forceFill([
                'metadata' => array_filter([
                    ...($link->metadata ?? []),
                    'roster_bridge_last_summary' => $summary,
                    'roster_bridge_synced_at' => now()->toIso8601String(),
                    'roster_bridge_last_error' => null,
                ]),
            ])->save();
        } catch (Throwable $exception) {
            $link->forceFill([
                'metadata' => array_filter([
                    ...($link->metadata ?? []),
                    'roster_bridge_last_error' => Str::limit($exception->getMessage(), 240),
                    'roster_bridge_failed_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }
    }

    protected function crmIdentity(User $user): array
    {
        $profile = $this->employeeProfileForCrm($user);
        $displayName = trim((string) ($profile?->preferred_name ?: $profile?->legal_name ?: $user->name));
        [$firstName, $lastName] = $this->splitName($displayName);

        return [
            'first_name' => $firstName !== '' ? $firstName : 'CreditSoft',
            'last_name' => $lastName,
            'timezone' => trim((string) ($profile?->timezone ?? '')) ?: (string) config('app.timezone', 'system'),
            'avatar_url' => $user->gravatar_url,
            'department' => trim((string) ($profile?->department ?? '')),
            'title' => trim((string) ($profile?->title ?? '')),
        ];
    }

    protected function employeeProfileForCrm(User $user): EmployeeProfile
    {
        $profile = $user->employeeProfile()->first();

        if (! $profile) {
            return EmployeeProfile::query()->create([
                'user_id' => $user->getKey(),
                'legal_name' => $user->name,
                'preferred_name' => $user->name,
                'department' => $this->departmentForUser($user),
                'title' => $user->primaryRoleLabel(),
                'timezone' => (string) config('app.timezone', 'America/Los_Angeles'),
                'onboarding_status' => 'active',
            ]);
        }

        $defaults = [
            'legal_name' => $user->name,
            'preferred_name' => $user->name,
            'department' => $this->departmentForUser($user),
            'title' => $user->primaryRoleLabel(),
            'timezone' => (string) config('app.timezone', 'America/Los_Angeles'),
            'onboarding_status' => 'active',
        ];

        $dirty = false;

        foreach ($defaults as $field => $value) {
            if (blank($profile->{$field}) && filled($value)) {
                $profile->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $profile->save();
        }

        return $profile;
    }

    protected function departmentForUser(User $user): string
    {
        return match ($user->roles->pluck('name')->first()) {
            'owner_admin', 'admin', 'demo_admin' => 'Leadership',
            'social_media_manager' => 'Social Media',
            'case_manager' => 'Case Management',
            'ai-operator' => 'AI Operations',
            default => 'Operations',
        };
    }

    protected function splitName(string $displayName): array
    {
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];
        $firstName = trim((string) array_shift($parts));
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    protected function applyCreditRepairLabels(Connection $connection, string $workspaceId): void
    {
        $labels = [
            'person' => ['Client', 'Clients', 'IconUsers', 0],
            'opportunity' => ['Lead', 'Leads', 'IconTargetArrow', 1],
            'company' => ['Affiliate', 'Affiliates', 'IconBuildingStore', 2],
        ];

        foreach ($labels as $objectName => [$singular, $plural, $icon]) {
            $connection->table('core.objectMetadata')
                ->where('workspaceId', $workspaceId)
                ->where('nameSingular', $objectName)
                ->update([
                    'labelSingular' => $singular,
                    'labelPlural' => $plural,
                    'icon' => $icon,
                    'updatedAt' => now(),
                ]);
        }

        foreach ($labels as $objectName => [, , , $position]) {
            $connection->update(
                'update core."navigationMenuItem" as n
                 set "position" = ?, "updatedAt" = ?
                 from core."objectMetadata" as o
                 where n."targetObjectMetadataId" = o.id
                   and o."workspaceId" = ?
                   and o."nameSingular" = ?
                   and n."folderId" is null',
                [
                    $position,
                    now(),
                    $workspaceId,
                    $objectName,
                ],
            );
        }
    }

    protected function clearCrmOnboardingPrompts(Connection $connection, string $workspaceId, string $email): void
    {
        $keys = [
            'ONBOARDING_CREATE_PROFILE_PENDING',
            'ONBOARDING_CONNECT_ACCOUNT_PENDING',
            'ONBOARDING_INVITE_TEAM_PENDING',
            'ONBOARDING_BOOK_ONBOARDING_PENDING',
        ];

        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $connection->delete(
            'delete from core."keyValuePair"
             where "type"::text = ?
               and "key" in ('.$placeholders.')
               and (
                    ("workspaceId" = ? and ("userId" is null or "userId" = (select id from core."user" where email = ? limit 1)))
                    or ("workspaceId" is null and "userId" = (select id from core."user" where email = ? limit 1))
               )',
            [
                'USER_VARIABLE',
                ...$keys,
                $workspaceId,
                $email,
                $email,
            ],
        );
    }

    protected function withCrmConnection(callable $callback): mixed
    {
        $name = 'creditsoft_crm_sidecar';
        $database = config('creditsoft.integrations.crm.database', []);

        config([
            "database.connections.{$name}" => [
                'driver' => 'pgsql',
                'host' => (string) ($database['host'] ?? '127.0.0.1'),
                'port' => (string) ($database['port'] ?? '5432'),
                'database' => (string) ($database['database'] ?? 'crm'),
                'username' => (string) ($database['username'] ?? 'crm'),
                'password' => (string) ($database['password'] ?? ''),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'core',
                'sslmode' => 'prefer',
            ],
        ]);

        DB::purge($name);

        try {
            return $callback(DB::connection($name));
        } finally {
            DB::disconnect($name);
        }
    }

    protected function qualifiedTable(string $schema, string $table): string
    {
        return sprintf('%s.%s', $this->quoteIdentifier($schema), $this->quoteIdentifier($table));
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    protected function graphql(string $query, array $variables = [], ?string $bearerToken = null, ?string $path = null): mixed
    {
        $request = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Origin' => $this->baseUrl(),
            ]);

        if (filled($bearerToken)) {
            $request = $request->withToken($bearerToken);
        }

        $response = $request->post($this->graphqlEndpoint(), [
            'query' => $query,
            'variables' => (object) $variables,
        ]);

        $this->throwIfGraphqlFailed($response);

        $payload = $response->json();

        if (filled($path)) {
            return data_get($payload, 'data.'.$path);
        }

        return Arr::get($payload, 'data', []);
    }

    protected function throwIfGraphqlFailed(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('CRM returned HTTP '.$response->status().'. '.Str::limit($response->body(), 300));
        }

        $payload = $response->json();
        $errors = collect(Arr::get($payload, 'errors', []))
            ->map(fn (array $error): string => (string) ($error['message'] ?? 'CRM GraphQL error'))
            ->filter()
            ->values();

        if ($errors->isNotEmpty()) {
            throw new RuntimeException($errors->implode(' '));
        }
    }

    protected function welcomeUrl(array $tokenPair, string $email): string
    {
        $url = rtrim($this->baseUrl(), '/').'/welcome';
        $query = http_build_query([
            'tokenPair' => json_encode($tokenPair, JSON_THROW_ON_ERROR),
            'email' => $email,
        ], '', '&', PHP_QUERY_RFC3986);

        return "{$url}?{$query}";
    }

    protected function workspaceDisplayName(User $user): string
    {
        $company = trim((string) config('creditsoft.office.company', ''));

        return $company !== '' ? "{$company} CRM" : 'CreditSoft CRM';
    }

    protected function workspaceUrl(mixed $workspaceUrls): ?string
    {
        $customUrl = (string) data_get($workspaceUrls, 'customUrl', '');
        $subdomainUrl = (string) data_get($workspaceUrls, 'subdomainUrl', '');

        return $customUrl !== '' ? $customUrl : ($subdomainUrl !== '' ? $subdomainUrl : null);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('creditsoft.integrations.crm.base_url', ''), '/');
    }

    protected function graphqlEndpoint(): string
    {
        return $this->baseUrl().'/metadata';
    }

    protected function recordError(OfficeCrmUserLink $link, string $message, Throwable $previous): RuntimeException
    {
        $error = "{$message} {$previous->getMessage()}";

        $link->forceFill([
            'last_error' => Str::limit($error, 1000),
        ])->save();

        return new RuntimeException($error, previous: $previous);
    }
}
