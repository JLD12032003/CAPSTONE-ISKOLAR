<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Keep only school admin accounts, remove president, vp, committee accounts
    $conn->exec("DELETE FROM admin_verifications WHERE user_id IN (SELECT id FROM users WHERE admin_role IN ('president', 'vp', 'committee'))");
    $conn->exec("DELETE FROM users WHERE admin_role IN ('president', 'vp', 'committee')");
    
    echo "Removed president, vp, and committee user accounts.\n";
    echo "Only school administrators can now access the system.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>