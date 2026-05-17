<?php
/**
 * Setup Script for Comprehensive Logging and Monitoring System
 * ISKOLar - Activity Tracking and Security Monitoring
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "<h2>ISKOLar Logging and Monitoring System Setup</h2>";
    echo "<p>Setting up comprehensive activity tracking and security monitoring...</p>";
    
    // Read and execute the schema
    $schemaFile = 'database/logging_monitoring_schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $schema = file_get_contents($schemaFile);
    
    // Split the schema into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "<h3>Executing Database Schema...</h3>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    
    foreach ($statements as $statement) {
        try {
            $conn->exec($statement);
            $successCount++;
            
            // Extract table/view/procedure name for logging
            if (preg_match('/CREATE\s+(TABLE|VIEW|PROCEDURE)\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Created {$matches[1]}: {$matches[2]}<br>";
            } elseif (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Inserted data into: {$matches[1]}<br>";
            } elseif (preg_match('/CREATE\s+INDEX\s+`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Created index: {$matches[1]}<br>";
            } else {
                echo "✅ Executed statement<br>";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "</div>";
    
    echo "<h3>Setup Summary</h3>";
    echo "<ul>";
    echo "<li>✅ Successful operations: $successCount</li>";
    echo "<li>❌ Failed operations: $errorCount</li>";
    echo "</ul>";
    
    if ($errorCount === 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>🎉 Setup Completed Successfully!</h4>";
        echo "<p>The comprehensive logging and monitoring system has been installed.</p>";
        echo "</div>";
        
        // Test the logging system
        echo "<h3>Testing Logging System...</h3>";
        
        require_once 'app/core/ActivityLogger.php';
        $logger = new ActivityLogger();
        
        // Test system activity log
        $testResult1 = $logger->logSystemActivity(
            1, 'admin', 'SYSTEM_CONFIG', 'logging_system', null,
            'Logging system setup completed successfully'
        );
        
        // Test admin activity log
        $testResult2 = $logger->logAdminActivity(
            1, 'SYSTEM_CONFIG', 'logging_system', null,
            'Initialized comprehensive logging and monitoring system',
            ['setup_time' => date('Y-m-d H:i:s'), 'version' => '1.0'],
            'MEDIUM'
        );
        
        if ($testResult1 && $testResult2) {
            echo "✅ Logging system test: PASSED<br>";
        } else {
            echo "❌ Logging system test: FAILED<br>";
        }
        
        // Create initial admin permissions
        echo "<h3>Setting Up Initial Permissions...</h3>";
        
        try {
            // Grant comprehensive log access to admin user ID 1
            $stmt = $conn->prepare("
                INSERT IGNORE INTO log_access_permissions (user_id, permission_level, log_categories, granted_by) 
                VALUES (1, 'decrypt_logs', ?, 1)
            ");
            $stmt->execute([json_encode(['admin_activity', 'security_events', 'audit_trail', 'system_activity'])]);
            
            $stmt = $conn->prepare("
                INSERT IGNORE INTO log_access_permissions (user_id, permission_level, log_categories, granted_by) 
                VALUES (1, 'read_all', ?, 1)
            ");
            $stmt->execute([json_encode(['login_attempts', 'system_activity', 'admin_activity', 'security_events', 'audit_trail'])]);
            
            echo "✅ Admin permissions configured<br>";
            
        } catch (Exception $e) {
            echo "❌ Permission setup error: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>⚠️ Setup Completed with Errors</h4>";
        echo "<p>Some operations failed. Please check the error messages above and resolve any issues.</p>";
        echo "</div>";
    }
    
    echo "<h3>📋 System Features Installed:</h3>";
    echo "<ul>";
    echo "<li>🔐 <strong>Login Attempt Tracking</strong> - Monitors all login attempts with IP and location data</li>";
    echo "<li>👨‍💼 <strong>Admin Activity Logs</strong> - Encrypted logs of all administrative actions</li>";
    echo "<li>📊 <strong>System Activity Logs</strong> - General user activity tracking</li>";
    echo "<li>🚨 <strong>Security Events</strong> - Automated detection of suspicious activities</li>";
    echo "<li>📋 <strong>Audit Trail</strong> - Comprehensive compliance and regulatory logging</li>";
    echo "<li>🔑 <strong>Access Control</strong> - Role-based log access permissions</li>";
    echo "<li>🔒 <strong>Encryption</strong> - Secure encryption for sensitive log data</li>";
    echo "</ul>";
    
    echo "<h3>🛡️ Security Features:</h3>";
    echo "<ul>";
    echo "<li>✅ Encrypted sensitive data in logs</li>";
    echo "<li>✅ Role-based access control for log viewing</li>";
    echo "<li>✅ Automatic suspicious activity detection</li>";
    echo "<li>✅ Comprehensive audit trails for compliance</li>";
    echo "<li>✅ Secure key management system</li>";
    echo "<li>✅ Automatic log retention and cleanup</li>";
    echo "</ul>";
    
    echo "<h3>📈 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Configure additional admin users with log access permissions</li>";
    echo "<li>Set up automated log cleanup schedules</li>";
    echo "<li>Configure security alert notifications</li>";
    echo "<li>Review and customize log retention policies</li>";
    echo "<li>Test the security dashboard functionality</li>";
    echo "</ol>";
    
    echo "<div style='background: #cce5ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📚 Documentation:</h4>";
    echo "<p>The logging system is now active and will automatically track:</p>";
    echo "<ul>";
    echo "<li>All login attempts (successful and failed)</li>";
    echo "<li>Administrative actions with risk levels</li>";
    echo "<li>Student and provider activities</li>";
    echo "<li>Security events and suspicious activities</li>";
    echo "<li>Data changes for compliance</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>❌ Setup Failed</h4>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>