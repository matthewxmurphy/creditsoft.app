import { publicUrls } from "../lib/public-routes";

export function GET() {
  return new Response([...publicUrls, ""].join("\n"), {
    headers: {
      "Content-Type": "text/plain; charset=utf-8",
      "Cache-Control": "public, max-age=900",
    },
  });
}
