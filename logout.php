<?php
/**
 * Logout Script
 * Handles user logout and session cleanup
 * Now includes session timeout handling
 */

session_start();

// Check logout reason
$reason = $_GET['reason'] ?? 'manual';
$message = 'success';

if ($reason === 'timeout') {
    $message = 'timeout';
}

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear any cookies if they exist
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to home page with appropriate message
header("Location: index.php?logout=$message");
exit();
?>