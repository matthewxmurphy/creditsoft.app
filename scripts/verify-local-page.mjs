import { existsSync } from 'node:fs';
import { chromium } from 'playwright';

const [target = 'http://127.0.0.1:8877/hr', expectedText = ''] = process.argv.slice(2);

const browserCandidates = [
    process.env.PLAYWRIGHT_CHROME_EXECUTABLE,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
].filter(Boolean);

const executablePath = browserCandidates.find((path) => existsSync(path));
const browser = await chromium.launch({
    executablePath,
    headless: process.env.PW_HEADED !== '1',
    args: ['--no-sandbox'],
});

const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const browserMessages = [];

page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type())) {
        browserMessages.push(`${message.type()}: ${message.text()}`);
    }
});
page.on('pageerror', (error) => {
    browserMessages.push(`pageerror: ${error.message}`);
});

await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

const bodyText = await page.locator('body').innerText({ timeout: 10000 });
const heading = await page.locator('h1, h2').first().innerText({ timeout: 10000 }).catch(() => '');
const matchedExpected = expectedText === '' || bodyText.includes(expectedText);

await browser.close();

console.log(JSON.stringify({
    ok: matchedExpected && browserMessages.length === 0,
    url: target,
    browser: executablePath ?? 'playwright-default',
    heading,
    expectedText: expectedText || null,
    matchedExpected,
    browserMessages,
}, null, 2));

if (!matchedExpected || browserMessages.some((message) => message.startsWith('pageerror:'))) {
    process.exitCode = 1;
}
