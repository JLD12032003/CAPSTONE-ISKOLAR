<?php
/**
 * ISKOLar Database Migration Helper
 * Run this once to set up user_type column
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Check if column already exists
    $checkStmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'user_type'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo "✅ Column 'user_type' already exists! No migration needed.\n";
        exit;
    }
    
    // Add the column
    $sql = "ALTER TABLE users ADD COLUMN user_type ENUM('student', 'donor', 'foundation') NOT NULL DEFAULT 'student' AFTER google_id";
    
    $conn->exec($sql);
    
    echo "✅ SUCCESS! Column 'user_type' has been added to the users table.\n";
    echo "📋 Migration Details:\n";
    echo "   - Database: email_auth\n";
    echo "   - Table: users\n";
    echo "   - New Column: user_type (ENUM)\n";
    echo "   - Values: 'student', 'donor', 'foundation'\n";
    echo "   - Default: 'student'\n";
    echo "\n🎉 Your ISKOLar application is now ready!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
