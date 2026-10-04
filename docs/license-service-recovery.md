# CreditSoft license API recovery

The canonical validation endpoint is `https://api.creditsoft.app/license/validate`.
The same validation-only service is also available through the `api.` hostnames for
`creatorpublishinghub.com`, `net30hosting.com`, and `matthewxmurphy.com`. These host
aliases validate CreditSoft licenses only; they do not grant unrelated product
entitlements.

The adapter accepts JSON or URL-encoded `POST` requests with `license_key`, plus
legacy `GET` requests using `key` or `license_key`. Compatibility routes
`/license/validate.json` and `/api/verify` are internally rewritten to the same
adapter without redirecting or exposing keys in a redirect location.

The existing MySQL license store and legacy validator remain authoritative. The
adapter fails closed when private configuration, database access, or the legacy
response contract is unavailable. It filters responses so customer data, license
keys, credentials, and unsupported entitlements are not returned.

## Production layout

- Source adapter: `web/api/license-validate.php`
- Request and response boundary: `web/api/license-validate-request.php`
- Legacy database validator: `web/api/license.php` in recoverable Git history and
  the deployed CreditSoft webroot
- Public routes: `/license/validate`, `/license/validate.json`, and `/api/verify`
- Private configuration remains outside the public webroot

## Rollback

Restore the backed-up `.htaccess` and Apache virtual-host configuration, remove the
validation adapter files if they did not exist before deployment, and restore the
four DNS zones from the root-private backup. Do not roll back the complete license
database merely to undo validation routing; normal successful checks update
`last_validated` and validation audit records.
