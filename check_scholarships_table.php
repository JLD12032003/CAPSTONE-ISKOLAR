<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Current scholarships table structure:\n\n";
    
    $stmt = $conn->query('DESCRIBE scholarships');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>