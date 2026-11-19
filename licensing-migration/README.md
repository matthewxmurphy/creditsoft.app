# CreditSoft Amazon licensing source checkpoint

Source checkpoint: **2026.11.18.001**. Author/developer and release owner:
[Matthew X Murphy](https://MatthewxMurphy.com),
[Built Not Begged](https://builtnotbegged.com). The existing application Composer
metadata declares MIT; this scoped source checkpoint preserves that license.
It does not change the separate WordPress/Gantry website or its GPL components.

The four-file modern reconciliation and its three test files are committed as
`8c99ab064c2889f829dde1add1c841ecb8b703b1`, pushed by the root operator to
`codex/aws-license-authority-20261118`. The prior `0cdaa94` checkpoint remains in
Git history. These source changes have not been deployed as an Office app.

## Resulting behavior

Office license checks and update requests use only the owner's pinned Amazon
authority. Historical/custom endpoint values remain stored but cannot redirect
credentials, even to another AWS tenant. Office edition, feature, grace and
installed-workspace policies remain the existing policies.

The modern checkpoint now includes the two reviewed policy fixes from the
separately published genuine Office primary: `packaged-pro` / `packaged_pro`
retain the Pro edition, and Cluster receives its established explicit closed
feature map instead of permissive empty-feature defaults. The two paid legacy
operator entrypoints are also closed: non-dry-run `creditsoft:release` returns
failure before feed/config/service work, and the disk archive builder throws
before any filesystem, Composer or ZIP work. Read-only release previews retain
the modern Chicago calendar and version validation.

New code requires fresh Amazon authorization. Updates use private Amazon HTTPS
grants, scoped cache keys, integer size, MD5 and SHA256 verification, temporary
downloads and verified cache reuse. Transport errors do not expose a signed URL
through exception chains. There is no public/local Office update fallback.

The primary must be named `creditsoft-office-v<release-version>.zip` and contain
the Office/Laravel application structure. Browser/SDK archives cannot substitute
for it. Archive traversal and symlink entries are rejected before extraction.
The actual primary Office application release has **not** been built or
published by this source lane.

The licensed browser companion keeps its existing `browser_companion` feature
gate. Its download additionally requires the corresponding feature-scoped AWS
artifact, matching hashes/version and the actual Companion Capture extension
identity. It cannot fall back to public Net30 ZIPs or a local source build.

Node installers stage a fresh AWS-authorized, verified Office primary and bundle
those exact bytes. They do not prefer local Net30 feeds, read old public ZIPs or
ship an expiring presigned URL for a later installation. Installer environment
defaults point at the same Amazon authority.

The employee workstation client remains the existing public/unlicensed router
installer. It is not a new paid SKU. Its staff-token/API pairing remains
separate from the Office license. Internal OPS extensions are not customer
browser companion artifacts and are not packaged for this channel.

## What is verified and what remains

`php tests/standalone/aws_license_authority.php` passes 71 isolated checks using
fake HTTP and synthetic local ZIPs. They cover pinned endpoints, edition and
installed-access preservation, strict product/metadata/type binding, fresh
authorization, no local/public paid fallback, Node primary bundling without
embedded grants, browser feature separation, archive paths and poisoned caches.
All changed PHP files are syntax checked. No real key, service, customer, node,
browser installation, OS service, mail or provider is contacted by that fixture.

`python3 tests/standalone/run_legacy_paid_operator.py --php-assertions on`
passes 63 checks against exact copied modern source files. The disposable PHP
fixture disables network, mail and process functions, boots no application and
uses no real filesystem facade. It verifies both write entrypoints stop before
side effects, two dry-run cases remain read-only, and three existing calendar /
version denials remain. Repeat with Python `-O` and `--php-assertions off` to
verify the guards do not depend on assertions. The temporary fixture is removed.

This branch and its source-patch ZIP are private source backups. The existing
Office backend migration to AWS was performed by the separate root operator;
this lane performed no live client deployment. The root operator separately
published the genuine recovered full Office `2026.11.18.001` primary
(`622caee227b7501b6db053315fe8ff14cf4df225c02d512c9af6c30a77f84bfb`),
with native acceptance and current-owner delivery proof. That older application
payload is distinct from this modern source tree. Its full source never
overwrites the modern repository. Native Laravel/update and installed-client
acceptance of this modern checkpoint remain required before its customer
rollout. Missing dependency trees were not downloaded for this checkpoint.

The accompanying artifact receipt distinguishes source patch, browser extension
and employee-client source. None is a substitute primary Office application.
Actual historical files on old update hosts require their own backup/retirement
verification; this offline source lane does not claim those files are removed.
The earlier source-patch archive at commit `0cdaa94` remains a historical source
backup and was not rebuilt by this reconciliation.

No actually used Laravel publisher or deployment target is established by this
source tree. Root's bounded stat audit found the two candidate application files
absent below the selected admin document root. That is not a host-wide absence
claim, and no active application is invented or deployed from that finding.

## Run and release

Run the standalone fixture above and review the exact scoped Git diff. Root
publishes the private source branch through the existing remote after review.
Use the existing guarded fleet publisher for actual immutable AWS releases,
with a genuine verified Office primary and the original product/feature grants.
Never register this source-patch ZIP, a browser extension or an SDK as that
primary. No credential, environment state, customer database, storage directory,
private signing key or live generated installer belongs in source packages.

Keep current runtime state and a verified code backup when deploying this
checkpoint. Roll back only the changed application code through the reviewed
forward release process; do not restore retired licensing authorities or replace
live customer state with a source snapshot.
