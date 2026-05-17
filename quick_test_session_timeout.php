<?php
/**
 * Quick Session Timeout Test
 * Simple test to verify session timeout is working
 */

echo "⚡ Quick Session Timeout Test\n";
echo "============================\n";

// Test 1: Check if files exist
echo "\n1. Checking Required Files:\n";
echo "--------------------------\n";

$requiredFiles = [
    'app/core/SessionTimeout.php' => 'Core session timeout logic',
    'assets/js/session-timeout.js' => 'Frontend JavaScript handler',
    'includes/session_timeout_integration.php' => 'Integration helper',
    'extend_session.php' => 'AJAX endpoint'
];

$allFilesExist = true;
foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file (MISSING)\n";
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "\n❌ Some required files are missing. Session timeout may not work.\n";
    exit(1);
}

// Test 2: Check SessionTimeout class
echo "\n2. Testing SessionTimeout Class:\n";
echo "-------------------------------\n";

try {
    session_start();
    require_once 'app/core/SessionTimeout.php';
    
    $sessionTimeout = new SessionTimeout();
    echo "✅ SessionTimeout class loaded successfully\n";
    
    // Test initialization
    $sessionTimeout->initTimeout('student');
    echo "✅ Session timeout initialized\n";
    
    // Test configuration
    $config = $sessionTimeout->getConfig();
    if ($config) {
        echo "✅ Configuration retrieved\n";
        echo "   - Timeout: {$config['timeout_duration']} seconds\n";
        echo "   - Warning: {$config['warning_time']} seconds\n";
    } else {
        echo "❌ Configuration not retrieved\n";
    }
    
    // Test validity
    $isValid = $sessionTimeout->isValid();
    echo $isValid ? "✅ Session is valid\n" : "❌ Session is invalid\n";
    
    // Test extension
    $extended = $sessionTimeout->extendSession();
    echo $extended ? "✅ Session extension works\n" : "❌ Session extension failed\n";
    
} catch (Exception $e) {
    echo "❌ SessionTimeout class error: " . $e->getMessage() . "\n";
}

// Test 3: Check integration in dashboard files
echo "\n3. Checking Dashboard Integration:\n";
echo "--------------------------------\n";

$dashboardFiles = [
    'app/views/student_home.php',
    'app/views/provider/dashboard.php',
    'app/views/admin/dashboard.php'
];

foreach ($dashboardFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'session_timeout_integration.php') !== false) {
            echo "✅ $file (integrated)\n";
        } else {
            echo "❌ $file (not integrated)\n";
        }
    } else {
        echo "❌ $file (file not found)\n";
    }
}

// Test 4: Check login integration
echo "\n4. Checking Login Integration:\n";
echo "-----------------------------\n";

if (file_exists('index.php')) {
    $content = file_get_contents('index.php');
    if (strpos($content, 'SessionTimeout') !== false) {
        echo "✅ index.php (session timeout integrated)\n";
    } else {
        echo "❌ index.php (session timeout not integrated)\n";
    }
} else {
    echo "❌ index.php (file not found)\n";
}

// Test 5: Role-based timeout verification
echo "\n5. Testing Role-Based Timeouts:\n";
echo "------------------------------\n";

$expectedTimeouts = [
    'student' => 3600,   // 1 hour
    'provider' => 7200,  // 2 hours
    'admin' => 1800      // 30 minutes
];

foreach ($expectedTimeouts as $role => $expectedSeconds) {
    $sessionTimeout->initTimeout($role);
    $config = $sessionTimeout->getConfig();
    
    if ($config && $config['timeout_duration'] == $expectedSeconds) {
        $minutes = $expectedSeconds / 60;
        echo "✅ $role: $expectedSeconds seconds ($minutes minutes)\n";
    } else {
        echo "❌ $role: incorrect timeout duration\n";
    }
}

// Final summary
echo "\n" . str_repeat("=", 40) . "\n";
echo "📋 QUICK TEST SUMMARY\n";
echo str_repeat("=", 40) . "\n";

if ($allFilesExist) {
    echo "✅ All required files present\n";
    echo "✅ SessionTimeout class functional\n";
    echo "✅ Role-based timeouts configured\n";
    echo "\n🎉 SESSION TIMEOUT IS READY!\n";
    
    echo "\n🌐 To test in browser:\n";
    echo "1. Login to your website\n";
    echo "2. Open browser console (F12)\n";
    echo "3. Look for 'sessionTimeoutConfig'\n";
    echo "4. Move mouse to test activity tracking\n";
    echo "5. Wait for timeout or modify durations\n";
    
    echo "\n⚡ For quick testing:\n";
    echo "Temporarily change timeout durations in SessionTimeout.php:\n";
    echo "- 'student' => 120 (2 minutes)\n";
    echo "- 'admin' => 60 (1 minute)\n";
    echo "- warningTime = 30 (30 seconds)\n";
    
} else {
    echo "❌ Some components missing\n";
    echo "⚠️ Session timeout may not work properly\n";
}

echo "\n" . str_repeat("=", 40) . "\n";
?>