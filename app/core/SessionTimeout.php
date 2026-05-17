<?php
/**
 * SessionTimeout Class
 * Adds session timeout functionality without breaking existing code
 * Maintains all current functionality while adding security timeout features
 */

class SessionTimeout {
    private $timeouts = [
        'student' => 3600,    // 1 hour
        'provider' => 7200,   // 2 hours  
        'admin' => 1800       // 30 minutes (more secure for admins)
    ];
    
    private $warningTime = 300; // 5 minutes warning before timeout
    
    public function __construct() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Initialize session timeout tracking
     * Call this after successful login
     */
    public function initTimeout($userType) {
        $_SESSION['session_start'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['timeout_duration'] = $this->timeouts[$userType] ?? $this->timeouts['student'];
        $_SESSION['user_type_timeout'] = $userType;
    }
    
    /**
     * Check if session is still valid
     * Returns true if valid, false if timed out
     */
    public function isValid() {
        if (!isset($_SESSION['last_activity']) || !isset($_SESSION['timeout_duration'])) {
            return true; // Don't break existing sessions
        }
        
        $currentTime = time();
        $timeSinceActivity = $currentTime - $_SESSION['last_activity'];
        
        if ($timeSinceActivity > $_SESSION['timeout_duration']) {
            return false; // Session timed out
        }
        
        // Update last activity
        $_SESSION['last_activity'] = $currentTime;
        return true;
    }
    
    /**
     * Get time remaining before timeout
     */
    public function getTimeRemaining() {
        if (!isset($_SESSION['last_activity']) || !isset($_SESSION['timeout_duration'])) {
            return null;
        }
        
        $currentTime = time();
        $timeSinceActivity = $currentTime - $_SESSION['last_activity'];
        $timeRemaining = $_SESSION['timeout_duration'] - $timeSinceActivity;
        
        return max(0, $timeRemaining);
    }
    
    /**
     * Check if warning should be shown
     */
    public function shouldShowWarning() {
        $timeRemaining = $this->getTimeRemaining();
        return $timeRemaining !== null && $timeRemaining <= $this->warningTime && $timeRemaining > 0;
    }
    
    /**
     * Extend session (refresh timeout)
     */
    public function extendSession() {
        if (isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * Get timeout configuration for JavaScript
     */
    public function getConfig() {
        if (!isset($_SESSION['timeout_duration'])) {
            return null;
        }
        
        return [
            'timeout_duration' => $_SESSION['timeout_duration'],
            'warning_time' => $this->warningTime,
            'time_remaining' => $this->getTimeRemaining(),
            'last_activity' => $_SESSION['last_activity'] ?? time()
        ];
    }
    
    /**
     * Clean up session on timeout
     */
    public function cleanup() {
        // Don't destroy session completely, just mark as timed out
        $_SESSION['timed_out'] = true;
        $_SESSION['timeout_reason'] = 'inactivity';
    }
}
?>