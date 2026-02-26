<?php
/**
 * CreditSoft Configuration
 * Located outside web root for security
 */

// OpenAI API Key for chatbot
define('OPENAI_API_KEY', 'sk-bAkutqQmZ3n7CJlpNS5quRcA7zqsGs9fijblPtZPwt0iA9hPUZiySxbNF9kgvvuf');

// Database credentials (for leads)
define('DB_HOST', 'localhost');
define('DB_NAME', 'creditso1_db');
define('DB_USER', 'creditso1_user');
define('DB_PASS', 'ACH0o-1S@Zb3R^$C');

// Turnstile
define('TURNSTILE_SITE_KEY', '0x4AAAAAACi2Qk6bhhRsXu2g');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAACi2QpE3EzYkp2FkHM5R6bes4zI');

// Site URLs
define('SITE_URL', 'https://www.creditsoft.app');
define('ADMIN_URL', 'https://app.creditsoft.app');

// Authorize.net Payment Settings
define('AUTHNET_LOGIN', '');
define('AUTHNET_TRANSACTION_KEY', '');
define('AUTHNET_MD5_HASH', '');
define('AUTHNET_TEST_MODE', true); // Set to false for production
