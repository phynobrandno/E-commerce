<?php
/**
 * Security Configuration File
 * This file handles encryption and security settings
 */

// IMPORTANT: Generate YOUR key using: php -r "echo bin2hex(random_bytes(32));"
// Then replace the value below with your generated 64-character hex key
define('ENCRYPTION_KEY', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2'); // CHANGE THIS!
define('ENCRYPTION_CIPHER', 'aes-256-gcm');

/**
 * Encrypt sensitive data
 */
function encryptData($data) {
    if (empty($data)) return null;
    
    $cipher = ENCRYPTION_CIPHER;
    
    // Validate encryption key
    if (strlen(ENCRYPTION_KEY) !== 64 || !ctype_xdigit(ENCRYPTION_KEY)) {
        error_log("Invalid encryption key format. Must be 64 hexadecimal characters.");
        return null;
    }
    
    $key = hex2bin(ENCRYPTION_KEY);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $tag = "";
    
    $ciphertext = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($ciphertext === false) {
        error_log("Encryption failed");
        return null;
    }
    
    // Combine IV + Tag + Ciphertext and encode
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt sensitive data
 */
function decryptData($encryptedData) {
    if (empty($encryptedData)) return null;
    
    $cipher = ENCRYPTION_CIPHER;
    
    // Validate encryption key
    if (strlen(ENCRYPTION_KEY) !== 64 || !ctype_xdigit(ENCRYPTION_KEY)) {
        error_log("Invalid encryption key format. Must be 64 hexadecimal characters.");
        return null;
    }
    
    $key = hex2bin(ENCRYPTION_KEY);
    $ivlen = openssl_cipher_iv_length($cipher);
    
    $data = base64_decode($encryptedData);
    
    if ($data === false || strlen($data) < $ivlen + 16) {
        return null;
    }
    
    $iv = substr($data, 0, $ivlen);
    $tag = substr($data, $ivlen, 16);
    $ciphertext = substr($data, $ivlen + 16);
    
    $plaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    return $plaintext !== false ? $plaintext : null;
}

/**
 * Mask card number for display (show last 4 digits only)
 */
function maskCardNumber($cardNumber) {
    if (empty($cardNumber)) return '****';
    
    // Try to decrypt first
    $decrypted = decryptData($cardNumber);
    
    // If decryption fails, assume it's plain text
    if (!$decrypted) {
        $decrypted = $cardNumber;
    }
    
    $cleaned = preg_replace('/\s+/', '', $decrypted);
    if (strlen($cleaned) < 4) return '****';
    
    return '****-****-****-' . substr($cleaned, -4);
}

/**
 * Mask account number for display
 */
function maskAccountNumber($accountNumber) {
    if (empty($accountNumber)) return '****';
    
    // Try to decrypt first
    $decrypted = decryptData($accountNumber);
    
    // If decryption fails, assume it's plain text (for backward compatibility)
    if ($decrypted === null || $decrypted === false) {
        $decrypted = $accountNumber;
    }
    
    // Clean the number
    $cleaned = preg_replace('/[^0-9]/', '', $decrypted);
    
    if (strlen($cleaned) < 4) {
        return '****';
    }
    
    // Show only last 4 digits
    return '****' . substr($cleaned, -4);
}

/**
 * Generate unique transaction ID
 */
function generateTransactionId() {
    return 'TXN-' . strtoupper(uniqid() . bin2hex(random_bytes(4)));
}

/**
 * Force HTTPS connection
 */
function forceHTTPS() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (!headers_sent()) {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect, true, 301);
            exit();
        }
    }
}

/**
 * Set secure session cookies (call BEFORE session_start())
 */
function setSecureSession() {
    // Only set if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS only in production
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
    }
}

/**
 * Add security headers
 */
function setSecurityHeaders() {
    if (!headers_sent()) {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        // Uncomment for production with HTTPS:
        // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

// Apply security settings
setSecureSession(); // This must be called BEFORE session_start()
setSecurityHeaders();

// For production, uncomment this to force HTTPS
// forceHTTPS();
?>