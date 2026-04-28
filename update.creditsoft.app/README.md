# updates.creditsoft.app

Portable package for:

- office checkout via Zelle QR
- payment matching with payer email/phone
- office renewal lane
- staging before the DNS cutover for `updates.creditsoft.app`

Intended remote staging paths:

- preview under main site: `/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html/updates.creditsoft.app`
- future subdomain root: `/home/mmurphy/updates.creditsoft.app` or a vhost-mapped directory once server routing is ready

This repo still keeps the package in a local `update.creditsoft.app` folder for compatibility while the public host moves to `updates.creditsoft.app`.
