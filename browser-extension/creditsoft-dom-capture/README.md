# CreditSoft Companion Capture

This companion helper ships with the CreditSoft package and posts the active page directly into CreditSoft. If the API is unreachable, it falls back to a JSON export that CreditSoft can ingest from the client browser evidence panel.

## What it captures

- page title
- page URL
- full DOM HTML
- selected text
- browser user agent
- capture timestamp
- optional operator note
- client lookup fields for email, first name, and last name
- optional cycle label so the server can drop the capture into the right review period
- package metadata so CreditSoft can recognize the helper as bundled tooling

## How to load it

The cleanest path is to download the bundled `CreditSoft browser companion` pack from the installer, Connectivity settings, or the client browser-evidence panel, then unzip it once on the office machine.

1. Open Chrome, Arc, or another Chromium browser.
2. Go to `chrome://extensions`.
3. Enable `Developer mode`.
4. Click `Load unpacked`.
5. Select the extracted `creditsoft-dom-capture` folder.

## How to use it

1. Open the credit report or source page you want to snapshot.
2. Open `chrome://extensions`, open the CreditSoft companion details, and use `Extension options` to save the API base and office token once.
3. Click the CreditSoft companion icon.
4. Add the customer email or name fields, plus an optional cycle label or note.
5. Click `Send current page`.
6. If the API cannot be reached, the companion will export a JSON fallback instead.

Safari `.webarchive` files can also be uploaded directly in the CreditSoft UI. The companion direct-post path is the cleanest way to package a browser snapshot into the same reporting-cycle pipeline CreditSoft uses for the rest of the casework.

If you are using Safari instead of Chromium, follow the steps in [SAFARI-QUICKSTART.md](./SAFARI-QUICKSTART.md).
