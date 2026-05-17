-- Comprehensive Logging and Monitoring System Schema
-- ISKOLar System - Activity Tracking and Security Monitoring

-- =====================================================
-- 1. LOGIN ATTEMPT LOGS TABLE
-- =====================================================
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
    
    INDEX idx_email (email),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempt_type (attempt_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 2. ADMIN ACTIVITY LOGS TABLE
-- =====================================================
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
    
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_entity_type (entity_type),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 3. SYSTEM ACTIVITY LOGS TABLE (General Activities)
-- =====================================================
CREATE TABLE IF NOT EXISTS system_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_type ENUM('student', 'provider', 'admin') NULL,
    action_type ENUM(
        'REGISTRATION', 'PROFILE_UPDATE', 'APPLICATION_SUBMIT', 'APPLICATION_DELETE',
        'SCHOLARSHIP_CREATE', 'SCHOLARSHIP_UPDATE', 'SCHOLARSHIP_DELETE',
        'FILE_UPLOAD', 'FILE_DELETE', 'EMAIL_VERIFICATION', 'PASSWORD_CHANGE',
        'SYSTEM_ACCESS', 'DATA_EXPORT', 'REPORT_VIEW'
    ) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id INT NULL,
    action_description TEXT NOT NULL,
    encrypted_metadata LONGTEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_user_type (user_type),
    INDEX idx_action_type (action_type),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 4. SECURITY EVENTS TABLE (High-Risk Activities)
-- =====================================================
CREATE TABLE IF NOT EXISTS security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM(
        'MULTIPLE_FAILED_LOGINS', 'SUSPICIOUS_IP', 'UNAUTHORIZED_ACCESS',
        'DATA_BREACH_ATTEMPT', 'PRIVILEGE_ESCALATION', 'UNUSUAL_ACTIVITY',
        'SYSTEM_INTRUSION', 'MALICIOUS_REQUEST'
    ) NOT NULL,
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
    
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 5. AUDIT TRAIL TABLE (Compliance & Regulatory)
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_type ENUM('FINANCIAL', 'ACADEMIC', 'PERSONAL_DATA', 'SYSTEM_CONFIG') NOT NULL,
    user_id INT NULL,
    admin_id INT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE', 'SELECT') NOT NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    encrypted_sensitive_data LONGTEXT NULL,
    compliance_flags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_audit_type (audit_type),
    INDEX idx_table_name (table_name),
    INDEX idx_record_id (record_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 6. LOG ACCESS CONTROL TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS log_access_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_level ENUM('READ_basic', 'read_sensitive', 'read_all', 'decrypt_logs') NOT NULL,
    log_categories JSON NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_permission_level (permission_level),
    INDEX idx_is_active (is_active),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_permission (user_id, permission_level)
);

-- =====================================================
-- 7. ENCRYPTION KEYS TABLE (Secure Key Management)
-- =====================================================
CREATE TABLE IF NOT EXISTS encryption_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    encrypted_key LONGTEXT NOT NULL,
    key_version INT DEFAULT 1,
    algorithm VARCHAR(50) DEFAULT 'AES-256-CBC',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_rotated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_key_name (key_name),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- INITIAL DATA SETUP
-- =====================================================

-- Insert default encryption keys (these will be properly encrypted in the application)
INSERT INTO encryption_keys (key_name, encrypted_key, created_by) VALUES
('admin_logs_key', 'PLACEHOLDER_ENCRYPTED_KEY_1', 1),
('sensitive_data_key', 'PLACEHOLDER_ENCRYPTED_KEY_2', 1),
('audit_trail_key', 'PLACEHOLDER_ENCRYPTED_KEY_3', 1);

-- Grant initial log access permissions to system admin
INSERT INTO log_access_permissions (user_id, permission_level, log_categories, granted_by) VALUES
(1, 'decrypt_logs', '["admin_activity", "security_events", "audit_trail"]', 1),
(1, 'read_all', '["login_attempts", "system_activity", "admin_activity", "security_events", "audit_trail"]', 1);

-- =====================================================
-- VIEWS FOR EASY ACCESS
-- =====================================================

-- Recent Login Attempts View
CREATE OR REPLACE VIEW recent_login_attempts AS
SELECT 
    la.*,
    u.fullname,
    u.user_type,
    CASE 
        WHEN la.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'Recent'
        WHEN la.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'Today'
        ELSE 'Older'
    END as recency
FROM login_attempts la
LEFT JOIN users u ON la.user_id = u.id
ORDER BY la.created_at DESC;

-- Admin Activity Summary View
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
ORDER BY aal.created_at DESC;

-- Security Events Dashboard View
CREATE OR REPLACE VIEW security_dashboard AS
SELECT 
    se.*,
    u.fullname as user_name,
    u.email as user_email,
    resolver.fullname as resolved_by_name,
    CASE 
        WHEN se.severity = 'CRITICAL' AND se.resolved = FALSE THEN 'URGENT ACTION REQUIRED'
        WHEN se.severity = 'HIGH' AND se.resolved = FALSE THEN 'HIGH PRIORITY'
        WHEN se.severity = 'MEDIUM' AND se.resolved = FALSE THEN 'MEDIUM PRIORITY'
        WHEN se.resolved = TRUE THEN 'RESOLVED'
        ELSE 'LOW PRIORITY'
    END as action_required
FROM security_events se
LEFT JOIN users u ON se.user_id = u.id
LEFT JOIN users resolver ON se.resolved_by = resolver.id
ORDER BY 
    CASE se.severity 
        WHEN 'CRITICAL' THEN 1 
        WHEN 'HIGH' THEN 2 
        WHEN 'MEDIUM' THEN 3 
        ELSE 4 
    END,
    se.resolved ASC,
    se.created_at DESC;

-- =====================================================
-- STORED PROCEDURES FOR LOG MANAGEMENT
-- =====================================================

DELIMITER //

-- Procedure to clean old logs (data retention)
CREATE PROCEDURE CleanOldLogs(IN retention_days INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Clean login attempts older than retention period
    DELETE FROM login_attempts 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY);
    
    -- Clean system activity logs (keep admin and security logs longer)
    DELETE FROM system_activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY)
    AND action_type NOT IN ('SYSTEM_ACCESS', 'DATA_EXPORT');
    
    -- Archive old audit trail (don't delete, just mark as archived)
    UPDATE audit_trail 
    SET compliance_flags = JSON_SET(COALESCE(compliance_flags, '{}'), '$.archived', TRUE)
    WHERE created_at < DATE_SUB(NOW(), INTERVAL (retention_days * 2) DAY);
    
    COMMIT;
END //

-- Procedure to detect suspicious activity
CREATE PROCEDURE DetectSuspiciousActivity()
BEGIN
    -- Detect multiple failed logins from same IP
    INSERT INTO security_events (event_type, severity, ip_address, user_agent, event_description, encrypted_evidence)
    SELECT 
        'MULTIPLE_FAILED_LOGINS',
        'HIGH',
        ip_address,
        user_agent,
        CONCAT('Multiple failed login attempts detected from IP: ', ip_address, ' (', COUNT(*), ' attempts in last hour)'),
        NULL
    FROM login_attempts 
    WHERE attempt_type = 'FAILED' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY ip_address, user_agent
    HAVING COUNT(*) >= 5
    AND ip_address NOT IN (
        SELECT DISTINCT ip_address FROM security_events 
        WHERE event_type = 'MULTIPLE_FAILED_LOGINS' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    );
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR AUTOMATIC LOGGING
-- =====================================================

-- Trigger for user table changes
DELIMITER //
CREATE TRIGGER user_audit_trigger 
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_trail (
        audit_type, user_id, table_name, record_id, action, 
        old_values, new_values, created_at
    ) VALUES (
        'PERSONAL_DATA',
        NEW.id,
        'users',
        NEW.id,
        'UPDATE',
        JSON_OBJECT(
            'email', OLD.email,
            'fullname', OLD.fullname,
            'user_type', OLD.user_type,
            'is_verified', OLD.is_verified
        ),
        JSON_OBJECT(
            'email', NEW.email,
            'fullname', NEW.fullname,
            'user_type', NEW.user_type,
            'is_verified', NEW.is_verified
        ),
        NOW()
    );
END //
DELIMITER ;

-- Trigger for scholarship applications
DELIMITER //
CREATE TRIGGER scholarship_application_audit_trigger 
AFTER INSERT ON scholarship_applications
FOR EACH ROW
BEGIN
    INSERT INTO system_activity_logs (
        user_id, user_type, action_type, entity_type, entity_id,
        action_description, ip_address, user_agent, created_at
    ) VALUES (
        NEW.student_id,
        'student',
        'APPLICATION_SUBMIT',
        'scholarship_application',
        NEW.id,
        CONCAT('Student submitted application for scholarship ID: ', NEW.scholarship_id),
        COALESCE(@current_ip, '127.0.0.1'),
        COALESCE(@current_user_agent, 'System'),
        NOW()
    );
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER scholarship_application_delete_audit_trigger 
AFTER DELETE ON scholarship_applications
FOR EACH ROW
BEGIN
    INSERT INTO system_activity_logs (
        user_id, user_type, action_type, entity_type, entity_id,
        action_description, ip_address, user_agent, created_at
    ) VALUES (
        OLD.student_id,
        'student',
        'APPLICATION_DELETE',
        'scholarship_application',
        OLD.id,
        CONCAT('Student deleted application for scholarship ID: ', OLD.scholarship_id, ' (Status was: ', OLD.status, ')'),
        COALESCE(@current_ip, '127.0.0.1'),
        COALESCE(@current_user_agent, 'System'),
        NOW()
    );
END //
DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional performance indexes
CREATE INDEX idx_login_attempts_recent ON login_attempts(created_at DESC, attempt_type);
CREATE INDEX idx_admin_logs_risk ON admin_activity_logs(risk_level, created_at DESC);
CREATE INDEX idx_security_events_unresolved ON security_events(resolved, severity, created_at DESC);
CREATE INDEX idx_audit_trail_compliance ON audit_trail(audit_type, created_at DESC);

-- =====================================================
-- COMMENTS AND DOCUMENTATION
-- =====================================================

ALTER TABLE login_attempts COMMENT = 'Tracks all login attempts with detailed information for security monitoring';
ALTER TABLE admin_activity_logs COMMENT = 'Encrypted logs of all administrative actions for compliance and security';
ALTER TABLE system_activity_logs COMMENT = 'General system activity tracking for all user types';
ALTER TABLE security_events COMMENT = 'High-priority security events requiring investigation';
ALTER TABLE audit_trail COMMENT = 'Comprehensive audit trail for regulatory compliance';
ALTER TABLE log_access_permissions COMMENT = 'Controls who can access different types of logs';
ALTER TABLE encryption_keys COMMENT = 'Secure storage of encryption keys for log data';