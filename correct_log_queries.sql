-- ✅ CORRECT LOG QUERIES FOR ISKOLar SYSTEM
-- ==========================================

-- 1. LOGIN ATTEMPTS (last 50)
-- Table name: login_attempts (NOT login_attempt_logs)
SELECT 
    id,
    email,
    ip_address,
    attempt_type as status,
    created_at as attempt_time
FROM login_attempts
ORDER BY created_at DESC
LIMIT 50;

-- 2. ADMIN ACTIVITIES (last 50)
-- Table name: admin_activity_logs
SELECT 
    id,
    admin_id,
    action_type,
    risk_level,
    created_at
FROM admin_activity_logs
ORDER BY created_at DESC
LIMIT 50;

-- 3. SECURITY EVENTS (last 50)
SELECT 
    id,
    event_type,
    severity,
    event_description,
    ip_address,
    created_at
FROM security_events
ORDER BY created_at DESC
LIMIT 50;

-- 4. SYSTEM ACTIVITIES (last 50)
SELECT 
    id,
    user_type,
    action_type,
    action_description,
    ip_address,
    created_at
FROM system_activity_logs
ORDER BY created_at DESC
LIMIT 50;

-- 5. AUDIT TRAIL (last 50)
SELECT 
    id,
    audit_type,
    table_name,
    action,
    created_at
FROM audit_trail
ORDER BY created_at DESC
LIMIT 50;

-- ==========================================
-- SECURE VIEWS (Non-sensitive data only)
-- ==========================================

-- Admin Activity Summary (safe to view)
SELECT * FROM admin_activity_summary 
ORDER BY created_at DESC 
LIMIT 50;

-- Security Dashboard (overview)
SELECT * FROM security_dashboard 
ORDER BY priority_order, created_at DESC 
LIMIT 50;

-- Recent Login Attempts (categorized)
SELECT * FROM recent_login_attempts 
WHERE recency IN ('Recent', 'Today')
ORDER BY created_at DESC 
LIMIT 50;

-- ==========================================
-- FILTERED QUERIES FOR SPECIFIC ANALYSIS
-- ==========================================

-- Failed login attempts only
SELECT 
    email,
    ip_address,
    failure_reason,
    created_at
FROM login_attempts 
WHERE attempt_type = 'FAILED'
ORDER BY created_at DESC
LIMIT 50;

-- High-risk admin activities
SELECT 
    aal.*,
    u.fullname as admin_name
FROM admin_activity_logs aal
JOIN users u ON aal.admin_id = u.id
WHERE aal.risk_level IN ('HIGH', 'CRITICAL')
ORDER BY aal.created_at DESC
LIMIT 50;

-- Critical security events
SELECT * FROM security_events 
WHERE severity = 'CRITICAL'
ORDER BY created_at DESC
LIMIT 50;

-- ==========================================
-- SUMMARY STATISTICS
-- ==========================================

-- Login attempt statistics
SELECT 
    attempt_type,
    COUNT(*) as count,
    DATE(created_at) as date
FROM login_attempts 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY attempt_type, DATE(created_at)
ORDER BY date DESC, attempt_type;

-- Admin activity by risk level
SELECT 
    risk_level,
    COUNT(*) as count
FROM admin_activity_logs 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY risk_level
ORDER BY 
    CASE risk_level 
        WHEN 'CRITICAL' THEN 1 
        WHEN 'HIGH' THEN 2 
        WHEN 'MEDIUM' THEN 3 
        ELSE 4 
    END;

-- Security events by severity
SELECT 
    severity,
    COUNT(*) as count
FROM security_events 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY severity
ORDER BY 
    CASE severity 
        WHEN 'CRITICAL' THEN 1 
        WHEN 'HIGH' THEN 2 
        WHEN 'MEDIUM' THEN 3 
        ELSE 4 
    END;