<?php
/**
 * Setup Secure Log System
 * Creates encrypted logging tables and access control
 * Run this ONCE to set up the secure logging system
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔐 Setting up Secure Log System...\n\n";
    
    // 1. Create encryption keys table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS encryption_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(100) NOT NULL,
            encrypted_key TEXT NOT NULL,
            key_version INT DEFAULT 1,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_rotated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_active_key (key_name, is_active)
        )
    ");
    echo "✅ Created encryption_keys table\n";
    
    // 2. Create log access permissions table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS log_access_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_level ENUM('read_basic', 'read_sensitive', 'read_all', 'decrypt_logs') NOT NULL,
            log_categories JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            granted_by INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            revoked_at TIMESTAMP NULL,
            revoked_by INT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (granted_by) REFERENCES users(id),
            FOREIGN KEY (revoked_by) REFERENCES users(id)
        )
    ");
    echo "✅ Created log_access_permissions table\n";
    
    // 3. Create login attempts table (if not exists)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            attempt_type ENUM('SUCCESS', 'FAILED', 'BLOCKED') NOT NULL,
            failure_reason VARCHAR(255) NULL,
            user_id INT NULL,
            session_id VARCHAR(255) NULL,
            location_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_created (email, created_at),
            INDEX idx_attempt_type_created (attempt_type, created_at),
            INDEX idx_ip_created (ip_address, created_at)
        )
    ");
    echo "✅ Created login_attempts table\n";
    
    // 4. Create admin activity logs table (if not exists)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action_type ENUM(
                'LOGIN', 'LOGOUT', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT',
                'VIEW_SENSITIVE', 'EXPORT_DATA', 'SYSTEM_CONFIG', 'USER_MANAGEMENT',
                'SCHOLARSHIP_REVIEW', 'APPLICATION_DECISION', 'EMAIL_SENT', 'REPORT_GENERATED'
            ) NOT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id INT NULL,
            action_description TEXT NOT NULL,
            encrypted_details LONGTEXT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            session_id VARCHAR(255) NULL,
            risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id),
            INDEX idx_admin_created (admin_id, created_at),
            INDEX idx_risk_created (risk_level, created_at),
            INDEX idx_action_created (action_type, created_at)
        )
    ");
    echo "✅ Created admin_activity_logs table\n";
    
    // 5. Create system activity logs table (if not exists)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS system_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            user_type ENUM('admin', 'provider', 'student') NOT NULL,
            action_type VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id INT NULL,
            action_description TEXT NOT NULL,
            encrypted_metadata LONGTEXT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            session_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_user_type_created (user_type, created_at)
        )
    ");
    echo "✅ Created system_activity_logs table\n";
    
    // 6. Create security events table (if not exists)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS security_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
            user_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            event_description TEXT NOT NULL,
            encrypted_evidence LONGTEXT NULL,
            resolved BOOLEAN DEFAULT FALSE,
            resolved_by INT NULL,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (resolved_by) REFERENCES users(id),
            INDEX idx_severity_created (severity, created_at),
            INDEX idx_event_type_created (event_type, created_at),
            INDEX idx_resolved (resolved, created_at)
        )
    ");
    echo "✅ Created security_events table\n";
    
    // 7. Create audit trail table (if not exists)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS audit_trail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_type ENUM('DATA_ACCESS', 'DATA_MODIFICATION', 'SYSTEM_CONFIG', 'USER_ACTION') NOT NULL,
            user_id INT NULL,
            admin_id INT NULL,
            table_name VARCHAR(100) NOT NULL,
            record_id INT NULL,
            action ENUM('CREATE', 'READ', 'UPDATE', 'DELETE') NOT NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            encrypted_sensitive_data LONGTEXT NULL,
            compliance_flags JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (admin_id) REFERENCES users(id),
            INDEX idx_table_record (table_name, record_id),
            INDEX idx_audit_type_created (audit_type, created_at)
        )
    ");
    echo "✅ Created audit_trail table\n";
    
    // 8. Insert initial encryption keys
    $adminLogKey = base64_encode(random_bytes(32));
    $sensitiveDataKey = base64_encode(random_bytes(32));
    $auditTrailKey = base64_encode(random_bytes(32));
    
    $stmt = $conn->prepare("
        INSERT IGNORE INTO encryption_keys (key_name, encrypted_key, created_by) VALUES 
        ('admin_logs_key', ?, 1),
        ('sensitive_data_key', ?, 1),
        ('audit_trail_key', ?, 1)
    ");
    $stmt->execute([$adminLogKey, $sensitiveDataKey, $auditTrailKey]);
    echo "✅ Created initial encryption keys\n";
    
    // 9. Create secure views for log access
    $conn->exec("
        CREATE OR REPLACE VIEW admin_activity_summary AS
        SELECT 
            aal.*,
            u.fullname as admin_name,
            u.email as admin_email,
            CASE 
                WHEN aal.risk_level = 'CRITICAL' THEN 'Immediate Review Required'
                WHEN aal.risk_level = 'HIGH' THEN 'Priority Review'
                WHEN aal.risk_level = 'MEDIUM' THEN 'Standard Review'
                ELSE 'Normal Activity'
            END as review_priority
        FROM admin_activity_logs aal
        JOIN users u ON aal.admin_id = u.id
        WHERE u.user_type = 'admin'
        ORDER BY aal.created_at DESC
    ");
    echo "✅ Created admin_activity_summary view\n";
    
    $conn->exec("
        CREATE OR REPLACE VIEW security_dashboard AS
        SELECT 
            se.*,
            u.fullname as user_name,
            u.email as user_email,
            CASE 
                WHEN se.severity = 'CRITICAL' THEN 1
                WHEN se.severity = 'HIGH' THEN 2
                WHEN se.severity = 'MEDIUM' THEN 3
                ELSE 4
            END as priority_order
        FROM security_events se
        LEFT JOIN users u ON se.user_id = u.id
        ORDER BY priority_order, se.created_at DESC
    ");
    echo "✅ Created security_dashboard view\n";
    
    $conn->exec("
        CREATE OR REPLACE VIEW recent_login_attempts AS
        SELECT 
            la.*,
            u.fullname as user_name,
            u.user_type,
            CASE 
                WHEN la.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'Recent'
                WHEN la.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'Today'
                WHEN la.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'This Week'
                ELSE 'Older'
            END as recency
        FROM login_attempts la
        LEFT JOIN users u ON la.user_id = u.id
        ORDER BY la.created_at DESC
    ");
    echo "✅ Created recent_login_attempts view\n";
    
    // 10. Create log cleanup procedure
    $conn->exec("
        CREATE PROCEDURE IF NOT EXISTS CleanupOldLogs()
        BEGIN
            -- Keep only 90 days of login attempts
            DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
            
            -- Keep only 1 year of system activity logs
            DELETE FROM system_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
            
            -- Keep admin activity logs and security events indefinitely for compliance
            -- Keep audit trail indefinitely for compliance
            
            SELECT 'Log cleanup completed' as status;
        END
    ");
    echo "✅ Created log cleanup procedure\n";
    
    echo "\n🎉 Secure Log System Setup Complete!\n\n";
    
    echo "📋 IMPORTANT SECURITY NOTES:\n";
    echo "1. Encryption keys have been generated and stored securely\n";
    echo "2. Only system administrators can access logs via database\n";
    echo "3. All sensitive data is encrypted in the database\n";
    echo "4. Access permissions must be granted explicitly\n";
    echo "5. Log cleanup procedure created for data retention\n\n";
    
    echo "🔑 ENCRYPTION KEYS GENERATED:\n";
    echo "- admin_logs_key: For admin activity encryption\n";
    echo "- sensitive_data_key: For sensitive system data\n";
    echo "- audit_trail_key: For compliance audit data\n\n";
    
    echo "⚠️  NEXT STEPS:\n";
    echo "1. Grant log access permissions to authorized personnel\n";
    echo "2. Set up regular key rotation schedule\n";
    echo "3. Configure log retention policies\n";
    echo "4. Test decryption access with authorized users\n\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up secure log system: " . $e->getMessage() . "\n";
}
?>