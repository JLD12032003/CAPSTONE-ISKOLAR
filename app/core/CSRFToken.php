<?php

/**
 * CSRF Protection Utility
 */

class CSRFToken {
    
    const TOKEN_NAME = '_csrf_token';
    const TOKEN_LENGTH = 32;

    /**
     * Generate a new CSRF token
     */
    public static function generate() {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Get the current CSRF token
     */
    public static function getToken() {
        return $_SESSION[self::TOKEN_NAME] ?? null;
    }

    /**
     * Verify a CSRF token
     */
    public static function verify($token) {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[self::TOKEN_NAME], $token ?? '');
    }

    /**
     * Get HTML input field for forms
     */
    public static function getInput() {
        $token = self::generate();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
}
