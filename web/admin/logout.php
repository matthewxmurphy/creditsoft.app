<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

cs_site_admin_logout();
cs_site_admin_flash('success', 'You have been signed out of the CreditSoft site admin lane.');
cs_site_admin_redirect(cs_site_admin_url('/login'));
