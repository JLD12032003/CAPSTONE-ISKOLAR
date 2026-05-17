<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Delete existing admin verifications
    $conn->exec("DELETE FROM admin_verifications WHERE user_id IN (SELECT id FROM users WHERE user_type = 'admin')");
    
    // Delete existing admin users
    $conn->exec("DELETE FROM users WHERE user_type = 'admin'");
    
    echo "Existing admin users deleted successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>