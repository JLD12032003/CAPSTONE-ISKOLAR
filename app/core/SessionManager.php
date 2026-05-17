<?php
/**
 * SessionManager Class
 * Handles session timeout and security features
 * Maintains all existing functionality while adding timeout protection
 */

class SessionManager {
    private $conn;
    private $logger;
    
    // Session timeout settings (in seconds)
    private $timeouts = [
        'student' => 3600,    // 1 hour
        'provider' => 7200,   // 2 hours  
        'admin' => 1800       // 30 minutes (more secure for admins)
    ];
    
    // Warning time before timeout (in seconds)
    private $warningTime = 300; // 5 minutes before timeout
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/ActivityLogger.php';
        
        $this->conn = (new Database())->connect();
        $this->logger = new ActivityLogger();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Initialize session with timeout tracking
     * Called after successful login
     */
    public function initializeSession($userId, $userType, $email) {
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_type'] = $userType;
        $_SESSION['email'] = $email;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['timeout_duration'] = $this->timeouts[$userType];
        $_SESSION['session_token'] = $this->generateSessionToken();
        
        // Store session in database for tracking
        $this->storeSessionInDatabase($userId, $userType);
        
        // Log session initialization
        $this->logger->logSystemActivity(
            $userId,
            $userType,
            'SESSION_INIT',
            'user_session',
            null,
            "Session initialized with {$this->timeouts[$userType]} second timeout",
            [
                'timeout_duration' => $this->timeouts[$userType],
                'session_token' => $_SESSION['session_token']
            ]
        );
        
        return $_SESSION['session_token'];
    }
    
    /**
     * Check if session is valid and not timed out
     * Returns session status information
     */
    public function checkSession() {
        // Check if session exists
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return [
                'valid' => false,
                'reason' => 'no_session',
                'message' => 'No active session found'
            ];
        }
        
        $currentTime = time();
        $lastActivity = $_SESSION['last_activity'];
        $timeoutDuration = $_SESSION['timeout_duration'] ?? $this->timeouts['student'];
        $timeSinceActivity = $currentTime - $lastActivity;
        
        // Check if session has timed out
        if ($timeSinceActivity > $timeoutDuration) {
            $this->logSessionTimeout();
            $this->destroySession();
            
            return [
                'valid' => false,
                'reason' => 'timeout',
                'message' => 'Session has expired due to inactivity',
                'inactive_time' => $timeSinceActivity
            ];
        }
        
        // Check if session is close to timeout (warning)
        $timeRemaining = $timeoutDuration - $timeSinceActivity;
        $showWarning = $timeRemaining <= $this->warningTime;
        
        // Update last activity time
        $_SESSION['last_activity'] = $currentTime;
        $this->updateSessionActivity();
        
