<?php
/**
 * Authentication Security Module
 * Enhances existing authentication without modifying email auth flow
 */

class AuthenticationSecurity {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Feature 1: Login Rate Limiting (Enhancement to existing auth)
     * Prevents brute force attacks without breaking existing login
     */
    public function checkRateLimit($email, $ipAddress) {
        try {
            $stmt = $this->conn->prepare("CALL sp_check_login_rate_limit(?, ?, @is_blocked, @attempts_count)");
            $stmt->execute([$email, $ipAddress]);
            
            $result = $this->conn->query("SELECT @is_blocked as is_blocked, @attempts_count as attempts_count")->fetch(PDO::FETCH_ASSOC);
            
            return [
                'is_blocked' => (bool)$result['is_blocked'],
                'attempts_count' => (int)$result['attempts_count'],
                'remaining_attempts' => max(0, 5 - (int)$result['attempts_count'])
            ];
        } catch (Exception $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            return ['is_blocked' => false, 'attempts_count' => 0, 'remaining_attempts' => 5];
        }
    }
    
    /**
     * Feature 2: Password Strength Validation (Enhancement)
     * Validates password strength without changing existing bcrypt hashing
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        // Minimum length (existing requirement: 6 chars)
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Must contain uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Must contain lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Must contain number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Must contain special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'strength_score' => $this->calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Log login attempts (integrates with existing auth flow)
     */
    public function logLoginAttempt($email, $ipAddress, $userAgent, $attemptType, $userId = null, $failureReason = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO login_attempts (email, ip_address, user_agent, attempt_type, user_id, failure_reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$email, $ipAddress, $userAgent, $attemptType, $userId, $failureReason]);
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate password strength score (0-100)
     */
    private function calculatePasswordStrength($password) {
        $score = 0;
        
        // Length bonus
        $score += min(25, strlen($password) * 2);
        
        // Character variety bonus
        if (preg_match('/[a-z]/', $password)) $score += 15;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 20;
        
        // Complexity bonus
        if (preg_match('/[A-Z].*[a-z]|[a-z].*[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9].*[^A-Za-z0-9]|[^A-Za-z0-9].*[0-9]/', $password)) $score += 5;
        
        return min(100, $score);
    }
    
    /**
     * Get login attempt statistics for monitoring
     */
    public function getLoginStats($timeframe = '24 HOUR') {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    attempt_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT email) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM login_attempts 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL {$timeframe})
                GROUP BY attempt_type
            ");
            $stmt->execute();
            
            $stats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['attempt_type']] = [
                    'count' => (int)$row['count'],
                    'unique_users' => (int)$row['unique_users'],
                    'unique_ips' => (int)$row['unique_ips']
                ];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get login stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if admin password needs rotation (6 months)
     */
    public function checkAdminPasswordExpiry($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    u.user_type,
                    COALESCE(aph.expires_at, u.created_at) as password_date,
                    DATEDIFF(NOW(), COALESCE(aph.expires_at, u.created_at)) as days_since_change
                FROM users u
                LEFT JOIN admin_password_history aph ON u.id = aph.user_id AND aph.is_active = 1
                WHERE u.id = ? AND u.user_type = 'admin'
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return ['needs_rotation' => false, 'days_remaining' => null];
            }
            
            $daysSinceChange = (int)$result['days_since_change'];
            $daysRemaining = 180 - $daysSinceChange; // 6 months = 180 days
            
            return [
                'needs_rotation' => $daysRemaining <= 0,
                'days_remaining' => max(0, $daysRemaining),
                'warning' => $daysRemaining <= 30 && $daysRemaining > 0
            ];
        } catch (Exception $e) {
            error_log("Failed to check admin password expiry: " . $e->getMessage());
            return ['needs_rotation' => false, 'days_remaining' => null];
        }
    }
}

/**
 * Enhanced Authentication Middleware (wraps existing auth)
 * Adds security checks without breaking existing functionality
 */
class EnhancedAuthMiddleware {
    private $authSecurity;
    
    public function __construct() {
        $this->authSecurity = new AuthenticationSecurity();
    }
    
    /**
     * Pre-login security checks (call before existing login logic)
     */
    public function preLoginCheck($email, $ipAddress, $userAgent) {
        // Check rate limiting
        $rateLimit = $this->authSecurity->checkRateLimit($email, $ipAddress);
        
        if ($rateLimit['is_blocked']) {
            $this->authSecurity->logLoginAttempt($email, $ipAddress, $userAgent, 'blocked', null, 'Rate limit exceeded');
            
            return [
                'allowed' => false,
                'message' => "Too many failed login attempts. Please try again in 15 minutes.",
                'attempts_remaining' => 0
            ];
        }
        
        return [
            'allowed' => true,
            'attempts_remaining' => $rateLimit['remaining_attempts']
        ];
    }
    
    /**
     * Post-login logging (call after existing login logic)
     */
    public function postLoginLog($email, $ipAddress, $userAgent, $success, $userId = null, $failureReason = null) {
        $attemptType = $success ? 'success' : 'failure';
        $this->authSecurity->logLoginAttempt($email, $ipAddress, $userAgent, $attemptType, $userId, $failureReason);
    }
    
    /**
     * Password validation for registration (enhances existing validation)
     */
    public function validateRegistrationPassword($password) {
        return $this->authSecurity->validatePasswordStrength($password);
    }
}
?>