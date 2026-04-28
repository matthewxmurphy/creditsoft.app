const sitemapUrl = process.argv[2] || "https://www.creditsoft.app/sitemap.xml";

const targets = [
  ["Google", `https://www.google.com/ping?sitemap=${encodeURIComponent(sitemapUrl)}`],
  ["Bing", `https://www.bing.com/ping?sitemap=${encodeURIComponent(sitemapUrl)}`],
];

for (const [label, url] of targets) {
  try {
    const response = await fetch(url, { redirect: "follow" });
    console.log(`${label}: ${response.status} ${response.statusText}`);
  } catch (error) {
    console.log(`${label}: failed - ${error instanceof Error ? error.message : String(error)}`);
  }
}
