<?php
// src/Security.php

class Security {
    
    /**
     * Generate CSRF token and store in session
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            if (function_exists('random_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token HTML input field
     */
    public static function getCSRFInput() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Rate limiting implementation
     * @param string $key Unique identifier (e.g., 'login_' . $ip)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 900) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $rateLimitKey = 'rate_limit_' . $key;
        $now = time();
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'attempts' => 1,
                'first_attempt' => $now,
                'locked_until' => null
            ];
            return true;
        }
        
        $data = $_SESSION[$rateLimitKey];
        
        // Check if currently locked
        if ($data['locked_until'] && $now < $data['locked_until']) {
            return false;
        }
        
        // Reset if time window has passed
        if ($now - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$rateLimitKey] = [
                'attempts' => 1,
                'first_attempt' => $now,
                'locked_until' => null
            ];
            return true;
        }
        
        // Increment attempts
        $data['attempts']++;
        
        // Lock if max attempts exceeded
        if ($data['attempts'] > $maxAttempts) {
            $data['locked_until'] = $now + $timeWindow;
            $_SESSION[$rateLimitKey] = $data;
            return false;
        }
        
        $_SESSION[$rateLimitKey] = $data;
        return true;
    }
    
    /**
     * Get remaining lockout time in seconds
     */
    public static function getRateLimitLockoutTime($key) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $rateLimitKey = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$rateLimitKey])) {
            return 0;
        }
        
        $data = $_SESSION[$rateLimitKey];
        
        if (!$data['locked_until']) {
            return 0;
        }
        
        $remaining = $data['locked_until'] - time();
        return max(0, $remaining);
    }
    
    /**
     * Sanitize input to prevent XSS
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     * Minimum 8 characters, at least one letter and one number
     */
    public static function validatePassword($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }
        
        if (!preg_match('/[A-Za-z]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre.'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins un chiffre.'];
        }
        
        return ['valid' => true, 'message' => 'Mot de passe valide.'];
    }
    
    /**
     * Regenerate session ID to prevent session fixation
     */
    public static function regenerateSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_regenerate_id(true);
    }
    
    /**
     * Set secure session configuration
     */
    public static function configureSecureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return; // Session already started
        }
        
        if (headers_sent()) {
            return; // Cannot start session if headers sent
        }
        
        // Basic secure settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        if ($secure) {
            ini_set('session.cookie_secure', '1');
        }
        
        // Set custom session save path to avoid external garbage collection
        $sessionPath = __DIR__ . '/../var/sessions';
        if (!file_exists($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }
        ini_set('session.save_path', $sessionPath);

        // Attempt to set cookie params safely
        $params = session_get_cookie_params();
        $lifetime = 60 * 60 * 24 * 30; // 30 days
        
        // Ensure server-side session data persists as long as the cookie
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.cookie_lifetime', (string)$lifetime);
        
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => $params['domain'],
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax' // Lax is better for PWA/Mobile navigation
            ]);
        } else {
            session_set_cookie_params(
                $lifetime,
                '/',
                $params['domain'],
                $secure,
                true
            );
        }
        
        session_start();
        
        // Session timeout check
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 2592000)) { // 30 days
            session_unset();
            session_destroy();
            session_start();
        }
        
        $_SESSION['last_activity'] = time();
        
        // Disable User-Agent check for mobile stability (UA can change slightly)
        /*
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_unset();
            session_destroy();
            session_start();
        }
        */
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://r2cdn.perplexity.ai; img-src 'self' data:; connect-src 'self';");
        
        // HSTS (only if using HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '0.0.0.0';
    }
}
