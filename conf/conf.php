<?php
// conf/conf.php

// Database Configuration
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Encryption Key (Must be 32 bytes for AES-256)
// You should generate a random key and store it here.
// Example: bin2hex(random_bytes(16));
define('ENCRYPTION_KEY', ''); // REPLACE THIS IN PRODUCTION

// App URL
define('APP_URL', '');

// SMTP Configuration for Email Verification
// Configure these values for your SMTP server
define('SMTP_HOST', '');        // SMTP server address
define('SMTP_PORT', );                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USER', '');     // SMTP username
define('SMTP_PASS', '-');       // SMTP password
define('SMTP_FROM', '');     // From email address
define('SMTP_FROM_NAME', '');       // From name
