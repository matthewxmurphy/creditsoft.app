# CreditSoft Client Portal Document Scanner

CreditSoft version: 2026.4.29.1

The portal document scanner is a drop-in helper for client upload forms. It keeps document review local in the browser, then sends a compact `document_quality` JSON field with the uploaded file so the intranet can record whether the image looked usable or needed a retake.

Use it on any multipart upload form that posts `document_file` to the CreditSoft document API:

```html
<link rel="stylesheet" href="/assets/portal/client-document-scanner.css">

<form
    method="post"
    enctype="multipart/form-data"
    action="/api/v1/clients/{clientCuid}/documents"
    data-creditsoft-document-scanner
>
    <input type="text" name="title" value="Photo of Drivers License">
    <input type="file" name="document_file" accept="image/*,.pdf,.heic,.heif">
    <button type="submit">Upload document</button>
</form>

<script src="/assets/portal/client-document-scanner.js" defer></script>
```

The script also auto-enhances forms that contain `input[name="document_file"]`, so existing portal forms can usually add only the CSS and JS includes.

## What It Checks

- IDs and Social Security cards should be photographed sideways.
- The document should fill the frame instead of being a tiny part of a large room photo.
- The image should be bright enough, not washed out, and not obviously blurry.
- Camera captures are cropped to a wide card frame for ID-style documents.
- PDFs are accepted without image analysis and still upload normally.

## API Field

The browser submits `document_quality` as JSON in the multipart request. The intranet stores it under `metadata.portal_capture` on the client document and returns the quality summary when listing portal-visible documents.

Example:

```json
{
  "status": "retake_recommended",
  "score": 63,
  "warnings": [
    {
      "code": "turn_phone_sideways",
      "message": "Turn the phone sideways and retake it so the card fills a wide frame."
    }
  ],
  "dimensions": {
    "width": 1170,
    "height": 2532
  },
  "orientation": "portrait",
  "document_frame_ratio": 0.462,
  "reviewed_at": "2026-04-29T00:00:00.000Z"
}
```

The server runs a second lightweight image inspection so uploads from older portal pages still get a quality flag.
