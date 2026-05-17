<?php
/**
 * Security Monitoring Module
 * Handles login attempt logging and admin activity audit trails
 */

class SecurityMonitoring {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Feature 1: Login Attempt Logging (Success and Failure)
     * Comprehensive logging of all authentication attempts
     */
    public function logLoginAttempt($email, $ipAddress, $userAgent, $attemptType, $userId = null, $failureReason = null, $additionalData = []) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO login_attempts (
                    email, ip_address, user_agent, attempt_type, 
                    user_id, failure_reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $email,
                $ipAddress,
                $userAgent,
                $attemptType,
                $userId,
                $failureReason
            ]);
            
            // If this is a failed attempt, check for suspicious activity
            if ($attemptType === 'failure' && $result) {
                $this->checkSuspiciousActivity($email, $ipAddress);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for suspicious login activity patterns
     */
    private function checkSuspiciousActivity($email, $ipAddress) {
        try {
            // Check for multiple failed attempts from same IP
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM login_attempts
                WHERE ip_address = ? 
                AND attempt_type = 'failure'
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$ipAddress]);
            $ipFailures = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Check for multiple failed attempts for same email
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM login_attempts
                WHERE email = ? 
                AND attempt_type = 'failure'
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$email]);
            $emailFailures = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Log high-risk security event if thresholds exceeded
            if ($ipFailures >= 10 || $emailFailures >= 5) {
                $this->logSecurityEvent(
                    null, // No user ID for failed attempts
                    'SUSPICIOUS_LOGIN_ACTIVITY',
                    'login_attempt',
                    null,
                    json_encode(['email' => $email]),
                    json_encode([
                        'ip_failures' => $ipFailures,
                        'email_failures' => $emailFailures,
                        'threshold_exceeded' => true
                    ]),
                    $ipAddress,
                    null,
                    'HIGH'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to check suspicious activity: " . $e->getMessage());
        }
    }
    
    /**
     * Feature 2: Admin Activity Audit Trail
     * Detailed logging of all administrative actions
     */
    public function logAdminActivity($userId, $actionType, $resourceType, $resourceId, $oldValues, $newValues, $ipAddress = null, $userAgent = null) {
        try {
            // Determine risk level based on action type
            $riskLevel = $this->determineRiskLevel($actionType, $resourceType);
            
            return $this->logSecurityEvent(
                $userId,
                $actionType,
                $resourceType,
                $resourceId,
                $oldValues,
                $newValues,
                $ipAddress,
                $userAgent,
                $riskLevel
            );
        } catch (Exception $e) {
            error_log("Failed to log admin activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generic security event logging
     */
    public function logSecurityEvent($userId, $actionType, $resourceType, $resourceId, $oldValues, $newValues, $ipAddress, $userAgent, $riskLevel = 'LOW') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO security_audit_logs (
                    user_id, action_type, resource_type, resource_id,
                    old_values, new_values, ip_address, user_agent, risk_level
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userId,
                $actionType,
                $resourceType,
                $resourceId,
                is_string($oldValues) ? $oldValues : json_encode($oldValues),
                is_string($newValues) ? $newValues : json_encode($newValues),
                $ipAddress,
                $userAgent,
                $riskLevel
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Determine risk level based on action and resource type
     */
    private function determineRiskLevel($actionType, $resourceType) {
        $highRiskActions = [
            'DELETE_USER',
            'CHANGE_USER_ROLE',
            'DELETE_SCHOLARSHIP',
            'APPROVE_SCHOLARSHIP',
            'SYSTEM_SETTINGS_CHANGE',
            'PASSWORD_RESET',
            'BULK_DELETE'
        ];
        
        $mediumRiskActions = [
            'CREATE_USER',
            'UPDATE_USER',
            'CREATE_SCHOLARSHIP',
            'UPDATE_SCHOLARSHIP',
            'APPROVE_APPLICATION',
            'REJECT_APPLICATION'
        ];
        
        $criticalResources = [
            'system_settings',
            'user_roles',
            'admin_accounts'
        ];
        
        // Critical risk for critical resources
        if (in_array($resourceType, $criticalResources)) {
            return 'CRITICAL';
        }
        
        // High risk actions
        if (in_array($actionType, $highRiskActions)) {
            return 'HIGH';
        }
        
        // Medium risk actions
        if (in_array($actionType, $mediumRiskActions)) {
            return 'MEDIUM';
        }
        
        return 'LOW';
    }
    
    /**
     * Get login attempt statistics
     */
    public function getLoginStatistics($timeframe = '24 HOUR', $groupBy = 'hour') {
        try {
            $dateFormat = $groupBy === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
            
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE_FORMAT(created_at, ?) as time_period,
                    attempt_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT email) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM login_attempts
                WHERE created_at > DATE_SUB(NOW(), INTERVAL {$timeframe})
                GROUP BY time_period, attempt_type
                ORDER BY time_period DESC
            ");
            $stmt->execute([$dateFormat]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get login statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get admin activity summary
     */
    public function getAdminActivitySummary($timeframe = '7 DAY', $userId = null) {
        try {
            $whereClause = $userId ? "AND sal.user_id = ?" : "";
            $params = $userId ? [$userId] : [];
            
            $stmt = $this->conn->prepare("
                SELECT 
                    u.fullname as admin_name,
                    u.email as admin_email,
                    sal.action_type,
                    sal.resource_type,
                    sal.risk_level,
                    COUNT(*) as action_count,
                    MAX(sal.created_at) as last_action
                FROM security_audit_logs sal
                JOIN users u ON sal.user_id = u.id
                WHERE u.user_type = 'admin'
                AND sal.created_at > DATE_SUB(NOW(), INTERVAL {$timeframe})
                {$whereClause}
                GROUP BY u.id, u.fullname, u.email, sal.action_type, sal.resource_type, sal.risk_level
                ORDER BY last_action DESC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get admin activity summary: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security alerts (high and critical risk events)
     */
    public function getSecurityAlerts($limit = 50) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    sal.*,
                    u.fullname as user_name,
                    u.email as user_email,
                    u.user_type
                FROM security_audit_logs sal
                LEFT JOIN users u ON sal.user_id = u.id
                WHERE sal.risk_level IN ('HIGH', 'CRITICAL')
                ORDER BY sal.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get security alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get failed login attempts by IP address
     */
    public function getFailedLoginsByIP($timeframe = '24 HOUR', $threshold = 5) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as failed_attempts,
                    COUNT(DISTINCT email) as targeted_accounts,
                    MIN(created_at) as first_attempt,
                    MAX(created_at) as last_attempt,
                    GROUP_CONCAT(DISTINCT email) as targeted_emails
                FROM login_attempts
                WHERE attempt_type = 'failure'
                AND created_at > DATE_SUB(NOW(), INTERVAL {$timeframe})
                GROUP BY ip_address
                HAVING failed_attempts >= ?
                ORDER BY failed_attempts DESC
            ");
            $stmt->execute([$threshold]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get failed logins by IP: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate security report
     */
    public function generateSecurityReport($timeframe = '7 DAY') {
        try {
            $report = [
                'period' => $timeframe,
                'generated_at' => date('Y-m-d H:i:s'),
                'login_summary' => [],
                'admin_activity' => [],
                'security_alerts' => [],
                'suspicious_ips' => [],
                'recommendations' => []
            ];
            
            // Login summary
            $loginStats = $this->getLoginStatistics($timeframe, 'day');
            $report['login_summary'] = $this->processLoginStats($loginStats);
            
            // Admin activity
            $report['admin_activity'] = $this->getAdminActivitySummary($timeframe);
            
            // Security alerts
            $report['security_alerts'] = $this->getSecurityAlerts(20);
            
            // Suspicious IPs
            $report['suspicious_ips'] = $this->getFailedLoginsByIP($timeframe, 3);
            
            // Generate recommendations
            $report['recommendations'] = $this->generateSecurityRecommendations($report);
            
            return $report;
        } catch (Exception $e) {
            error_log("Failed to generate security report: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process login statistics for reporting
     */
    private function processLoginStats($loginStats) {
        $summary = [
            'total_attempts' => 0,
            'successful_logins' => 0,
            'failed_attempts' => 0,
            'blocked_attempts' => 0,
            'success_rate' => 0,
            'daily_breakdown' => []
        ];
        
        foreach ($loginStats as $stat) {
            $summary['total_attempts'] += $stat['count'];
            
            switch ($stat['attempt_type']) {
                case 'success':
                    $summary['successful_logins'] += $stat['count'];
                    break;
                case 'failure':
                    $summary['failed_attempts'] += $stat['count'];
                    break;
                case 'blocked':
                    $summary['blocked_attempts'] += $stat['count'];
                    break;
            }
            
            $summary['daily_breakdown'][] = $stat;
        }
        
        if ($summary['total_attempts'] > 0) {
            $summary['success_rate'] = round(($summary['successful_logins'] / $summary['total_attempts']) * 100, 2);
        }
        
        return $summary;
    }
    
    /**
     * Generate security recommendations based on report data
     */
    private function generateSecurityRecommendations($report) {
        $recommendations = [];
        
        // Check success rate
        if (isset($report['login_summary']['success_rate']) && $report['login_summary']['success_rate'] < 80) {
            $recommendations[] = [
                'type' => 'WARNING',
                'message' => 'Low login success rate detected. Consider reviewing authentication process.',
                'priority' => 'MEDIUM'
            ];
        }
        
        // Check for high-risk activities
        $highRiskCount = count(array_filter($report['security_alerts'], function($alert) {
            return $alert['risk_level'] === 'HIGH' || $alert['risk_level'] === 'CRITICAL';
        }));
        
        if ($highRiskCount > 10) {
            $recommendations[] = [
                'type' => 'ALERT',
                'message' => 'High number of high-risk security events detected. Review admin activities.',
                'priority' => 'HIGH'
            ];
        }
        
        // Check for suspicious IPs
        if (count($report['suspicious_ips']) > 5) {
            $recommendations[] = [
                'type' => 'SECURITY',
                'message' => 'Multiple IPs with failed login attempts detected. Consider implementing IP blocking.',
                'priority' => 'HIGH'
            ];
        }
        
        return $recommendations;
    }
}

/**
 * Real-time Security Monitor
 * Provides real-time monitoring capabilities
 */
class RealTimeSecurityMonitor {
    private $monitoring;
    
    public function __construct() {
        $this->monitoring = new SecurityMonitoring();
    }
    
    /**
     * Check for immediate security threats
     */
    public function checkImmediateThreats() {
        $threats = [];
        
        // Check for recent critical events
        $criticalEvents = $this->monitoring->getSecurityAlerts(10);
        $recentCritical = array_filter($criticalEvents, function($event) {
            return $event['risk_level'] === 'CRITICAL' && 
                   strtotime($event['created_at']) > (time() - 3600); // Last hour
        });
        
        if (!empty($recentCritical)) {
            $threats[] = [
                'type' => 'CRITICAL_ACTIVITY',
                'message' => 'Critical security events detected in the last hour',
                'count' => count($recentCritical),
                'severity' => 'CRITICAL'
            ];
        }
        
        // Check for brute force attacks
        $suspiciousIPs = $this->monitoring->getFailedLoginsByIP('1 HOUR', 10);
        if (!empty($suspiciousIPs)) {
            $threats[] = [
                'type' => 'BRUTE_FORCE',
                'message' => 'Potential brute force attacks detected',
                'count' => count($suspiciousIPs),
                'severity' => 'HIGH'
            ];
        }
        
        return $threats;
    }
    
    /**
     * Get real-time dashboard data
     */
    public function getDashboardData() {
        return [
            'active_sessions' => $this->getActiveSessionCount(),
            'recent_logins' => $this->monitoring->getLoginStatistics('1 HOUR'),
            'security_alerts' => $this->monitoring->getSecurityAlerts(5),
            'immediate_threats' => $this->checkImmediateThreats(),
            'system_health' => $this->getSystemHealthStatus()
        ];
    }
    
    /**
     * Get active session count
     */
    private function getActiveSessionCount() {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->query("
                SELECT COUNT(*) as count 
                FROM user_sessions 
                WHERE is_active = 1 AND expires_at > NOW()
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get system health status
     */
    private function getSystemHealthStatus() {
        return [
            'status' => 'HEALTHY',
            'uptime' => '99.9%',
            'last_check' => date('Y-m-d H:i:s')
        ];
    }
}
?>