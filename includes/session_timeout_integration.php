<?php
/**
 * Session Timeout Integration
 * Include this file in existing pages to add session timeout functionality
 * Does not break existing code - only adds timeout features
 */

// Only initialize if session exists and user is logged in
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    
    // Include session timeout class
    require_once __DIR__ . '/../app/core/SessionTimeout.php';
    
    $sessionTimeout = new SessionTimeout();
    
    // Initialize timeout if not already done
    if (!isset($_SESSION['session_start'])) {
        $userType = $_SESSION['user_type'] ?? 'student';
        $sessionTimeout->initTimeout($userType);
    }
    
    // Check if session is still valid
    if (!$sessionTimeout->isValid()) {
        // Session timed out - redirect to logout
        $sessionTimeout->cleanup();
        header('Location: logout.php?reason=timeout');
        exit;
    }
    
    // Get timeout configuration for JavaScript
    $timeoutConfig = $sessionTimeout->getConfig();
    
    if ($timeoutConfig) {
        // Output JavaScript configuration
        echo '<script type="text/javascript">';
        echo 'var sessionTimeoutConfig = ' . json_encode([
            'timeoutDuration' => $timeoutConfig['timeout_duration'],
            'warningTime' => $timeoutConfig['warning_time'],
            'timeRemaining' => $timeoutConfig['time_remaining'],
            'checkInterval' => 60,
            'extendUrl' => 'extend_session.php',
            'logoutUrl' => 'logout.php'
        ]) . ';';
        echo '</script>';
        
        // Include the session timeout JavaScript
        echo '<script src="assets/js/session-timeout.js"></script>';
    }
}
?>