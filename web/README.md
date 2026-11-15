# Retained CreditSoft PHP services

This directory contains existing backend/admin, account, licensing, payment and
support sources. The public CreditSoft.app website runs WordPress and Gantry 6
from `wordpress/creditsoft-helium`; it is not built or deployed from this directory.

Generated marketing HTML and the old fingerprinted static-build directory were
removed after the November 15, 2026 WordPress cutover. Do not restore them over
WordPress. Preserve service PHP, private configuration and customer files during
theme updates. Follow `wordpress/migration/README.md` for scoped service links,
deployment verification and private rollback.
