# Changes

## 2026.10.1 — unreleased proposal

- Adapt the existing 12 calculators to a shared WordPress shortcode/dynamic block.
- Add exact GET/HEAD fallback `/tools/` routes only for otherwise-unowned 404s;
  keep core HEAD exits bodyless and preserve exact private/draft page paths.
- Preserve published calculator modules; scope new styling and enforce one live
  calculator per page. Leave the existing site, leads, checkout and deployment alone.
- Include the virtual tools index and calculator URLs in WordPress’s native
  sitemap when indexing and pretty permalinks are enabled. Keep editor-owned
  paths out of the tools sitemap so WordPress handles their discovery.
- Let the site append content below the virtual tools index and calculator pages
  before the theme prints its asset tags.
- Let classic themes use a neutral tools wrapper when they supply the main
  landmark.
- Declare the canonical tools URLs on rendered fallback pages, using the same
  paths as the sitemap and preserving the configured WordPress home base.
- Keep the published page descriptions on the tools index and calculator fallbacks.
- Keep calculator inputs out of HTTP requests when Enter is pressed with
  JavaScript unavailable.
- Omit stale results from browser prints after calculator inputs change and
  show the instruction to recalculate.
- Give changed calculator styles a new URL so browsers load them after a
  private plugin replacement at the same proposal version.
