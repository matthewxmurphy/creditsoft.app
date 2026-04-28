import { existsSync, readFileSync } from "node:fs";

const token = process.env.META_GRAPH_ACCESS_TOKEN || process.env.META_ACCESS_TOKEN || "";
const source =
  process.argv[2] ||
  (existsSync("dist/social-preview-urls.txt")
    ? "dist/social-preview-urls.txt"
    : "https://www.creditsoft.app/social-preview-urls.txt");

const text = source.startsWith("http")
  ? await fetch(source).then((response) => response.text())
  : readFileSync(source, "utf8");

const urls = text
  .split(/\r?\n/)
  .map((line) => line.trim())
  .filter((line) => line.startsWith("http"));

if (!token) {
  console.log("META_GRAPH_ACCESS_TOKEN is not set. Open these Sharing Debugger URLs instead:");
  for (const url of urls) {
    console.log(`https://developers.facebook.com/tools/debug/?q=${encodeURIComponent(url)}`);
  }
  process.exitCode = 1;
} else {
  for (const url of urls) {
    const params = new URLSearchParams({
      id: url,
      scrape: "true",
      access_token: token,
    });

    try {
      const response = await fetch("https://graph.facebook.com/", {
        method: "POST",
        body: params,
      });
      console.log(`${url}: ${response.status} ${response.statusText}`);
    } catch (error) {
      console.log(`${url}: failed - ${error instanceof Error ? error.message : String(error)}`);
    }
  }
}
