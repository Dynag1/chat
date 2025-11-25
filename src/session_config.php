<?php
// src/session_config.php
// Include this file at the beginning of every page that needs sessions

require_once __DIR__ . '/Security.php';

// Configure and start secure session
Security::configureSecureSession();

// Set security headers
Security::setSecurityHeaders();
