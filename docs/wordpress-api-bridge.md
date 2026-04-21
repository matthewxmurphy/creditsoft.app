# CreditSoft WordPress API Bridge

The free WordPress add-on lives in `wordpress/creditsoft-api-bridge`.

It is meant for customer websites that need to send leads into CreditSoft without exposing the intranet API key in browser JavaScript. The plugin stays free, but it only works after the CRO adds a valid website API key generated inside CreditSoft.

## Install

1. Download `public/downloads/creditsoft-api-bridge.zip` from the CreditSoft API docs download button.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload `creditsoft-api-bridge.zip`.
4. Activate **CreditSoft API Bridge**.
5. Open **Settings > CreditSoft API Bridge**.
6. Set the CreditSoft API base URL. The default is `https://www.creditsoft.app/api/v1`.
7. Paste the website API key from CreditSoft Connectivity.
8. Add `[creditsoft_lead_form]` to the WordPress intake, portal, or consultation page.

Manual install:

1. Unzip `creditsoft-api-bridge.zip`.
2. Upload `creditsoft-api-bridge` to `wp-content/plugins/`.
3. Activate **CreditSoft API Bridge**.

## Shortcodes

Basic form:

```text
[creditsoft_lead_form]
```

Portal access style:

```text
[creditsoft_lead_form title="Request portal access" button="Request access"]
```

Affiliate-specific landing page:

```text
[creditsoft_lead_form affiliate_key="credit-sense"]
```

Optional score field:

```text
[creditsoft_lead_form show_score="true"]
```

## Affiliate Tracking

The plugin captures these query string values and stores the selected key in a 30-day cookie:

```text
affiliate_key
creditsoft_affiliate
affiliate
aff
ref
```

Example:

```text
https://credit-essense.com/portal/?affiliate=credit-sense
```

The bridge sends the value to CreditSoft as `affiliate_key` and keeps the raw website key in lead metadata as `affiliate_query_key`. If the key matches an affiliate configured in Growth settings, CreditSoft attaches the official affiliate record.

## API Proxy

The plugin also forwards:

```text
/api/v1/*
```

to the configured CreditSoft API base URL. If the inbound request has no `Authorization` header and the WordPress settings page has a saved API key, the plugin adds:

```text
Authorization: Bearer <saved website key>
```

That keeps simple website forms and portal tools from needing to know the API key.
