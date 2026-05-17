<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "📋 All Tables in Database:\n";
    echo "=========================\n";
    
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "- {$table}\n";
    }
    
    echo "\nTotal tables: " . count($tables) . "\n\n";
    
    // Check specifically for logging tables
    echo "🔍 Checking for Logging Tables:\n";
    echo "===============================\n";
    
    $loggingTables = [
        'login_attempts',
        'login_attempt_logs', 
        'admin_activity_logs',
        'system_activity_logs',
        'security_events',
        'audit_trail'
    ];
    
    foreach ($loggingTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ {$table} - EXISTS\n";
        } else {
            echo "❌ {$table} - MISSING\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>