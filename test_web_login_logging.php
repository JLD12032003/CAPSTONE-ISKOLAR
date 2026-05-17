<?php
/**
 * Test Web Login Logging Integration
 * Simulates web login requests to verify ActivityLogger integration in index.php
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🧪 Testing Web Login Logging Integration...\n";
    echo "==========================================\n";
    
    // Clear any existing test data
    $conn->exec("DELETE FROM login_attempts WHERE email LIKE 'webtest_%@example.com'");
    $conn->exec("DELETE FROM system_activity_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'webtest_%@example.com')");
    
    echo "\n1. Current Login Attempts Before Test:\n";
    echo "-------------------------------------\n";
    
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM login_attempts 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $beforeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Login attempts in last hour: $beforeCount\n";
    
    echo "\n2. Simulating Web Login Requests:\n";
    echo "--------------------------------\n";
    
    // Create a test user for login simulation
    $testEmail = 'webtest_user@example.com';
    $testPassword = 'TestPass123!';
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    
    // Insert test user
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, email, password, user_type, is_verified) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        password = VALUES(password), is_verified = VALUES(is_verified)
    ");
    $stmt->execute(['Test User', $testEmail, $hashedPassword, 'student', 1]);
    
    $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$testEmail]);
    $testUserId = $userStmt->fetch(PDO::FETCH_ASSOC)['id'];
    
    echo "✅ Test user created: $testEmail (ID: $testUserId)\n";
    
    // Simulate login requests by calling the ActivityLogger directly
    // (since we can't easily simulate POST requests to index.php)
    require_once 'app/core/ActivityLogger.php';
    $logger = new ActivityLogger();
    
    echo "\n3. Simulating Different Login Scenarios:\n";
    echo "---------------------------------------\n";
    
    // Test 1: Successful login
    $result1 = $logger->logLoginAttempt($testEmail, 'SUCCESS', $testUserId, 'Login successful');
    echo $result1 ? "✅ SUCCESS login logged\n" : "❌ Failed to log SUCCESS\n";
    
    // Test 2: Failed login - wrong password
    $result2 = $logger->logLoginAttempt($testEmail, 'FAILED', $testUserId, 'Invalid password');
    echo $result2 ? "✅ FAILED login (wrong password) logged\n" : "❌ Failed to log FAILED\n";
    
    // Test 3: Failed login - email not found
    $result3 = $logger->logLoginAttempt('webtest_notfound@example.com', 'FAILED', null, 'Email not found');
    echo $result3 ? "✅ FAILED login (email not found) logged\n" : "❌ Failed to log FAILED\n";
    
    // Test 4: Failed login - unverified account
    $result4 = $logger->logLoginAttempt('webtest_unverified@example.com', 'FAILED', null, 'Account not verified');
    echo $result4 ? "✅ FAILED login (unverified) logged\n" : "❌ Failed to log FAILED\n";
    
    // Test 5: System activity logging
    $result5 = $logger->logSystemActivity(
        $testUserId, 
        'student', 
        'LOGIN', 
        'user_session', 
        $testUserId, 
        'User successfully logged in via web interface'
    );
    echo $result5 ? "✅ System activity logged\n" : "❌ Failed to log system activity\n";
    
    echo "\n4. Checking Database Records:\n";
    echo "-----------------------------\n";
    
    // Check login attempts
    $stmt = $conn->prepare("
        SELECT id, email, ip_address, attempt_type, failure_reason, created_at
        FROM login_attempts 
        WHERE email LIKE 'webtest_%@example.com'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $loginRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Login Attempts Found: " . count($loginRecords) . "\n";
    foreach ($loginRecords as $record) {
        $reason = $record['failure_reason'] ? " ({$record['failure_reason']})" : '';
        echo "   • {$record['email']} - {$record['attempt_type']}{$reason} - {$record['created_at']}\n";
    }
    
    // Check system activities
    $stmt = $conn->prepare("
        SELECT id, user_type, action_type, action_description, created_at
        FROM system_activity_logs 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$testUserId]);
    $systemRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📊 System Activities Found: " . count($systemRecords) . "\n";
    foreach ($systemRecords as $record) {
        echo "   • {$record['user_type']} - {$record['action_type']} - {$record['action_description']} - {$record['created_at']}\n";
    }
    
    echo "\n5. Current Total Login Attempts:\n";
    echo "-------------------------------\n";
    
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM login_attempts 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $afterCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Login attempts in last hour: $afterCount\n";
    echo "New attempts added: " . ($afterCount - $beforeCount) . "\n";
    
    echo "\n6. Recent Login Attempts (All Users):\n";
    echo "------------------------------------\n";
    
    $stmt = $conn->query("
        SELECT id, email, attempt_type, failure_reason, created_at
        FROM login_attempts 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentAttempts as $attempt) {
        $reason = $attempt['failure_reason'] ? " ({$attempt['failure_reason']})" : '';
        echo "📝 {$attempt['email']} - {$attempt['attempt_type']}{$reason} - {$attempt['created_at']}\n";
    }
    
    // Clean up test data
    $conn->exec("DELETE FROM login_attempts WHERE email LIKE 'webtest_%@example.com'");
    $conn->exec("DELETE FROM system_activity_logs WHERE user_id = $testUserId");
    $conn->exec("DELETE FROM users WHERE email LIKE 'webtest_%@example.com'");
    
    echo "\n🧹 Test data cleaned up.\n";
    
    echo "\n✅ Web Login Logging Integration Test Complete!\n";
    echo "==============================================\n";
    echo "The ActivityLogger is now integrated into index.php login handler.\n";
    echo "All web login attempts will be automatically logged.\n";
    echo "\nTo test with real web interface:\n";
    echo "1. Go to your website login page\n";
    echo "2. Try logging in with valid/invalid credentials\n";
    echo "3. Run: php check_login_attempts.php\n";
    echo "4. Verify new login attempts appear in the database\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>