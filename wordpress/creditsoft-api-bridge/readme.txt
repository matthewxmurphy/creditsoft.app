=== CreditSoft API Bridge ===
Contributors: creditsoft
Tags: credit repair, crm, lead form, affiliate, api bridge
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free CreditSoft website bridge for lead intake, affiliate tracking, and API proxying. Requires a valid CreditSoft API key.

== Description ==

CreditSoft API Bridge connects a WordPress website to the CreditSoft intranet without exposing the private API key in browser JavaScript.

The add-on can:

* Render a lead form with `[creditsoft_lead_form]`.
* Track affiliate/referral query strings with `affiliate_key`, `creditsoft_affiliate`, `affiliate`, `aff`, or `ref`.
* Store the affiliate/referral value for 30 days.
* Send leads into CreditSoft with contact fields, goals, page metadata, UTM values, and external reference tracking.
* Forward `/api/v1/*` from WordPress to the configured CreditSoft API target.

The plugin is free, but it only works when the site has a valid CreditSoft API key generated inside the intranet.

== Installation ==

1. Download `creditsoft-api-bridge.zip` from the CreditSoft API docs download button.
2. In WordPress, open Plugins > Add New > Upload Plugin.
3. Upload `creditsoft-api-bridge.zip`.
4. Activate "CreditSoft API Bridge" from the WordPress plugins screen.
5. Open Settings > CreditSoft API Bridge.
6. Set the CreditSoft API base URL. The default is `https://www.creditsoft.app/api/v1`.
7. Paste the website API key generated inside CreditSoft Connectivity.
8. Add `[creditsoft_lead_form]` to any intake or portal access page.

Manual install:

1. Unzip `creditsoft-api-bridge.zip`.
2. Upload the `creditsoft-api-bridge` folder to `/wp-content/plugins/`.
3. Activate "CreditSoft API Bridge" from the WordPress plugins screen.
4. Open Settings > CreditSoft API Bridge.
5. Set the CreditSoft API base URL. The default is `https://www.creditsoft.app/api/v1`.
6. Paste the website API key generated inside CreditSoft Connectivity.
7. Add `[creditsoft_lead_form]` to any intake or portal access page.

== Shortcodes ==

Basic lead form:

`[creditsoft_lead_form]`

Custom title/button:

`[creditsoft_lead_form title="Request portal access" button="Request access"]`

Hard-code a default affiliate for one page:

`[creditsoft_lead_form affiliate_key="credit-sense"]`

Show the optional score field:

`[creditsoft_lead_form show_score="true"]`

== Affiliate Tracking ==

The plugin watches for these query parameters:

* `affiliate_key`
* `creditsoft_affiliate`
* `affiliate`
* `aff`
* `ref`

Example:

`https://example.com/portal/?affiliate=credit-sense`

That value is included in the CreditSoft lead payload as `affiliate_key` and stored in metadata as `affiliate_query_key`. If the key matches an affiliate configured in CreditSoft Growth settings, CreditSoft attaches the official affiliate record.

== Changelog ==

= 0.1.0 =
* Initial CreditSoft API bridge with lead form shortcode, affiliate tracking, server-side API key storage, and `/api/v1/*` proxying.
