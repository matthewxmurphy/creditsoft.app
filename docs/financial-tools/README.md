# CreditSoft financial tools

The public collection at https://creditsoft.app/tools/ runs inside WordPress and
the CreditSoft Gantry 6 theme. It includes twelve calculators and worksheets:
budget, payoff, utilization, consultation prep, strategy, score scenario,
optimizer, funding readiness, negative-item review, debt comparison, timeline and
account helper. Every tool and the collection index retain the visible ordinary
HTML attribution link to https://credit-essense.com/tools/.

Calculations run entirely in the browser. These pages use no account, client API,
office connection, financial-input storage or database writes. Their captured
page-specific assets omit Analytics, Meta and chat scripts. Print opens the local
browser print dialog.

## Maintained WordPress source

- `wordpress/creditsoft-financial-tools/assets/catalog.mjs`: inventory, inputs,
  bounds and assumptions.
- `assets/model.mjs`: deterministic calculations and worksheet outputs.
- `assets/client.mjs`: accessible controls and text-only DOM result handling.
- `wordpress/creditsoft-financial-tools/tests/model.test.mjs`: preserved numeric,
  boundary, rollover, privacy and published-seed checks.
- `wordpress/creditsoft-helium/content/preserved/pages/tools*.html`: initial
  WordPress seeds and captured presentation/behavior.

Native WordPress Pages are the live content authority. The preserved theme assets
keep the published appearance and existing formulas. The calculator plugin also
provides a shortcode/block and fallback routes for deliberately selected future
pages; existing native Pages take precedence.

```sh
node wordpress/creditsoft-financial-tools/scripts/prepare-catalog.mjs
node --test wordpress/creditsoft-financial-tools/tests/*.test.mjs
```

Native tests remain in `wordpress/creditsoft-financial-tools/tests/native-acceptance.php`
and the theme's guarded disposable-fixture checks. Source tests do not replace
actual WordPress/browser acceptance.

## Verified conversion

On November 15, 2026, Chrome verified all twelve default results and enabled
controls on WordPress. Payoff returned 30 months from its defaults, four months
for a $100 balance at 0% with $30 monthly payments, and restored the defaults on
Reset. At 390px the WordPress tools and mobile navigation had no horizontal
overflow. Videos, header/footer, source attribution and browser-local behavior
were also checked during migration. Follow `wordpress/migration/README.md` for
the current theme deployment and private rollback procedure.

The old static 17-file deployment scripts and public build tree have been removed.
Earlier September 22 release details remain in Git history and private deployment
receipts; they are not an active deployment procedure.