        return [
            'valid' => true,
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['user_type'],
            'email' => $_SESSION['email'],
            'time_remaining' => $timeRemaining,
            'show_warning' => $showWarning,
            'warning_threshold' => $this->warningTime,
            'last_activity' => $lastActivity,
            'session_duration' => $currentTime - $_SESSION['login_time']
        ];
    }
    
    /**
     * Extend session timeout (for active users)
     */
    public function extendSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        $this->updateSessionActivity();
        
        // Log session extension
        $this->logger->logSystemActivity(
            $_SESSION['user_id'],
            $_SESSION['user_type'],
            'SESSION_EXTEND',
            'user_session',
            null,
            'Session timeout extended due to user activity'
        );
        
        return true;
    }
    
    /**
     * Manually refresh session (user clicked "Stay Logged In")
     */
    public function refreshSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $currentTime = time();
        $_SESSION['last_activity'] = $currentTime;
        $this->updateSessionActivity();
        
        // Log manual session refresh
        $this->logger->logSystemActivity(
            $_SESSION['user_id'],
            $_SESSION['user_type'],
            'SESSION_REFRESH',
            'user_session',
            null,
            'Session manually refreshed by user'
        );
        
        return [
            'success' => true,
            'new_timeout' => $currentTime + $_SESSION['timeout_duration'],
            'time_remaining' => $_SESSION['timeout_duration']
        ];
    }
    
    /**
     * Get session timeout configuration for frontend
     */
    public function getTimeoutConfig() {
        if (!isset($_SESSION['user_type'])) {
            return null;
        }
        
        return [
            'timeout_duration' => $_SESSION['timeout_duration'],
            'warning_time' => $this->warningTime,
            'check_interval' => 60, // Check every minute
            'user_type' => $_SESSION['user_type']
        ];
    }
    
    /**
     * Destroy session and cleanup
     */
    public function destroySession($reason = 'logout') {
        if (isset($_SESSION['user_id'])) {
            // Log session destruction
            $this->logger->logSystemActivity(
                $_SESSION['user_id'],
                $_SESSION['user_type'] ?? 'unknown',
                'SESSION_DESTROY',
                'user_session',
                null,
                "Session destroyed: $reason",
                [
                    'reason' => $reason,
                    'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0
                ]
            );
            
            // Remove from database
            $this->removeSessionFromDatabase();
        }
        
        // Clear all session data
        session_unset();
        session_destroy();
        
        // Start new session for any subsequent operations
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Check for concurrent sessions (security feature)
     */
    public function checkConcurrentSessions($userId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as session_count 
            FROM active_sessions 
            WHERE user_id = ? 
            AND expires_at > NOW()
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['session_count'] ?? 0;
    }
    
    /**
     * Generate secure session token
     */
    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Store session information in database
     */
    private function storeSessionInDatabase($userId, $userType) {
        try {
            // Create active_sessions table if it doesn't exist
            $this->createActiveSessionsTable();
            
            // Remove any existing sessions for this user
            $stmt = $this->conn->prepare("DELETE FROM active_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Insert new session
            $stmt = $this->conn->prepare("
                INSERT INTO active_sessions (
                    user_id, user_type, session_id, session_token, 
                    ip_address, user_agent, created_at, last_activity, expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
            ");
            
            $stmt->execute([
                $userId,
                $userType,
                session_id(),
                $_SESSION['session_token'],
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $this->timeouts[$userType]
            ]);
            
        } catch (Exception $e) {
            error_log("Session storage error: " . $e->getMessage());
        }
    }
    
    /**
     * Update session activity in database
     */
    private function updateSessionActivity() {
        try {
            if (!isset($_SESSION['session_token'])) {
                return;
            }
            
            $stmt = $this->conn->prepare("
                UPDATE active_sessions 
                SET last_activity = NOW(), 
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                WHERE session_token = ?
            ");
            
            $stmt->execute([
                $_SESSION['timeout_duration'],
                $_SESSION['session_token']
            ]);
            
        } catch (Exception $e) {
            error_log("Session update error: " . $e->getMessage());
        }
    }
    
    /**
     * Remove session from database
     */
    private function removeSessionFromDatabase() {
        try {
            if (!isset($_SESSION['session_token'])) {
                return;
            }
            
            $stmt = $this->conn->prepare("DELETE FROM active_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
            
        } catch (Exception $e) {
            error_log("Session removal error: " . $e->getMessage());
        }
    }
    
    /**
     * Log session timeout event
     */
    private function logSessionTimeout() {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        $inactiveTime = time() - $_SESSION['last_activity'];
        
        $this->logger->logSystemActivity(
            $_SESSION['user_id'],
            $_SESSION['user_type'],
            'SESSION_TIMEOUT',
            'user_session',
            null,
            "Session timed out after {$inactiveTime} seconds of inactivity",
            [
                'inactive_time' => $inactiveTime,
                'timeout_duration' => $_SESSION['timeout_duration'],
                'user_type' => $_SESSION['user_type']
            ]
        );
        
        // Create security event for admin timeouts
        if ($_SESSION['user_type'] === 'admin') {
            $this->logger->createSecurityEvent(
                'SESSION_TIMEOUT',
                'MEDIUM',
                $_SESSION['user_id'],
                "Admin session timed out after {$inactiveTime} seconds",
                ['inactive_time' => $inactiveTime]
            );
        }
    }
    
    /**
     * Create active_sessions table if it doesn't exist
     */
    private function createActiveSessionsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS active_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_type ENUM('student', 'provider', 'admin') NOT NULL,
                session_id VARCHAR(255) NOT NULL,
                session_token VARCHAR(255) NOT NULL UNIQUE,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                
                INDEX idx_user_id (user_id),
                INDEX idx_session_token (session_token),
                INDEX idx_expires_at (expires_at),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        
        try {
            $this->conn->exec($sql);
        } catch (Exception $e) {
            error_log("Active sessions table creation error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up expired sessions (maintenance function)
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->conn->prepare("DELETE FROM active_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0) {
                error_log("Cleaned up {$deletedCount} expired sessions");
            }
            
            return $deletedCount;
            
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get active sessions for admin monitoring
     */
    public function getActiveSessions($adminId = null) {
        try {
            // Check if user has permission to view sessions
            if ($adminId && !$this->canViewSessions($adminId)) {
                throw new Exception('Insufficient permissions to view active sessions');
            }
            
            $stmt = $this->conn->prepare("
                SELECT 
                    s.*, 
                    u.fullname, 
                    u.email,
                    TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as minutes_inactive
                FROM active_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.expires_at > NOW()
                ORDER BY s.last_activity DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get active sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user can view session information
     */
    private function canViewSessions($userId) {
        $stmt = $this->conn->prepare("
            SELECT user_type FROM users WHERE id = ? AND user_type = 'admin'
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch() !== false;
    }
}
?>