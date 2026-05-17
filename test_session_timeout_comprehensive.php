<?php
/**
 * Comprehensive Session Timeout Test
 * Tests all aspects of session timeout functionality
 */

session_start();

require_once 'app/core/SessionTimeout.php';

echo "🧪 Comprehensive Session Timeout Test\n";
echo "====================================\n";

class SessionTimeoutTester {
    private $sessionTimeout;
    private $testResults = [];
    
    public function __construct() {
        $this->sessionTimeout = new SessionTimeout();
    }
    
    public function runAllTests() {
        echo "\n🔄 Running comprehensive session timeout tests...\n\n";
        
        $this->testInitialization();
        $this->testRoleBasedTimeouts();
        $this->testSessionValidity();
        $this->testWarningSystem();
        $this->testSessionExtension();
        $this->testConfiguration();
        $this->testTimeoutSimulation();
        
        $this->displayResults();
        $this->showManualTestingInstructions();
    }
    
    private function testInitialization() {
        echo "1. Testing Session Initialization:\n";
        echo "--------------------------------\n";
        
        try {
            $this->sessionTimeout->initTimeout('student');
            $this->addResult('Session initialization', true, 'Student timeout initialized');
            echo "✅ Student session initialized\n";
            
            $config = $this->sessionTimeout->getConfig();
            if ($config && $config['timeout_duration'] == 3600) {
                $this->addResult('Configuration retrieval', true, 'Config retrieved correctly');
                echo "✅ Configuration retrieved: {$config['timeout_duration']} seconds\n";
            } else {
                $this->addResult('Configuration retrieval', false, 'Config not retrieved');
                echo "❌ Configuration not retrieved\n";
            }
        } catch (Exception $e) {
            $this->addResult('Session initialization', false, $e->getMessage());
            echo "❌ Initialization failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testRoleBasedTimeouts() {
        echo "2. Testing Role-Based Timeouts:\n";
        echo "------------------------------\n";
        
        $expectedTimeouts = [
            'student' => 3600,   // 1 hour
            'provider' => 7200,  // 2 hours
            'admin' => 1800      // 30 minutes
        ];
        
        foreach ($expectedTimeouts as $role => $expectedDuration) {
            try {
                $this->sessionTimeout->initTimeout($role);
                $config = $this->sessionTimeout->getConfig();
                
                if ($config && $config['timeout_duration'] == $expectedDuration) {
                    $this->addResult("$role timeout", true, "$expectedDuration seconds");
                    $minutes = $expectedDuration / 60;
                    echo "✅ $role: $expectedDuration seconds ($minutes minutes)\n";
                } else {
                    $this->addResult("$role timeout", false, "Expected $expectedDuration, got " . ($config['timeout_duration'] ?? 'null'));
                    echo "❌ $role: Incorrect timeout duration\n";
                }
            } catch (Exception $e) {
                $this->addResult("$role timeout", false, $e->getMessage());
                echo "❌ $role: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    private function testSessionValidity() {
        echo "3. Testing Session Validity:\n";
        echo "---------------------------\n";
        
        try {
            // Test with fresh session
            $this->sessionTimeout->initTimeout('student');
            $isValid = $this->sessionTimeout->isValid();
            
            if ($isValid) {
                $this->addResult('Fresh session validity', true, 'Session is valid');
                echo "✅ Fresh session is valid\n";
            } else {
                $this->addResult('Fresh session validity', false, 'Fresh session invalid');
                echo "❌ Fresh session is invalid\n";
            }
            
            // Test time remaining
            $timeRemaining = $this->sessionTimeout->getTimeRemaining();
            if ($timeRemaining > 0) {
                $this->addResult('Time remaining calculation', true, "$timeRemaining seconds");
                echo "✅ Time remaining: $timeRemaining seconds\n";
            } else {
                $this->addResult('Time remaining calculation', false, "Invalid time: $timeRemaining");
                echo "❌ Invalid time remaining: $timeRemaining\n";
            }
            
        } catch (Exception $e) {
            $this->addResult('Session validity', false, $e->getMessage());
            echo "❌ Session validity test failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testWarningSystem() {
        echo "4. Testing Warning System:\n";
        echo "-------------------------\n";
        
        try {
            // Test with fresh session (should not show warning)
            $this->sessionTimeout->initTimeout('student');
            $shouldWarn = $this->sessionTimeout->shouldShowWarning();
            
            if (!$shouldWarn) {
                $this->addResult('Fresh session warning', true, 'No warning for fresh session');
                echo "✅ No warning for fresh session\n";
            } else {
                $this->addResult('Fresh session warning', false, 'Unexpected warning');
                echo "❌ Unexpected warning for fresh session\n";
            }
            
            // Simulate near-timeout session
            $_SESSION['last_activity'] = time() - 3400; // 3400 seconds ago (200 seconds remaining)
            $shouldWarn = $this->sessionTimeout->shouldShowWarning();
            
            if ($shouldWarn) {
                $this->addResult('Near-timeout warning', true, 'Warning shown correctly');
                echo "✅ Warning shown for near-timeout session\n";
            } else {
                $this->addResult('Near-timeout warning', false, 'Warning not shown');
                echo "❌ Warning not shown for near-timeout session\n";
            }
            
        } catch (Exception $e) {
            $this->addResult('Warning system', false, $e->getMessage());
            echo "❌ Warning system test failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testSessionExtension() {
        echo "5. Testing Session Extension:\n";
        echo "----------------------------\n";
        
        try {
            $this->sessionTimeout->initTimeout('student');
            $originalActivity = $_SESSION['last_activity'];
            
            // Wait a moment
            sleep(1);
            
            // Extend session
            $extended = $this->sessionTimeout->extendSession();
            $newActivity = $_SESSION['last_activity'];
            
            if ($extended && $newActivity > $originalActivity) {
                $this->addResult('Session extension', true, 'Session extended successfully');
                echo "✅ Session extended successfully\n";
                echo "   Original: $originalActivity, New: $newActivity\n";
            } else {
                $this->addResult('Session extension', false, 'Extension failed');
                echo "❌ Session extension failed\n";
            }
            
        } catch (Exception $e) {
            $this->addResult('Session extension', false, $e->getMessage());
            echo "❌ Session extension test failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testConfiguration() {
        echo "6. Testing Configuration:\n";
        echo "------------------------\n";
        
        try {
            $this->sessionTimeout->initTimeout('admin');
            $config = $this->sessionTimeout->getConfig();
            
            $requiredKeys = ['timeout_duration', 'warning_time', 'time_remaining', 'last_activity'];
            $allKeysPresent = true;
            
            foreach ($requiredKeys as $key) {
                if (!isset($config[$key])) {
                    $allKeysPresent = false;
                    echo "❌ Missing config key: $key\n";
                }
            }
            
            if ($allKeysPresent) {
                $this->addResult('Configuration completeness', true, 'All keys present');
                echo "✅ All configuration keys present\n";
                echo "   Timeout: {$config['timeout_duration']}s\n";
                echo "   Warning: {$config['warning_time']}s\n";
                echo "   Remaining: {$config['time_remaining']}s\n";
            } else {
                $this->addResult('Configuration completeness', false, 'Missing keys');
            }
            
        } catch (Exception $e) {
            $this->addResult('Configuration test', false, $e->getMessage());
            echo "❌ Configuration test failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testTimeoutSimulation() {
        echo "7. Testing Timeout Simulation:\n";
        echo "------------------------------\n";
        
        try {
            // Simulate expired session
            $this->sessionTimeout->initTimeout('admin');
            $_SESSION['last_activity'] = time() - 2000; // 2000 seconds ago (expired)
            
            $isValid = $this->sessionTimeout->isValid();
            
            if (!$isValid) {
                $this->addResult('Timeout simulation', true, 'Expired session detected');
                echo "✅ Expired session correctly detected as invalid\n";
            } else {
                $this->addResult('Timeout simulation', false, 'Expired session still valid');
                echo "❌ Expired session incorrectly detected as valid\n";
            }
            
            // Test cleanup
            $this->sessionTimeout->cleanup();
            if (isset($_SESSION['timed_out'])) {
                $this->addResult('Timeout cleanup', true, 'Cleanup executed');
                echo "✅ Timeout cleanup executed\n";
            } else {
                $this->addResult('Timeout cleanup', false, 'Cleanup not executed');
                echo "❌ Timeout cleanup not executed\n";
            }
            
        } catch (Exception $e) {
            $this->addResult('Timeout simulation', false, $e->getMessage());
            echo "❌ Timeout simulation failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function addResult($test, $passed, $details) {
        $this->testResults[] = [
            'test' => $test,
            'passed' => $passed,
            'details' => $details
        ];
    }
    
    private function displayResults() {
        echo "📊 Test Results Summary:\n";
        echo "=======================\n";
        
        $totalTests = count($this->testResults);
        $passedTests = array_filter($this->testResults, function($result) {
            return $result['passed'];
        });
        $passedCount = count($passedTests);
        
        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedCount\n";
        echo "Failed: " . ($totalTests - $passedCount) . "\n";
        echo "Success Rate: " . round(($passedCount / $totalTests) * 100, 1) . "%\n\n";
        
        if ($passedCount == $totalTests) {
            echo "🎉 ALL TESTS PASSED!\n";
            echo "✅ Session timeout is working correctly\n";
        } else {
            echo "⚠️ Some tests failed. Check the details above.\n";
            
            echo "\nFailed Tests:\n";
            echo "------------\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "❌ {$result['test']}: {$result['details']}\n";
                }
            }
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
    }
    
    private function showManualTestingInstructions() {
        echo "🌐 Manual Web Testing Instructions:\n";
        echo "==================================\n";
        echo "1. Open your ISKOLar website in browser\n";
        echo "2. Open Developer Tools (F12) → Console tab\n";
        echo "3. Login with any account\n";
        echo "4. Look for 'sessionTimeoutConfig' in console\n";
        echo "5. Move mouse/click to test activity tracking\n";
        echo "6. Wait for timeout or modify durations for quick test\n\n";
        
        echo "⚡ Quick Testing Setup:\n";
        echo "======================\n";
        echo "For faster testing, temporarily modify SessionTimeout.php:\n";
        echo "- Change 'student' => 120 (2 minutes)\n";
        echo "- Change 'admin' => 60 (1 minute)\n";
        echo "- Change warningTime = 30 (30 seconds)\n";
        echo "- Test and restore original values\n\n";
        
        echo "🔍 What to Verify:\n";
        echo "==================\n";
        echo "✅ Warning modal appears before timeout\n";
        echo "✅ 'Stay Logged In' button extends session\n";
        echo "✅ 'Logout Now' button logs out immediately\n";
        echo "✅ Automatic logout after timeout expires\n";
        echo "✅ Session extends with mouse/keyboard activity\n";
        echo "✅ Different roles have different timeout durations\n";
        
        echo "\n🎯 Expected Behavior:\n";
        echo "====================\n";
        echo "- Student: 1 hour timeout, 5min warning\n";
        echo "- Provider: 2 hour timeout, 5min warning\n";
        echo "- Admin: 30 minute timeout, 5min warning\n";
        echo "- Activity tracking: mouse, keyboard, scroll, touch\n";
        echo "- Automatic session extension on activity\n";
        echo "- Graceful logout with timeout message\n";
    }
}

// Run the comprehensive test
$tester = new SessionTimeoutTester();
$tester->runAllTests();
?>