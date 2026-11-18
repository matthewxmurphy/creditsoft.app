# CreditSoft Amazon licensing source checkpoint

Source checkpoint: **2026.11.18.001**. Author/developer and release owner:
[Matthew X Murphy](https://MatthewxMurphy.com),
[Built Not Begged](https://builtnotbegged.com). The existing application Composer
metadata declares MIT; this scoped source checkpoint preserves that license.
It does not change the separate WordPress/Gantry website or its GPL components.

## Resulting behavior

Office license checks and update requests use only the owner's pinned Amazon
authority. Historical/custom endpoint values remain stored but cannot redirect
credentials, even to another AWS tenant. Office edition, feature, grace and
installed-workspace policies remain the existing policies.

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

`php tests/standalone/aws_license_authority.php` passes 50 isolated checks using
fake HTTP and synthetic local ZIPs. They cover pinned endpoints, edition and
installed-access preservation, strict product/metadata/type binding, fresh
authorization, no local/public paid fallback, Node primary bundling without
embedded grants, browser feature separation, archive paths and poisoned caches.
All changed PHP files are syntax checked. No real key, service, customer, node,
browser installation, OS service, mail or provider is contacted by that fixture.

This branch and its source-patch ZIP are private source backups. The existing
Office backend migration to AWS was performed by the separate root operator;
this lane performed no live client deployment. A full primary Office archive,
native Laravel/update application proof, actual auxiliary package/channel
acceptance and installed desktop/browser checks remain required before customer
rollout. Missing dependency trees were not downloaded for this checkpoint.

The accompanying artifact receipt distinguishes source patch, browser extension
and employee-client source. None is a substitute primary Office application.
Actual historical files on old update hosts require their own backup/retirement
verification; this offline source lane does not claim those files are removed.

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
