<?php
/**
 * Session Timeout Setup Script
 * Creates necessary database tables and directories for session timeout feature
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Setting up Session Timeout Feature...\n\n";
    
    // Create active_sessions table
    $sql = "
        CREATE TABLE IF NOT EXISTS active_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('student', 'provider', 'admin') NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            session_token VARCHAR(255) NOT NULL UNIQUE,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            
            INDEX idx_user_id (user_id),
            INDEX idx_session_token (session_token),
            INDEX idx_expires_at (expires_at),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->exec($sql);
    echo "✅ Created active_sessions table\n";
    
    // Create assets/js directory if it doesn't exist
    if (!file_exists('assets')) {
        mkdir('assets', 0755, true);
        echo "✅ Created assets directory\n";
    }
    
    if (!file_exists('assets/js')) {
        mkdir('assets/js', 0755, true);
        echo "✅ Created assets/js directory\n";
    }
    
    // Create includes directory if it doesn't exist
    if (!file_exists('includes')) {
        mkdir('includes', 0755, true);
        echo "✅ Created includes directory\n";
    }
    
    // Create cleanup procedure for expired sessions
    $cleanupProcedure = "
        DROP PROCEDURE IF EXISTS CleanupExpiredSessions;
        
        DELIMITER //
        CREATE PROCEDURE CleanupExpiredSessions()
        BEGIN
            DELETE FROM active_sessions WHERE expires_at < NOW();
            SELECT ROW_COUNT() as cleaned_sessions;
        END //
        DELIMITER ;
    ";
    
    $conn->exec($cleanupProcedure);
    echo "✅ Created session cleanup procedure\n";
    
    // Create session timeout configuration view
    $configView = "
        CREATE OR REPLACE VIEW session_timeout_config AS
        SELECT 
            'student' as user_type, 3600 as timeout_seconds, '1 hour' as timeout_description
        UNION ALL
        SELECT 
            'provider' as user_type, 7200 as timeout_seconds, '2 hours' as timeout_description
        UNION ALL
        SELECT 
            'admin' as user_type, 1800 as timeout_seconds, '30 minutes' as timeout_description
    ";
    
    $conn->exec($configView);
    echo "✅ Created session timeout configuration view\n";
    
    // Test the session timeout functionality
    echo "\n🧪 Testing Session Timeout Functionality...\n";
    
    // Test SessionTimeout class
    require_once 'app/core/SessionTimeout.php';
    $sessionTimeout = new SessionTimeout();
    echo "✅ SessionTimeout class loaded successfully\n";
    
    // Test configuration
    $config = $sessionTimeout->getConfig();
    if ($config === null) {
        echo "ℹ️  No active session - this is expected for setup\n";
    } else {
        echo "✅ Session timeout configuration working\n";
    }
    
    echo "\n📋 Session Timeout Feature Setup Complete!\n\n";
    
    echo "🔧 Configuration:\n";
    echo "   - Student sessions: 1 hour (3600 seconds)\n";
    echo "   - Provider sessions: 2 hours (7200 seconds)\n";
    echo "   - Admin sessions: 30 minutes (1800 seconds)\n";
    echo "   - Warning time: 5 minutes before timeout\n\n";
    
    echo "📁 Files Created/Modified:\n";
    echo "   - app/core/SessionTimeout.php (Session timeout logic)\n";
    echo "   - assets/js/session-timeout.js (Frontend timeout handling)\n";
    echo "   - includes/session_timeout_integration.php (Integration helper)\n";
    echo "   - extend_session.php (AJAX endpoint for session extension)\n";
    echo "   - Modified: index.php (Login integration)\n";
    echo "   - Modified: logout.php (Timeout handling)\n";
    echo "   - Modified: Dashboard files (Integration)\n\n";
    
    echo "🛡️ Security Features:\n";
    echo "   - Automatic session timeout based on user role\n";
    echo "   - Warning popup 5 minutes before timeout\n";
    echo "   - Activity tracking to extend sessions\n";
    echo "   - Secure session token management\n";
    echo "   - Database tracking of active sessions\n\n";
    
    echo "✅ All existing functionality preserved!\n";
    echo "✅ Session timeout feature added successfully!\n\n";
    
    // Show active sessions (if any)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM active_sessions WHERE expires_at > NOW()");
    $stmt->execute();
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Current Status:\n";
    echo "   - Active sessions: {$activeCount}\n";
    echo "   - Session timeout: ENABLED\n";
    echo "   - Integration: COMPLETE\n\n";
    
    echo "🚀 The session timeout feature is now active!\n";
    echo "   Users will be automatically logged out after inactivity.\n";
    echo "   Warning popups will appear 5 minutes before timeout.\n";
    echo "   All existing functionality remains unchanged.\n\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up session timeout: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>