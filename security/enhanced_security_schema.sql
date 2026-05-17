-- ============================================
-- ISKOLar Enhanced Security Schema
-- Additional security tables (preserving existing schema)
-- ============================================

USE ISKOLAR_101;

-- ============================================
-- 1. LOGIN ATTEMPTS LOGGING TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    attempt_type ENUM('success', 'failure', 'blocked') NOT NULL,
    failure_reason VARCHAR(255),
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_time (email, created_at),
    INDEX idx_ip_time (ip_address, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 2. SECURITY AUDIT LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS security_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_action (user_id, action_type),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 3. SESSION MANAGEMENT TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 4. DATA CLASSIFICATION TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS data_classification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    classification ENUM('PUBLIC', 'SENSITIVE', 'CONFIDENTIAL') NOT NULL,
    encryption_required TINYINT(1) DEFAULT 0,
    access_roles JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_table_column (table_name, column_name)
) ENGINE=InnoDB;

-- ============================================
-- 5. ENCRYPTED STUDENT DATA TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS encrypted_student_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    
    -- Encrypted sensitive fields
    encrypted_gwa VARBINARY(255),
    encrypted_family_income VARBINARY(255),
    encrypted_father_income VARBINARY(255),
    encrypted_mother_income VARBINARY(255),
    encrypted_mobile_number VARBINARY(255),
    encrypted_birthdate VARBINARY(255),
    
    -- Encryption metadata
    encryption_key_id VARCHAR(50),
    last_encrypted TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 6. ADMIN PASSWORD ROTATION TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS admin_password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- INSERT DATA CLASSIFICATION RULES
-- ============================================
INSERT INTO data_classification (table_name, column_name, classification, encryption_required, access_roles) VALUES
-- Student sensitive data
('student_profiles', 'gwa', 'CONFIDENTIAL', 1, '["admin", "provider"]'),
('student_profiles', 'family_monthly_income', 'CONFIDENTIAL', 1, '["admin"]'),
('student_profiles', 'father_income', 'CONFIDENTIAL', 1, '["admin"]'),
('student_profiles', 'mother_income', 'CONFIDENTIAL', 1, '["admin"]'),
('student_profiles', 'mobile_number', 'SENSITIVE', 1, '["student", "admin"]'),
('student_profiles', 'birthdate', 'SENSITIVE', 1, '["student", "admin"]'),

-- User data
('users', 'email', 'SENSITIVE', 0, '["student", "provider", "admin"]'),
('users', 'password', 'CONFIDENTIAL', 1, '["admin"]'),

-- Public data
('scholarships', 'title', 'PUBLIC', 0, '["student", "provider", "admin"]'),
('scholarships', 'description', 'PUBLIC', 0, '["student", "provider", "admin"]'),
('schools', 'school_name', 'PUBLIC', 0, '["student", "provider", "admin"]');

-- ============================================
-- SECURITY TRIGGERS
-- ============================================

DELIMITER //

-- Trigger to log sensitive data access
CREATE TRIGGER tr_log_student_profile_access
AFTER SELECT ON student_profiles
FOR EACH ROW
BEGIN
    -- This would be implemented in application layer
    -- Trigger syntax doesn't support SELECT logging directly
END //

-- Trigger to enforce admin password rotation
CREATE TRIGGER tr_check_admin_password_expiry
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.user_type = 'admin' AND OLD.password != NEW.password THEN
        INSERT INTO admin_password_history (user_id, password_hash, expires_at)
        VALUES (NEW.id, NEW.password, DATE_ADD(NOW(), INTERVAL 6 MONTH));
    END IF;
END //

DELIMITER ;

-- ============================================
-- SECURITY VIEWS
-- ============================================

-- View for login attempt analysis
CREATE OR REPLACE VIEW v_login_security_summary AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_attempts,
    SUM(CASE WHEN attempt_type = 'success' THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN attempt_type = 'failure' THEN 1 ELSE 0 END) as failed_attempts,
    SUM(CASE WHEN attempt_type = 'blocked' THEN 1 ELSE 0 END) as blocked_attempts,
    COUNT(DISTINCT email) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips
FROM login_attempts
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- View for admin activity monitoring
CREATE OR REPLACE VIEW v_admin_activity_summary AS
SELECT 
    u.fullname as admin_name,
    u.email as admin_email,
    COUNT(sal.id) as total_actions,
    COUNT(CASE WHEN sal.risk_level = 'HIGH' THEN 1 END) as high_risk_actions,
    COUNT(CASE WHEN sal.risk_level = 'CRITICAL' THEN 1 END) as critical_actions,
    MAX(sal.created_at) as last_activity
FROM users u
LEFT JOIN security_audit_logs sal ON u.id = sal.user_id
WHERE u.user_type = 'admin'
GROUP BY u.id, u.fullname, u.email;

-- ============================================
-- STORED PROCEDURES FOR SECURITY
-- ============================================

DELIMITER //

-- Procedure to check login rate limiting
CREATE PROCEDURE sp_check_login_rate_limit(
    IN p_email VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    OUT p_is_blocked BOOLEAN,
    OUT p_attempts_count INT
)
BEGIN
    DECLARE failed_attempts INT DEFAULT 0;
    
    -- Count failed attempts in last 15 minutes
    SELECT COUNT(*) INTO failed_attempts
    FROM login_attempts
    WHERE (email = p_email OR ip_address = p_ip_address)
    AND attempt_type = 'failure'
    AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE);
    
    SET p_attempts_count = failed_attempts;
    SET p_is_blocked = (failed_attempts >= 5);
END //

-- Procedure to log security events
CREATE PROCEDURE sp_log_security_event(
    IN p_user_id INT,
    IN p_action_type VARCHAR(100),
    IN p_resource_type VARCHAR(50),
    IN p_resource_id INT,
    IN p_old_values JSON,
    IN p_new_values JSON,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL')
)
BEGIN
    INSERT INTO security_audit_logs (
        user_id, action_type, resource_type, resource_id,
        old_values, new_values, ip_address, user_agent, risk_level
    ) VALUES (
        p_user_id, p_action_type, p_resource_type, p_resource_id,
        p_old_values, p_new_values, p_ip_address, p_user_agent, p_risk_level
    );
END //

DELIMITER ;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_login_attempts_email_time ON login_attempts(email, created_at);
CREATE INDEX idx_audit_logs_user_time ON security_audit_logs(user_id, created_at);
CREATE INDEX idx_sessions_user_active ON user_sessions(user_id, is_active);
CREATE INDEX idx_encrypted_data_user ON encrypted_student_data(user_id);