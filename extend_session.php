<?php
/**
 * Session Extension Endpoint
 * Handles AJAX requests to extend user sessions
 * Maintains existing functionality while adding timeout support
 */

header('Content-Type: application/json');

// Start session
session_start();

// Include session timeout class
require_once 'app/core/SessionTimeout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $sessionTimeout = new SessionTimeout();
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'extend') {
        // Extend the session
        $extended = $sessionTimeout->extendSession();
        
        if ($extended) {
            echo json_encode([
                'success' => true,
                'message' => 'Session extended',
                'time_remaining' => $sessionTimeout->getTimeRemaining(),
                'config' => $sessionTimeout->getConfig()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to extend session'
            ]);
        }
    } elseif ($action === 'check') {
        // Check session status
        $isValid = $sessionTimeout->isValid();
        
        echo json_encode([
            'success' => true,
            'valid' => $isValid,
            'time_remaining' => $sessionTimeout->getTimeRemaining(),
            'show_warning' => $sessionTimeout->shouldShowWarning(),
            'config' => $sessionTimeout->getConfig()
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>