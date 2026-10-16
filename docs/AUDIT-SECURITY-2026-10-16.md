# Unreleased security maintenance — 2026-10-16

Author/developer: Matthew X Murphy, https://MatthewxMurphy.com.
Product attribution: https://builtnotbegged.com. Existing owners, grants and third-party notices are preserved; no new fleet-wide license is selected.

## Local development authentication

The optional local developer bypass now requires APP_ENV=local, explicit enablement, a raw REMOTE_ADDR of 127.0.0.1 or ::1, no Forwarded/X-Real-IP or any X-Forwarded-* header, and an allowed Host. Host controls routing only. Production requests, remote peers and forwarding headers cannot select an owner identity. Existing API/logout and authenticated-user exclusions remain.

Keep CREDITSOFT_LOCAL_AUTH_BYPASS disabled behind every reverse proxy, tunnel or forwarded port, including development: a completely headerless local intermediary is indistinguishable from a direct local socket. This middleware does not establish a trusted-proxy identity. The default remains disabled. A production environment fails closed even when explicitly enabled.

The public source's web-meta/api-bridge.json is removed from the Git index prospectively; the working copy was privately byte-preserved, and the exact runtime path is ignored. Use api-bridge.example.json only as a schema and provision real credentials privately. No source-history rewrite or credential validation/rotation was performed. Treat previously committed bridge/provider/DB material as exposed until its owner inventories and rotates applicable credentials. Deleting a file from current source does not revoke prior access.

## Verification and operation

Native Laravel tests use a disposable SQLite in-memory database and synthetic users, never production configuration. Run the focused case with `DB_CONNECTION=sqlite DB_DATABASE=:memory: MAIL_MAILER=array php artisan test --filter=LocalAuthBypassSecurityTest` in the documented test environment. Keep a synthetic APP_KEY in the isolated shell; do not load a live .env. Existing phpunit.xml environment values are not forced, so verify effective database settings before any test.

No schema migration, live user edit, provider call, source version bump or deployment is part of this change. Review the PR and locked dependencies before rollout; preserve the current deployed source and private configuration. Roll back with a scoped source revert while retaining actual account, payment and customer state. Never restore an exposed credential as rollback. Runtime verification must separately prove denial for unauthorized requests and normal authorized behavior.

## Standards adoption

The shared engineering SOP was read at https://github.com/matthewxmurphy/developer-standards. Exact candidate source is checked for secrets before push, and the focused security behavior is tested. Complete license assignment, third-party inventory, engineering.json/portable-checker enrollment, CI enforcement, broader architecture/API documentation and full release/runtime qualification remain recorded gaps. Public repositories do not vendor the internally distributed portable checker. Existing versions and release artifacts remain unchanged.
