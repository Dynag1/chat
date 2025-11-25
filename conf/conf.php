<?php
// conf/conf.php

// Database Configuration
define('DB_HOST', '4685j.myd.infomaniak.com:3306');
define('DB_NAME', '4685j_chat');
define('DB_USER', '4685j_chat');
define('DB_PASS', 'Po-_BCs23b%7mC');

// Encryption Key (Must be 32 bytes for AES-256)
// You should generate a random key and store it here.
// Example: bin2hex(random_bytes(16));
define('ENCRYPTION_KEY', '0123456789abcdef0123456789abcdef'); // REPLACE THIS IN PRODUCTION

// App URL
define('APP_URL', 'https://atypi.bavarder.eu/public');

// SMTP Configuration for Email Verification
// Configure these values for your SMTP server
define('SMTP_HOST', 'mail.infomaniak.com');        // SMTP server address
define('SMTP_PORT', 465);                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USER', 'contact@bavarder.eu');     // SMTP username
define('SMTP_PASS', 'j7k2Po85G-__DG');       // SMTP password
define('SMTP_FROM', 'contact@bavarder.eu');     // From email address
define('SMTP_FROM_NAME', 'Atypi Chat');       // From name
