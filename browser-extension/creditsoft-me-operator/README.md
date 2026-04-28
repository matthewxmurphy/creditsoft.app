# CreditSoft OPS

Internal-only Chrome overlay for OPS migration work.

## Load it

1. Unzip or copy the merged operator bundle.
2. Open Chrome and go to `chrome://extensions`.
3. Turn on `Developer mode`.
4. Click `Load unpacked` and choose this extension folder.

## Set it up

1. Open the extension settings.
2. Save your private `API base URL` if you do not want the local auto-detect path.
3. Save your private CreditSoft OPS key.
4. Click `Test ping` to confirm the private API is ready.

## Use it

1. Open the page you want to migrate.
2. Open the popup from the toolbar.
3. Click `Stage current page` to capture the live page HTML, URL, title, and field candidates.
4. Click `Import letter or library` when the page is a letter detail page or the DisputeFox library index.

On a DisputeFox library page, the operator will:

- detect the `LetterID` detail links on the page
- walk them one by one in the active tab
- import each one into CreditSoft's internal template lane
- return to the library page when it finishes

## API shape assumed

This overlay talks to:

- `GET /api/v1/migration-operator/ping`
- `POST /api/v1/migration-operator/captures`
- `POST /api/v1/migration-operator/letter-templates`

It sends:

- `source_system`
- `capture_type`
- `page_title`
- `page_url`
- `operator_note`
- `html`
- `metadata`

The `Import letter or library` button uses the internal-only `letter-templates` endpoint so the page can land directly in CreditSoft's imported template lane. It also preserves the DisputeFox-specific structured fields we care about in metadata:

- category (`lettercategory`)
- letter title (`letterTitle`)
- description (`letterDescription`)
- body field name (`letterEditor`)
