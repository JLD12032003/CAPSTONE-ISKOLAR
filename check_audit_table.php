<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();

    echo "Checking scholarship_audit_log table structure...\n";
    $stmt = $conn->query('DESCRIBE scholarship_audit_log');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
    echo "\nChecking if table has any records...\n";
    $stmt = $conn->query('SELECT COUNT(*) as count FROM scholarship_audit_log');
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Records in audit log: $count\n";
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>