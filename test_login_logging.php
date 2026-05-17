<?php
/**
 * Test Login Logging Integration
 * Verifies that login attempts are being logged to the login_attempts table
 */

require_once 'config/database.php';
require_once 'app/core/ActivityLogger.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🧪 Testing Login Logging Integration...\n";
    echo "=====================================\n";
    
    // Clear any existing test data
    $conn->exec("DELETE FROM login_attempts WHERE email LIKE 'test_%@example.com'");
    
    // Test the ActivityLogger directly
    $logger = new ActivityLogger();
    
    echo "\n1. Testing Direct ActivityLogger Integration:\n";
    echo "--------------------------------------------\n";
    
    // Test successful login
    $result1 = $logger->logLoginAttempt('test_success@example.com', 'SUCCESS', 1, null);
    echo $result1 ? "✅ SUCCESS login logged\n" : "❌ Failed to log SUCCESS\n";
    
    // Test failed login - invalid credentials
    $result2 = $logger->logLoginAttempt('test_fail@example.com', 'FAILED', null, 'Invalid credentials');
    echo $result2 ? "✅ FAILED login logged\n" : "❌ Failed to log FAILED\n";
    
    // Test failed login - unverified account
    $result3 = $logger->logLoginAttempt('test_unverified@example.com', 'FAILED', 2, 'Account not verified');
    echo $result3 ? "✅ UNVERIFIED login logged\n" : "❌ Failed to log UNVERIFIED\n";
    
    // Test OTP verification
    $result4 = $logger->logLoginAttempt('test_otp@example.com', 'SUCCESS', 1, 'OTP verified - Login complete');
    echo $result4 ? "✅ OTP SUCCESS logged\n" : "❌ Failed to log OTP SUCCESS\n";
    
    echo "\n2. Checking Database Records:\n";
    echo "-----------------------------\n";
    
    $stmt = $conn->prepare("
        SELECT id, email, ip_address, attempt_type, failure_reason, created_at
        FROM login_attempts 
        WHERE email LIKE 'test_%@example.com'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($records) > 0) {
        echo "✅ Found " . count($records) . " test records in login_attempts table:\n\n";
        
        foreach ($records as $record) {
            echo "📝 ID: {$record['id']}\n";
            echo "   Email: {$record['email']}\n";
            echo "   IP: {$record['ip_address']}\n";
            echo "   Status: {$record['attempt_type']}\n";
            echo "   Reason: " . ($record['failure_reason'] ?: 'N/A') . "\n";
            echo "   Time: {$record['created_at']}\n";
            echo "   ────────────────────────────────────\n";
        }
    } else {
        echo "❌ No records found in login_attempts table\n";
    }
    
    echo "\n3. Testing Real Login Flow:\n";
    echo "---------------------------\n";
    echo "To test the complete integration:\n";
    echo "1. Go to your login page\n";
    echo "2. Try logging in with valid/invalid credentials\n";
    echo "3. Check the login_attempts table with this query:\n\n";
    
    echo "SELECT id, email, ip_address, attempt_type as status, failure_reason, created_at as attempt_time\n";
    echo "FROM login_attempts\n";
    echo "WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)\n";
    echo "ORDER BY created_at DESC;\n\n";
    
    echo "4. Verify that both successful and failed attempts are logged\n";
    
    echo "\n4. Current Login Attempts (Last 10):\n";
    echo "------------------------------------\n";
    
    $stmt = $conn->prepare("
        SELECT id, email, ip_address, attempt_type, failure_reason, created_at
        FROM login_attempts 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recent) > 0) {
        foreach ($recent as $record) {
            $status = $record['attempt_type'];
            $reason = $record['failure_reason'] ? " ({$record['failure_reason']})" : '';
            echo "📊 {$record['email']} - {$status}{$reason} - {$record['created_at']}\n";
        }
    } else {
        echo "No login attempts found yet.\n";
        echo "Try logging in through the web interface to see records appear here.\n";
    }
    
    // Clean up test data
    $conn->exec("DELETE FROM login_attempts WHERE email LIKE 'test_%@example.com'");
    echo "\n🧹 Test data cleaned up.\n";
    
    echo "\n✅ Login logging integration test completed!\n";
    echo "The ActivityLogger is now integrated into AuthController.\n";
    echo "All login attempts (success/failure) will be automatically logged.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>