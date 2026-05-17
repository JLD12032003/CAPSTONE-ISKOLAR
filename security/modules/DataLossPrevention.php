<?php
/**
 * Data Loss Prevention (DLP) Module
 * Handles session timeout and data classification management
 */

class DataLossPrevention {
    private $conn;
    private $sessionTimeout = 3600; // 1 hour default
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Feature 1: Session Timeout Management
     * Automatic session expiration and cleanup
     */
    public function initializeSession($userId, $ipAddress, $userAgent, $customTimeout = null) {
        try {
            $timeout = $customTimeout ?? $this->sessionTimeout;
            $sessionId = $this->generateSecureSessionId();
            $expiresAt = date('Y-m-d H:i:s', time() + $timeout);
            
            // Clean up old sessions for this user
            $this->cleanupUserSessions($userId);
            
            // Create new session record
            $stmt = $this->conn->prepare("
                INSERT INTO user_sessions (
                    id, user_id, ip_address, user_agent, expires_at, is_active
                ) VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $result = $stmt->execute([$sessionId, $userId, $ipAddress, $userAgent, $expiresAt]);
            
            if ($result) {
                // Set PHP session variables
                $_SESSION['secure_session_id'] = $sessionId;
                $_SESSION['session_expires_at'] = time() + $timeout;
                $_SESSION['last_activity'] = time();
                
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'expires_at' => $expiresAt,
                    'timeout_seconds' => $timeout
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create session'];
        } catch (Exception $e) {
            error_log("Session initialization failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Session creation error'];
        }
    }
    
    /**
     * Check if session is valid and not expired
     */
    public function validateSession($sessionId = null) {
        try {
            $sessionId = $sessionId ?? ($_SESSION['secure_session_id'] ?? null);
            
            if (!$sessionId) {
                return ['valid' => false, 'reason' => 'No session ID'];
            }
            
            $stmt = $this->conn->prepare("
                SELECT * FROM user_sessions 
                WHERE id = ? AND is_active = 1 AND expires_at > NOW()
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['valid' => false, 'reason' => 'Session expired or invalid'];
            }
            
            // Check PHP session timeout
            if (isset($_SESSION['session_expires_at']) && time() > $_SESSION['session_expires_at']) {
                $this->terminateSession($sessionId);
                return ['valid' => false, 'reason' => 'Session timeout'];
            }
            
            // Update last activity
            $this->updateSessionActivity($sessionId);
            $_SESSION['last_activity'] = time();
            
            return [
                'valid' => true,
                'session' => $session,
                'expires_in' => strtotime($session['expires_at']) - time()
            ];
        } catch (Exception $e) {
            error_log("Session validation failed: " . $e->getMessage());
            return ['valid' => false, 'reason' => 'Validation error'];
        }
    }
    
    /**
     * Extend session timeout (for active users)
     */
    public function extendSession($sessionId, $additionalTime = null) {
        try {
            $additionalTime = $additionalTime ?? $this->sessionTimeout;
            $newExpiresAt = date('Y-m-d H:i:s', time() + $additionalTime);
            
            $stmt = $this->conn->prepare("
                UPDATE user_sessions 
                SET expires_at = ?, last_activity = NOW()
                WHERE id = ? AND is_active = 1
            ");
            
            $result = $stmt->execute([$newExpiresAt, $sessionId]);
            
            if ($result) {
                $_SESSION['session_expires_at'] = time() + $additionalTime;
                return ['success' => true, 'new_expires_at' => $newExpiresAt];
            }
            
            return ['success' => false, 'message' => 'Failed to extend session'];
        } catch (Exception $e) {
            error_log("Session extension failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Extension error'];
        }
    }
    
    /**
     * Terminate session
     */
    public function terminateSession($sessionId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_sessions 
                SET is_active = 0 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$sessionId]);
            
            // Clear PHP session
            if (isset($_SESSION['secure_session_id']) && $_SESSION['secure_session_id'] === $sessionId) {
                session_unset();
                session_destroy();
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Session termination failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Feature 2: Data Classification System
     * Manage data sensitivity levels and access controls
     */
    public function classifyData($tableName, $columnName, $classification, $encryptionRequired = false, $accessRoles = []) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO data_classification (
                    table_name, column_name, classification, 
                    encryption_required, access_roles
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    classification = VALUES(classification),
                    encryption_required = VALUES(encryption_required),
                    access_roles = VALUES(access_roles),
                    updated_at = NOW()
            ");
            
            return $stmt->execute([
                $tableName,
                $columnName,
                $classification,
                $encryptionRequired ? 1 : 0,
                json_encode($accessRoles)
            ]);
        } catch (Exception $e) {
            error_log("Data classification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get data classification rules
     */
    public function getDataClassification($tableName = null, $classification = null) {
        try {
            $whereConditions = [];
            $params = [];
            
            if ($tableName) {
                $whereConditions[] = "table_name = ?";
                $params[] = $tableName;
            }
            
            if ($classification) {
                $whereConditions[] = "classification = ?";
                $params[] = $classification;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $stmt = $this->conn->prepare("
                SELECT * FROM data_classification 
                {$whereClause}
                ORDER BY table_name, column_name
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get data classification: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check data access permissions
     */
    public function checkDataAccess($userRole, $tableName, $columnName) {
        try {
            $stmt = $this->conn->prepare("
                SELECT classification, access_roles
                FROM data_classification
                WHERE table_name = ? AND column_name = ?
            ");
            $stmt->execute([$tableName, $columnName]);
            $classification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$classification) {
                // If no classification exists, default to public access
                return [
                    'allowed' => true,
                    'classification' => 'PUBLIC',
                    'reason' => 'No classification found - default public access'
                ];
            }
            
            $accessRoles = json_decode($classification['access_roles'], true) ?? [];
            $allowed = in_array($userRole, $accessRoles);
            
            return [
                'allowed' => $allowed,
                'classification' => $classification['classification'],
                'reason' => $allowed ? 'Access granted' : 'Insufficient role permissions'
            ];
        } catch (Exception $e) {
            error_log("Data access check failed: " . $e->getMessage());
            return [
                'allowed' => false,
                'classification' => 'UNKNOWN',
                'reason' => 'Access check error'
            ];
        }
    }
    
    /**
     * Filter sensitive data based on user role
     */
    public function filterSensitiveData($data, $tableName, $userRole) {
        $filteredData = [];
        
        foreach ($data as $column => $value) {
            $accessCheck = $this->checkDataAccess($userRole, $tableName, $column);
            
            if ($accessCheck['allowed']) {
                $filteredData[$column] = $value;
            } else {
                // Replace with classification indicator
                switch ($accessCheck['classification']) {
                    case 'SENSITIVE':
                        $filteredData[$column] = '[SENSITIVE DATA - ACCESS DENIED]';
                        break;
                    case 'CONFIDENTIAL':
                        $filteredData[$column] = '[CONFIDENTIAL DATA - ACCESS DENIED]';
                        break;
                    default:
                        $filteredData[$column] = '[RESTRICTED ACCESS]';
                }
            }
        }
        
        return $filteredData;
    }
    
    /**
     * Log data access attempts
     */
    public function logDataAccess($userId, $tableName, $columnName, $accessGranted, $classification) {
        try {
            $monitoring = new SecurityMonitoring();
            
            $riskLevel = 'LOW';
            if ($classification === 'CONFIDENTIAL') {
                $riskLevel = 'HIGH';
            } elseif ($classification === 'SENSITIVE') {
                $riskLevel = 'MEDIUM';
            }
            
            return $monitoring->logSecurityEvent(
                $userId,
                $accessGranted ? 'DATA_ACCESS_GRANTED' : 'DATA_ACCESS_DENIED',
                'data_access',
                null,
                json_encode(['table' => $tableName, 'column' => $columnName]),
                json_encode(['classification' => $classification, 'granted' => $accessGranted]),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $riskLevel
            );
        } catch (Exception $e) {
            error_log("Failed to log data access: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_sessions 
                SET is_active = 0 
                WHERE expires_at <= NOW() AND is_active = 1
            ");
            
            $result = $stmt->execute();
            $cleanedCount = $stmt->rowCount();
            
            // Also clean up very old inactive sessions (older than 30 days)
            $stmt = $this->conn->prepare("
                DELETE FROM user_sessions 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            
            return [
                'success' => $result,
                'expired_sessions_cleaned' => $cleanedCount,
                'old_sessions_deleted' => $stmt->rowCount()
            ];
        } catch (Exception $e) {
            error_log("Session cleanup failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Cleanup error'];
        }
    }
    
    /**
     * Get session statistics
     */
    public function getSessionStatistics() {
        try {
            $stats = [];
            
            // Active sessions count
            $stmt = $this->conn->query("
                SELECT COUNT(*) as count 
                FROM user_sessions 
                WHERE is_active = 1 AND expires_at > NOW()
            ");
            $stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Expired sessions count
            $stmt = $this->conn->query("
                SELECT COUNT(*) as count 
                FROM user_sessions 
                WHERE expires_at <= NOW() AND is_active = 1
            ");
            $stats['expired_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Sessions by user type
            $stmt = $this->conn->query("
                SELECT u.user_type, COUNT(us.id) as session_count
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.is_active = 1 AND us.expires_at > NOW()
                GROUP BY u.user_type
            ");
            $stats['sessions_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Average session duration
            $stmt = $this->conn->query("
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, last_activity)) as avg_duration_minutes
                FROM user_sessions
                WHERE is_active = 0 AND last_activity IS NOT NULL
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $avgDuration = $stmt->fetch(PDO::FETCH_ASSOC)['avg_duration_minutes'];
            $stats['average_session_duration_minutes'] = round($avgDuration, 2);
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get session statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate secure session ID
     */
    private function generateSecureSessionId() {
        return hash('sha256', uniqid() . random_bytes(32) . microtime(true));
    }
    
    /**
     * Clean up old sessions for a user (keep only the most recent 3)
     */
    private function cleanupUserSessions($userId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_sessions 
                SET is_active = 0 
                WHERE user_id = ? 
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM user_sessions 
                        WHERE user_id = ? AND is_active = 1
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ) as recent_sessions
                )
            ");
            
            return $stmt->execute([$userId, $userId]);
        } catch (Exception $e) {
            error_log("User session cleanup failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update session activity timestamp
     */
    private function updateSessionActivity($sessionId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Session activity update failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Session Security Middleware
 * Integrates session management with existing authentication
 */
class SessionSecurityMiddleware {
    private $dlp;
    
    public function __construct() {
        $this->dlp = new DataLossPrevention();
    }
    
    /**
     * Initialize secure session (call after successful login)
     */
    public function initializeSecureSession($userId, $userRole) {
        // Set different timeout based on user role
        $timeout = $this->getTimeoutForRole($userRole);
        
        $result = $this->dlp->initializeSession(
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $timeout
        );
        
        if ($result['success']) {
            // Set additional security headers
            $this->setSecurityHeaders();
        }
        
        return $result;
    }
    
    /**
     * Validate session on each request
     */
    public function validateCurrentSession() {
        $validation = $this->dlp->validateSession();
        
        if (!$validation['valid']) {
            // Clear PHP session and redirect to login
            session_unset();
            session_destroy();
            
            return [
                'valid' => false,
                'redirect_to_login' => true,
                'reason' => $validation['reason']
            ];
        }
        
        // Check if session needs extension (less than 10 minutes remaining)
        if ($validation['expires_in'] < 600) {
            $this->dlp->extendSession($_SESSION['secure_session_id']);
        }
        
        return $validation;
    }
    
    /**
     * Get timeout duration based on user role
     */
    private function getTimeoutForRole($userRole) {
        $timeouts = [
            'student' => 3600,    // 1 hour
            'provider' => 7200,   // 2 hours
            'admin' => 1800       // 30 minutes (more secure for admins)
        ];
        
        return $timeouts[$userRole] ?? 3600;
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders() {
        if (!headers_sent()) {
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
?>