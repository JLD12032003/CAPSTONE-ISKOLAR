<?php
/**
 * Test Real Login Flow
 * Creates a test user and verifies login logging works through actual web interface
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔐 Testing Real Login Flow...\n";
    echo "============================\n";
    
    // Create a test user for real login testing
    $testEmail = 'realtest@example.com';
    $testPassword = 'TestPass123!';
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    
    // Insert/update test user
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, email, password, user_type, is_verified) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        password = VALUES(password), is_verified = VALUES(is_verified)
    ");
    $stmt->execute(['Real Test User', $testEmail, $hashedPassword, 'student', 1]);
    
    $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$testEmail]);
    $testUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    $testUserId = $testUser['id'];
    
    echo "✅ Test user ready: $testEmail (ID: $testUserId)\n";
    echo "   Password: $testPassword\n";
    
    // Clear previous test attempts
    $conn->exec("DELETE FROM login_attempts WHERE email = '$testEmail'");
    
    echo "\n📊 Current Login Attempts Count:\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM login_attempts");
    $beforeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total login attempts before test: $beforeCount\n";
    
    echo "\n🌐 Instructions for Manual Testing:\n";
    echo "==================================\n";
    echo "1. Open your web browser\n";
    echo "2. Go to your ISKOLar website\n";
    echo "3. Click 'Login' button\n";
    echo "4. Try these login attempts:\n";
    echo "\n   ✅ SUCCESSFUL LOGIN:\n";
    echo "      Email: $testEmail\n";
    echo "      Password: $testPassword\n";
    echo "\n   ❌ FAILED LOGIN (wrong password):\n";
    echo "      Email: $testEmail\n";
    echo "      Password: wrongpassword\n";
    echo "\n   ❌ FAILED LOGIN (wrong email):\n";
    echo "      Email: nonexistent@example.com\n";
    echo "      Password: anypassword\n";
    
    echo "\n5. After testing, run this command to check results:\n";
    echo "   php check_login_attempts.php\n";
    
    echo "\n⏳ Waiting for you to test... (Press Enter when done)\n";
    
    // Wait for user input
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    echo "\n📊 Checking Results After Manual Test:\n";
    echo "=====================================\n";
    
    // Check total count after test
    $stmt = $conn->query("SELECT COUNT(*) as count FROM login_attempts");
    $afterCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total login attempts after test: $afterCount\n";
    echo "New attempts added: " . ($afterCount - $beforeCount) . "\n";
    
    // Check specific attempts for our test user
    $stmt = $conn->prepare("
        SELECT id, email, ip_address, attempt_type, failure_reason, created_at
        FROM login_attempts 
        WHERE email = ? OR created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$testEmail]);
    $testAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($testAttempts) > 0) {
        echo "\n✅ Login attempts found:\n";
        foreach ($testAttempts as $attempt) {
            $reason = $attempt['failure_reason'] ? " ({$attempt['failure_reason']})" : '';
            echo "📝 {$attempt['email']} - {$attempt['attempt_type']}{$reason} - {$attempt['created_at']}\n";
        }
    } else {
        echo "\n❌ No new login attempts found.\n";
        echo "This might mean:\n";
        echo "1. You didn't test the login yet\n";
        echo "2. There's an issue with the integration\n";
        echo "3. The login attempts are not being logged\n";
    }
    
    // Check system activities for successful logins
    $stmt = $conn->prepare("
        SELECT id, user_type, action_type, action_description, created_at
        FROM system_activity_logs 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$testUserId]);
    $systemActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($systemActivities) > 0) {
        echo "\n✅ System activities found:\n";
        foreach ($systemActivities as $activity) {
            echo "📊 {$activity['user_type']} - {$activity['action_type']} - {$activity['action_description']} - {$activity['created_at']}\n";
        }
    } else {
        echo "\n⚠️ No system activities found (this is normal if no successful logins occurred)\n";
    }
    
    echo "\n🔍 Integration Status:\n";
    echo "====================\n";
    
    if ($afterCount > $beforeCount) {
        echo "✅ SUCCESS: Login logging is working!\n";
        echo "   New login attempts were recorded in the database.\n";
        echo "   The ActivityLogger integration in index.php is functional.\n";
    } else {
        echo "❌ ISSUE: No new login attempts recorded.\n";
        echo "   Please check:\n";
        echo "   1. Did you actually try logging in through the web interface?\n";
        echo "   2. Is the website accessible and working?\n";
        echo "   3. Are there any PHP errors in the web server logs?\n";
    }
    
    echo "\n📋 Summary:\n";
    echo "==========\n";
    echo "• Test user created: $testEmail\n";
    echo "• Login attempts before: $beforeCount\n";
    echo "• Login attempts after: $afterCount\n";
    echo "• New attempts: " . ($afterCount - $beforeCount) . "\n";
    echo "• Test user attempts: " . count(array_filter($testAttempts, function($a) use ($testEmail) { return $a['email'] === $testEmail; })) . "\n";
    
    // Keep test user for future testing
    echo "\n💡 Test user '$testEmail' will remain in database for future testing.\n";
    echo "   You can use it anytime to verify login logging is working.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>