<?php
/**
 * API Input Validator
 * Validates and sanitizes API inputs
 */

class ApiValidator {
    
    /**
     * Validate required fields
     */
    public static function required($data, $fields) {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[$field] = "The {$field} field is required.";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate email format
     */
    public static function email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format.";
        }
        return null;
    }
    
    /**
     * Validate password strength
     */
    public static function password($password) {
        if (strlen($password) < 6) {
            return "Password must be at least 6 characters long.";
        }
        return null;
    }
    
    /**
     * Validate user type
     */
    public static function userType($userType) {
        $validTypes = ['student', 'provider', 'admin'];
        if (!in_array($userType, $validTypes)) {
            return "Invalid user type. Must be one of: " . implode(', ', $validTypes);
        }
        return null;
    }
    
    /**
     * Validate numeric value
     */
    public static function numeric($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return "Value must be numeric.";
        }
        
        if ($min !== null && $value < $min) {
            return "Value must be at least {$min}.";
        }
        
        if ($max !== null && $value > $max) {
            return "Value must not exceed {$max}.";
        }
        
        return null;
    }
    
    /**
     * Validate date format
     */
    public static function date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            return "Invalid date format. Expected format: {$format}";
        }
        return null;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate pagination parameters
     */
    public static function pagination($page, $limit) {
        $errors = [];
        
        if (!is_numeric($page) || $page < 1) {
            $errors['page'] = 'Page must be a positive integer.';
        }
        
        if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
            $errors['limit'] = 'Limit must be between 1 and 100.';
        }
        
        return $errors;
    }
    
    /**
     * Get JSON input data
     */
    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            ApiResponse::error('Invalid JSON input', 400);
        }
        
        return self::sanitize($data ?: []);
    }
}