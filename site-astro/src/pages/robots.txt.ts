import { siteOrigin } from "../lib/public-routes";

export function GET() {
  return new Response(
    [
      "User-agent: *",
      "Allow: /",
      "Disallow: /admin/",
      "Disallow: /api/",
      "Disallow: /vendor/",
      `Sitemap: ${siteOrigin}/sitemap.xml`,
      "",
    ].join("\n"),
    {
      headers: {
        "Content-Type": "text/plain; charset=utf-8",
        "Cache-Control": "public, max-age=900",
      },
    },
  );
}
