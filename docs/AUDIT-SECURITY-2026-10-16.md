# Unreleased security maintenance — 2026-10-16

Author/developer: Matthew X Murphy, https://MatthewxMurphy.com.
Product attribution: https://builtnotbegged.com. Existing owners, grants and third-party notices are preserved; no new fleet-wide license is selected.

## Local development authentication

The optional local developer bypass now requires APP_ENV=local, explicit enablement, a raw REMOTE_ADDR of 127.0.0.1 or ::1, no Forwarded/X-Real-IP or any X-Forwarded-* header, and an allowed Host. Host controls routing only. Production requests, remote peers and forwarding headers cannot select an owner identity. Existing API/logout and authenticated-user exclusions remain.

Keep CREDITSOFT_LOCAL_AUTH_BYPASS disabled behind every reverse proxy, tunnel or forwarded port, including development: a completely headerless local intermediary is indistinguishable from a direct local socket. This middleware does not establish a trusted-proxy identity. The default remains disabled. Enabling it in any effective application environment other than exactly `local` now throws a `LogicException` at application startup, before the application's environment-file synchronization, installer-state reads or observer registration. Disable it before starting production, staging or ordinary test environments; no operator configuration is changed by this source patch.

The public source's web-meta/api-bridge.json is removed from the Git index prospectively; the working copy was privately byte-preserved, and the exact runtime path is ignored. Use api-bridge.example.json only as a schema and provision real credentials privately. No source-history rewrite or credential validation/rotation was performed. Treat previously committed bridge/provider/DB material as exposed until its owner inventories and rotates applicable credentials. Deleting a file from current source does not revoke prior access.

## Verification and operation

From the repository root, install locked test dependencies with `composer install --no-scripts --no-plugins`, then run `vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/LocalAuthBypassStartupTest.php`. This focused startup test calls the real application provider against an in-memory configuration container, rejects unsafe environments before service resolution and does not boot the kernel or load environment files.

The request suite is `tests/Feature/LocalAuthBypassSecurityTest.php`. Run it only in a disposable source export with no real `.env`, runtime files or bootstrap caches. The ordinary Laravel test kernel boots before a test's `setUp`/factory operations; phpunit.xml alone is not an isolation boundary. Before provider registration, bind recording-only `EnvironmentEditor` and `CreditsoftClusterDatabaseSyncService` services, and verify effective SQLite `:memory:` with no database URL, array mail/cache/session, synthetic APP_KEY, and owned absent installer/YAML paths. Block HTTP/mail/queue before provider boot, audit discovered providers and avoid console command discovery. Native acceptance additionally requires unsafe enabled startup to fail before environment synchronization, normal disabled startup, explicit local IPv4/IPv6 login, remote/Host/forwarding denial, and preserved API/logout/authenticated-user exclusions. Synthetic fixtures do not establish production/proxy configuration or replace separately authorized runtime verification.

No schema migration, live user edit, provider call, source version bump or deployment is part of this change. Review the PR and locked dependencies before rollout; preserve the current deployed source and private configuration. Roll back with a scoped source revert while retaining actual account, payment and customer state. Never restore an exposed credential as rollback. Runtime verification must separately prove denial for unauthorized requests and normal authorized behavior.

## Standards adoption

The shared engineering SOP was read at https://github.com/matthewxmurphy/developer-standards. Exact candidate source is checked for secrets before push, and the focused security behavior is tested. Complete license assignment, third-party inventory, engineering.json/portable-checker enrollment, CI enforcement, broader architecture/API documentation and full release/runtime qualification remain recorded gaps. Public repositories do not vendor the internally distributed portable checker. Existing versions and release artifacts remain unchanged.
