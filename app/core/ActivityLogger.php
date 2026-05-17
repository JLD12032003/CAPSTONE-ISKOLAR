<?php
/**
 * ActivityLogger Class
 * Comprehensive logging system for ISKOLar
 * Handles login attempts, admin activities, system activities, and security events
 */

require_once __DIR__ . '/LogEncryption.php';

class ActivityLogger {
    private $conn;
    private $encryption;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->conn = (new Database())->connect();
        $this->encryption = new LogEncryption();
        
        // Set session variables for triggers
        $this->setSessionVariables();
    }
    
    /**
     * Set session variables for database triggers
     */
    private function setSessionVariables() {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'System';
            
            $this->conn->exec("SET @current_ip = '$ip'");
            $this->conn->exec("SET @current_user_agent = '$userAgent'");
        } catch (Exception $e) {
            error_log("Failed to set session variables: " . $e->getMessage());
        }
    }
    
    /**
     * Log login attempts (success, failure, blocked)
     */
    public function logLoginAttempt($email, $attemptType, $userId = null, $failureReason = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO login_attempts (
                    email, ip_address, user_agent, attempt_type, failure_reason,
                    user_id, session_id, location_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $locationData = $this->getLocationData();
            
            $stmt->execute([
                $email,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $attemptType,
                $failureReason,
                $userId,
                session_id(),
                json_encode($locationData)
            ]);
            
            // Check for suspicious activity after failed attempts
            if ($attemptType === 'FAILED') {
                $this->checkSuspiciousLoginActivity($email);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Login attempt logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log admin activities with encryption for sensitive data
     */
    public function logAdminActivity($adminId, $actionType, $entityType = null, $entityId = null, 
                                   $description, $sensitiveDetails = null, $riskLevel = 'LOW') {
        try {
            $encryptedDetails = null;
            if ($sensitiveDetails) {
                $encryptedDetails = $this->encryption->encryptLogData($sensitiveDetails, 'admin_logs_key');
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO admin_activity_logs (
                    admin_id, action_type, entity_type, entity_id, action_description,
                    encrypted_details, ip_address, user_agent, session_id, risk_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $adminId,
                $actionType,
                $entityType,
                $entityId,
                $description,
                $encryptedDetails,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'System',
                session_id(),
                $riskLevel
            ]);
            
            // Create security event for high-risk activities
            if (in_array($riskLevel, ['HIGH', 'CRITICAL'])) {
                $this->createSecurityEvent('UNUSUAL_ACTIVITY', $riskLevel, $adminId, 
                    "High-risk admin activity: $description");
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Admin activity logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log general system activities
     */
    public function logSystemActivity($userId, $userType, $actionType, $entityType = null, 
                                    $entityId = null, $description, $metadata = null) {
        try {
            $encryptedMetadata = null;
            if ($metadata) {
                $encryptedMetadata = $this->encryption->encryptLogData($metadata, 'sensitive_data_key');
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO system_activity_logs (
                    user_id, user_type, action_type, entity_type, entity_id,
                    action_description, encrypted_metadata, ip_address, user_agent, 
                    session_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $userType,
                $actionType,
                $entityType,
                $entityId,
                $description,
                $encryptedMetadata,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'System',
                session_id()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("System activity logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create security events for suspicious activities
     */
    public function createSecurityEvent($eventType, $severity, $userId = null, $description, $evidence = null) {
        try {
            $encryptedEvidence = null;
            if ($evidence) {
                $encryptedEvidence = $this->encryption->encryptLogData($evidence, 'sensitive_data_key');
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO security_events (
                    event_type, severity, user_id, ip_address, user_agent,
                    event_description, encrypted_evidence, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $eventType,
                $severity,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'System',
                $description,
                $encryptedEvidence
            ]);
            
            // Send alert for critical events
            if ($severity === 'CRITICAL') {
                $this->sendSecurityAlert($eventType, $description);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Security event logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log audit trail for compliance
     */
    public function logAuditTrail($auditType, $userId, $adminId, $tableName, $recordId, 
                                 $action, $oldValues = null, $newValues = null, $sensitiveData = null) {
        try {
            $encryptedSensitiveData = null;
            if ($sensitiveData) {
                $encryptedSensitiveData = $this->encryption->encryptLogData($sensitiveData, 'audit_trail_key');
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO audit_trail (
                    audit_type, user_id, admin_id, table_name, record_id, action,
                    old_values, new_values, encrypted_sensitive_data, compliance_flags, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $complianceFlags = json_encode([
                'gdpr_relevant' => $this->isGDPRRelevant($tableName),
                'financial_data' => $this->isFinancialData($tableName),
                'academic_record' => $this->isAcademicRecord($tableName)
            ]);
            
            $stmt->execute([
                $auditType,
                $userId,
                $adminId,
                $tableName,
                $recordId,
                $action,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $encryptedSensitiveData,
                $complianceFlags
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Audit trail logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for suspicious login activity
     */
    private function checkSuspiciousLoginActivity($email) {
        try {
            // Check for multiple failed attempts in the last hour
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as failed_count, ip_address
                FROM login_attempts 
                WHERE email = ? 
                AND attempt_type = 'FAILED' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip_address
                HAVING failed_count >= 5
            ");
            $stmt->execute([$email]);
            $suspiciousIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($suspiciousIPs as $ip) {
                $this->createSecurityEvent(
                    'MULTIPLE_FAILED_LOGINS',
                    'HIGH',
                    null,
                    "Multiple failed login attempts for email: $email from IP: {$ip['ip_address']} ({$ip['failed_count']} attempts)",
                    ['email' => $email, 'ip_address' => $ip['ip_address'], 'failed_count' => $ip['failed_count']]
                );
            }
            
        } catch (Exception $e) {
            error_log("Suspicious activity check error: " . $e->getMessage());
        }
    }
    
    /**
     * Get location data from IP address
     */
    private function getLocationData() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // For localhost/development, return mock data
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return [
                'ip' => $ip,
                'country' => 'Local',
                'city' => 'Development',
                'timezone' => date_default_timezone_get()
            ];
        }
        
        // In production, you could integrate with a geolocation service
        return [
            'ip' => $ip,
            'country' => 'Unknown',
            'city' => 'Unknown',
            'timezone' => date_default_timezone_get()
        ];
    }
    
    /**
     * Send security alerts for critical events
     */
    private function sendSecurityAlert($eventType, $description) {
        // In production, implement email/SMS alerts to security team
        error_log("CRITICAL SECURITY EVENT: $eventType - $description");
    }
    
    /**
     * Helper methods for compliance flags
     */
    private function isGDPRRelevant($tableName) {
        $gdprTables = ['users', 'student_profiles', 'provider_profiles'];
        return in_array($tableName, $gdprTables);
    }
    
    private function isFinancialData($tableName) {
        $financialTables = ['scholarships', 'scholarship_awards', 'scholarship_applications'];
        return in_array($tableName, $financialTables);
    }
    
    private function isAcademicRecord($tableName) {
        $academicTables = ['student_profiles', 'scholarship_applications', 'scholarship_awards'];
        return in_array($tableName, $academicTables);
    }
    
    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities($userId, $userType, $limit = 10) {
        try {
            if ($userType === 'admin') {
                $stmt = $this->conn->prepare("
                    SELECT action_type, action_description, created_at, risk_level
                    FROM admin_activity_logs 
                    WHERE admin_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
            } else {
                $stmt = $this->conn->prepare("
                    SELECT action_type, action_description, created_at
                    FROM system_activity_logs 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
            }
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get recent activities error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security dashboard data (admin only)
     */
    public function getSecurityDashboard($adminId) {
        try {
            // Check if admin has permission
            if (!$this->encryption->canReadLogCategory($adminId, 'security_events')) {
                throw new Exception('Access denied: Insufficient permissions');
            }
            
            $data = [];
            
            // Recent security events
            $stmt = $this->conn->prepare("
                SELECT * FROM security_dashboard 
                WHERE resolved = FALSE 
                ORDER BY 
                    CASE severity 
                        WHEN 'CRITICAL' THEN 1 
                        WHEN 'HIGH' THEN 2 
                        WHEN 'MEDIUM' THEN 3 
                        ELSE 4 
                    END
                LIMIT 20
            ");
            $stmt->execute();
            $data['security_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent failed logins
            $stmt = $this->conn->prepare("
                SELECT * FROM recent_login_attempts 
                WHERE attempt_type = 'FAILED' 
                AND recency IN ('Recent', 'Today')
                LIMIT 10
            ");
            $stmt->execute();
            $data['failed_logins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // High-risk admin activities
            $stmt = $this->conn->prepare("
                SELECT * FROM admin_activity_summary 
                WHERE risk_level IN ('HIGH', 'CRITICAL')
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 10
            ");
            $stmt->execute();
            $data['high_risk_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Security dashboard error: " . $e->getMessage());
            throw $e;
        }
    }
}
?>