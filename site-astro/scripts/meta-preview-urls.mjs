import { existsSync, readFileSync } from "node:fs";

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

for (const url of urls) {
  console.log(`https://developers.facebook.com/tools/debug/?q=${encodeURIComponent(url)}`);
}
