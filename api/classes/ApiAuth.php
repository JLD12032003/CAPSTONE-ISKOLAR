<?php
/**
 * API Authentication Handler
 * JWT-based authentication for API endpoints
 */

class ApiAuth {
    private static $secretKey = 'ISKOLar_API_Secret_Key_2026'; // Change in production
    private static $algorithm = 'HS256';
    
    /**
     * Generate JWT token
     */
    public static function generateToken($userId, $userType, $email) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        
        $payload = json_encode([
            'user_id' => $userId,
            'user_type' => $userType,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secretKey, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify JWT token
     */
    public static function verifyToken($token) {
        if (!$token) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, self::$secretKey, true);
        $expectedBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if ($signature !== $expectedBase64Signature) {
            return false;
        }
        
        // Decode payload
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        // Check expiration
        if ($payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    /**
     * Get current user from token
     */
    public static function getCurrentUser() {
        $token = self::getBearerToken();
        
        if (!$token) {
            return null;
        }
        
        return self::verifyToken($token);
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth($allowedRoles = []) {
        $user = self::getCurrentUser();
        
        if (!$user) {
            ApiResponse::unauthorized('Authentication required');
        }
        
        if (!empty($allowedRoles) && !in_array($user['user_type'], $allowedRoles)) {
            ApiResponse::forbidden('Insufficient permissions');
        }
        
        return $user;
    }
    
    /**
     * Get bearer token from header
     */
    private static function getBearerToken() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Generate API key for external integrations
     */
    public static function generateApiKey($userId, $purpose = 'general') {
        return hash('sha256', $userId . $purpose . self::$secretKey . time());
    }
}