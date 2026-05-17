<?php
/**
 * Check Foreign Key Constraints
 * Identify and fix foreign key constraint issues
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔍 Checking Foreign Key Constraints...\n\n";
    
    // Get database name
    $stmt = $conn->query("SELECT DATABASE() as db_name");
    $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
    
    echo "Database: {$dbName}\n\n";
    
    // Check foreign key constraints for scholarship_applications table
    echo "📋 Foreign Key Constraints for scholarship_applications:\n";
    echo "=====================================================\n";
    
    $stmt = $conn->prepare("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'scholarship_applications'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute([$dbName]);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "No foreign key constraints found.\n\n";
    } else {
        foreach ($constraints as $constraint) {
            echo "Constraint: {$constraint['CONSTRAINT_NAME']}\n";
            echo "Column: {$constraint['COLUMN_NAME']}\n";
            echo "References: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n\n";
        }
    }
    
    // Check if there are any orphaned records
    echo "🔍 Checking for Orphaned Records:\n";
    echo "================================\n";
    
    // Check scholarship_applications with invalid scholarship_id
    $stmt = $conn->query("
        SELECT COUNT(*) as count
        FROM scholarship_applications sa
        LEFT JOIN scholarships s ON sa.scholarship_id = s.id
        WHERE s.id IS NULL
    ");
    $orphanedScholarships = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Applications with invalid scholarship_id: {$orphanedScholarships}\n";
    
    // Check scholarship_applications with invalid student_id
    $stmt = $conn->query("
        SELECT COUNT(*) as count
        FROM scholarship_applications sa
        LEFT JOIN users u ON sa.student_id = u.id
        WHERE u.id IS NULL
    ");
    $orphanedStudents = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Applications with invalid student_id: {$orphanedStudents}\n";
    
    // Check scholarships with invalid provider_id
    $stmt = $conn->query("
        SELECT COUNT(*) as count
        FROM scholarships s
        LEFT JOIN users u ON s.provider_id = u.id
        WHERE u.id IS NULL
    ");
    $orphanedProviders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Scholarships with invalid provider_id: {$orphanedProviders}\n\n";
    
    // Show current table counts
    echo "📊 Current Table Counts:\n";
    echo "=======================\n";
    
    $tables = ['users', 'scholarships', 'scholarship_applications'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "{$table}: {$count} records\n";
    }
    
    echo "\n";
    
    // Check specific error from the screenshot
    echo "🚨 Checking Specific Error Condition:\n";
    echo "====================================\n";
    
    // The error mentions: Cannot add or update a child row: a foreign key constraint fails
    // Let's check if there are any foreign key constraints that might be causing issues
    
    $stmt = $conn->query("SHOW CREATE TABLE scholarship_applications");
    $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "scholarship_applications table definition:\n";
    echo $createTable['Create Table'] . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>