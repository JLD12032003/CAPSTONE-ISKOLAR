<?php
/**
 * API Configuration
 */

class ApiConfig {
    // API Version
    const VERSION = '1.0';
    
    // Debug mode (set to false in production)
    const DEBUG_MODE = true;
    
    // JWT Configuration
    const JWT_SECRET = 'your-secret-key-change-in-production';
    const JWT_EXPIRY = 3600; // 1 hour
    
    // Rate limiting
    const RATE_LIMIT_REQUESTS = 100;
    const RATE_LIMIT_WINDOW = 3600; // 1 hour
    
    // Pagination
    const DEFAULT_PAGE_SIZE = 20;
    const MAX_PAGE_SIZE = 100;
    
    // File upload limits
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_FILE_TYPES = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    
    // API Response codes
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    
    /**
     * Get database connection
     */
    public static function getDatabase() {
        return (new Database())->connect();
    }
    
    /**
     * Generate JWT token
     */
    public static function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + self::JWT_EXPIRY;
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify JWT token
     */
    public static function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$header, $payload, $signature] = $parts;
        
        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::JWT_SECRET, true);
        $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        
        if (!hash_equals($signature, $validSignature)) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if ($payload['exp'] < time()) {
            return false; // Token expired
        }
        
        return $payload;
    }
    
    /**
     * Standard API response format
     */
    public static function response($data = null, $message = null, $status = 200, $errors = null) {
        http_response_code($status);
        
        $response = [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'timestamp' => date('c'),
            'version' => self::VERSION
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $required) {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get pagination parameters
     */
    public static function getPagination() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(self::MAX_PAGE_SIZE, max(1, intval($_GET['limit'] ?? self::DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        
        return compact('page', 'limit', 'offset');
    }
}