<?php
require_once 'config/database.php';
require_once 'app/core/ActivityLogger.php';

try {
    $database = new Database();
    $conn = $database->connect();
    $logger = new ActivityLogger();
    
    echo "🧪 Testing Log Queries...\n";
    echo "========================\n\n";
    
    // 1. Test the correct table names
    echo "📋 Testing Login Attempts Query:\n";
    $stmt = $conn->query("
        SELECT id, email, ip_address, attempt_type as status, created_at as attempt_time
        FROM login_attempts
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "No login attempts found. Let me create some test data...\n";
        
        // Create test login attempts
        $logger->logLoginAttempt('test@example.com', 'SUCCESS', 1);
        $logger->logLoginAttempt('admin@test.com', 'FAILED', null, 'Invalid password');
        $logger->logLoginAttempt('hacker@bad.com', 'BLOCKED', null, 'Too many attempts');
        
        echo "✅ Created test login attempts\n";
        
        // Re-run query
        $stmt = $conn->query("
            SELECT id, email, ip_address, attempt_type as status, created_at as attempt_time
            FROM login_attempts
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($results as $row) {
        echo "  ID: {$row['id']} | Email: {$row['email']} | Status: {$row['status']} | Time: {$row['attempt_time']}\n";
    }
    
    echo "\n📋 Testing Admin Activity Query:\n";
    $stmt = $conn->query("
        SELECT id, admin_id, action_type, risk_level, created_at
        FROM admin_activity_logs
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "No admin activities found. Let me create some test data...\n";
        
        // Create test admin activities
        $logger->logAdminActivity(1, 'LOGIN', null, null, 'Admin logged into system', null, 'LOW');
        $logger->logAdminActivity(1, 'USER_MANAGEMENT', 'user', 2, 'Created new user account', ['user_type' => 'student'], 'MEDIUM');
        $logger->logAdminActivity(1, 'SYSTEM_CONFIG', 'settings', null, 'Updated system configuration', ['setting' => 'email_config'], 'HIGH');
        
        echo "✅ Created test admin activities\n";
        
        // Re-run query
        $stmt = $conn->query("
            SELECT id, admin_id, action_type, risk_level, created_at
            FROM admin_activity_logs
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($results as $row) {
        echo "  ID: {$row['id']} | Admin: {$row['admin_id']} | Action: {$row['action_type']} | Risk: {$row['risk_level']} | Time: {$row['created_at']}\n";
    }
    
    echo "\n📋 Testing Security Events Query:\n";
    $stmt = $conn->query("
        SELECT id, event_type, severity, event_description, created_at
        FROM security_events
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "No security events found. Let me create some test data...\n";
        
        // Create test security events
        $logger->createSecurityEvent('MULTIPLE_FAILED_LOGINS', 'HIGH', null, 'Multiple failed login attempts detected from IP 192.168.1.100');
        $logger->createSecurityEvent('UNUSUAL_ACTIVITY', 'MEDIUM', 1, 'User accessed system outside normal hours');
        
        echo "✅ Created test security events\n";
        
        // Re-run query
        $stmt = $conn->query("
            SELECT id, event_type, severity, event_description, created_at
            FROM security_events
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($results as $row) {
        echo "  ID: {$row['id']} | Type: {$row['event_type']} | Severity: {$row['severity']} | Time: {$row['created_at']}\n";
        echo "    Description: {$row['event_description']}\n";
    }
    
    echo "\n✅ All queries working correctly!\n\n";
    
    echo "📋 CORRECT QUERIES TO USE:\n";
    echo "=========================\n";
    echo "-- Login attempts:\n";
    echo "SELECT id, email, ip_address, attempt_type as status, created_at as attempt_time\n";
    echo "FROM login_attempts ORDER BY created_at DESC LIMIT 50;\n\n";
    
    echo "-- Admin activities:\n";
    echo "SELECT id, admin_id, action_type, risk_level, created_at\n";
    echo "FROM admin_activity_logs ORDER BY created_at DESC LIMIT 50;\n\n";
    
    echo "-- Security events:\n";
    echo "SELECT id, event_type, severity, event_description, created_at\n";
    echo "FROM security_events ORDER BY created_at DESC LIMIT 50;\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>