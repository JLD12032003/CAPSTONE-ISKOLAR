<?php
/**
 * Authentication Middleware for API
 */

class AuthMiddleware {
    
    public function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            return ['success' => false, 'message' => 'Authorization header missing'];
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return ['success' => false, 'message' => 'Invalid authorization header format'];
        }
        
        $token = $matches[1];
        $payload = ApiConfig::verifyJWT($token);
        
        if (!$payload) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // Get user from database
        $conn = ApiConfig::getDatabase();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_verified = 1");
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found or not verified'];
        }
        
        return ['success' => true, 'user' => $user, 'payload' => $payload];
    }
    
    public function requireRole($allowedRoles) {
        $authResult = $this->authenticate();
        
        if (!$authResult['success']) {
            return $authResult;
        }
        
        $userRole = $authResult['user']['user_type'];
        
        if (!in_array($userRole, $allowedRoles)) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }
        
        return $authResult;
    }
}