<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    echo "Users table structure:\n";
    $stmt = $conn->query('DESCRIBE users');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nAdmin verifications table structure:\n";
    $stmt = $conn->query('DESCRIBE admin_verifications');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>