<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Scholarship Applications Table Structure:\n";
    echo "=====================================\n";
    
    $stmt = $conn->prepare('DESCRIBE scholarship_applications');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['Field'] . ' - ' . $column['Type'] . "\n";
    }
    
    echo "\nSample Application Data:\n";
    echo "=======================\n";
    
    $stmt = $conn->prepare('SELECT id, student_id, documents FROM scholarship_applications LIMIT 1');
    $stmt->execute();
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        echo "Application ID: " . $sample['id'] . "\n";
        echo "Student ID: " . $sample['student_id'] . "\n";
        echo "Documents: " . ($sample['documents'] ?? 'NULL') . "\n";
    } else {
        echo "No applications found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>