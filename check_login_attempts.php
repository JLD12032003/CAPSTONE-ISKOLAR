<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "📊 Current Login Attempts (Last 50):\n";
    echo "====================================\n";
    
    $stmt = $conn->query("
        SELECT id, email, ip_address, attempt_type as status, failure_reason, created_at as attempt_time 
        FROM login_attempts 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($results as $row) {
        $reason = $row['failure_reason'] ? ' (' . $row['failure_reason'] . ')' : '';
        echo "ID: {$row['id']} | {$row['email']} | {$row['status']}{$reason} | {$row['attempt_time']}\n";
    }
    
    echo "\nTotal records: " . count($results) . "\n";
    
    echo "\n📈 Login Attempt Statistics:\n";
    echo "============================\n";
    
    $stmt = $conn->query("
        SELECT 
            attempt_type,
            COUNT(*) as count,
            DATE(created_at) as date
        FROM login_attempts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY attempt_type, DATE(created_at)
        ORDER BY date DESC, attempt_type
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($stats as $stat) {
        echo "{$stat['date']} | {$stat['attempt_type']}: {$stat['count']} attempts\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>