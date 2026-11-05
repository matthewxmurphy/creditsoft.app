# Child-theme notices

New child customization, particles, styles, page template, setup data, tests and
documentation: copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>,
product/support <https://builtnotbegged.com>, GPL-2.0-or-later. Recorded policy
identity: Matthew X Murphy. This is an unreleased 2026.10.1 source proposal.

`includes/theme.php` is adapted from official Helium 5.6.4. Its original Tiger12,
RocketTheme and GPLv2-and-later notices are retained. The documented child stream
fallback and maintenance comments are the only changes to that inherited PHP body.
`gantry/theme.yaml` derives the parent configuration, fonts and variation definitions
from the same release, with child identity, parent binding and no child updater.
Inherited Helium notice: original creator RocketTheme; current developer Tiger12,
LLC; copyright (C) 2007–2022 Tiger12, LLC; GNU GPLv2 and later.

The wordmark at `assets/creditsoft-wordmark.png` is an unchanged copy of the public
CreditSoft `site-astro/public/brand/creditsoft-wordmark.png`. Homepage/navigation
copy comes from the existing public Astro defaults and components. The browser
and Apple touch icons are unchanged copies of `site-astro/public/favicon.svg`,
`site-astro/public/favicon.ico` and `site-astro/public/apple-touch-icon.png`. Those source
assets have no explicit separate license identifier; this notice does not assign
a new blanket grant to those assets or the wider CreditSoft repository.

Gantry/Helium and Elementor remain separately installed upstream packages with
their own notices. No proprietary/pro package, credential or vendor dependency
is redistributed here.

The three static marketing exports preserve displayed public copy from
`site-astro/src/pages/about-us.astro`, `resources.astro` and `docs.astro`.
The source `about.astro` alias is documented rather than turned into a new runtime
redirect. New export structure, allowlisted preparation helper and focused tests
use the same scoped first-party notices above; the underlying published copy and
wider repository are not assigned a new blanket license by this adaptation.

The Multi-office export preserves the selected public `site-lanes.ts` data and
`[slug].astro` body. `assets/multi-office-node-topology.png` is an unchanged copy of
`site-astro/public/assets/images/product-proof/multi-office-node-topology.png`,
including its original rendered labels. Like the published wordmark, this asset
has no separate explicit license identifier in the source; its reuse here does
not assign a new blanket grant to Astro, those assets or the wider repository.
New export/helper/test work carries the existing scoped first-party notices.

The Contact export preserves public `site-astro/src/pages/contact-us.astro` copy
and passive destinations. The new binding and minimal form definition are scoped
first-party work; their field/import structure targets the separately installed
Fluent Forms FREE 6.2.16 API. No Fluent Forms vendor PHP/JS is bundled or relicensed
by this child. Native rendering, validation, storage and admin import stay with
that GPL-licensed upstream plugin.

The Cookies, Privacy and Terms exports preserve the current public Astro policy
copy from `site-astro/src/pages/cookies.astro`, `privacy.astro` and `terms.astro`.
New Elementor structure and parity tests carry the scoped first-party notices;
this adaptation does not rewrite those policies or assign a blanket new license
to the published copy or wider repository.
