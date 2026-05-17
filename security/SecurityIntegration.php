<?php
/**
 * Security Integration Wrapper
 * Integrates all security modules with existing ISKOLar system
 * WITHOUT modifying the existing email authentication flow
 */

require_once __DIR__ . '/modules/AuthenticationSecurity.php';
require_once __DIR__ . '/modules/AuthorizationSecurity.php';
require_once __DIR__ . '/modules/DataEncryption.php';
require_once __DIR__ . '/modules/SecurityMonitoring.php';
require_once __DIR__ . '/modules/DataLossPrevention.php';

class SecurityIntegration {
    private $authSecurity;
    private $authzSecurity;
    private $dataEncryption;
    private $monitoring;
    private $dlp;
    
    public function __construct() {
        $this->authSecurity = new AuthenticationSecurity();
        $this->authzSecurity = new AuthorizationSecurity();
        $this->dataEncryption = new DataEncryption();
        $this->monitoring = new SecurityMonitoring();
        $this->dlp = new DataLossPrevention();
    }
    
    /**
     * WRAPPER FOR EXISTING LOGIN (PRESERVES EMAIL AUTH)
     * Call this BEFORE your existing login logic
     */
    public function preLoginSecurity($email, $password) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // 1. Check rate limiting
        $enhancedAuth = new EnhancedAuthMiddleware();
        $preCheck = $enhancedAuth->preLoginCheck($email, $ipAddress, $userAgent);
        
        if (!$preCheck['allowed']) {
            return [
                'allowed' => false,
                'message' => $preCheck['message'],
                'security_block' => true
            ];
        }
        
        return [
            'allowed' => true,
            'attempts_remaining' => $preCheck['attempts_remaining']
        ];
    }
    
    /**
     * WRAPPER FOR EXISTING LOGIN SUCCESS
     * Call this AFTER successful login in your existing code
     */
    public function postLoginSuccess($user, $email) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // 1. Log successful login
        $this->monitoring->logLoginAttempt($email, $ipAddress, $userAgent, 'success', $user['id']);
        
        // 2. Initialize secure session
        $sessionMiddleware = new SessionSecurityMiddleware();
        $sessionResult = $sessionMiddleware->initializeSecureSession($user['id'], $user['user_type']);
        
        // 3. Check admin password expiry
        if ($user['user_type'] === 'admin') {
            $passwordCheck = $this->authSecurity->checkAdminPasswordExpiry($user['id']);
            if ($passwordCheck['needs_rotation']) {
                return [
                    'success' => true,
                    'password_rotation_required' => true,
                    'days_overdue' => abs($passwordCheck['days_remaining'])
                ];
            } elseif ($passwordCheck['warning']) {
                return [
                    'success' => true,
                    'password_rotation_warning' => true,
                    'days_remaining' => $passwordCheck['days_remaining']
                ];
            }
        }
        
        return [
            'success' => true,
            'session_initialized' => $sessionResult['success']
        ];
    }
    
    /**
     * WRAPPER FOR EXISTING LOGIN FAILURE
     * Call this AFTER failed login in your existing code
     */
    public function postLoginFailure($email, $failureReason = 'Invalid credentials') {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Log failed login attempt
        $this->monitoring->logLoginAttempt($email, $ipAddress, $userAgent, 'failure', null, $failureReason);
        
        return ['logged' => true];
    }
    
    /**
     * WRAPPER FOR EXISTING REGISTRATION
     * Call this to validate password strength during registration
     */
    public function validateRegistrationSecurity($password) {
        return $this->authSecurity->validatePasswordStrength($password);
    }
    
    /**
     * SECURE DATA RETRIEVAL (for student profiles)
     * Use this instead of direct database queries for sensitive data
     */
    public function getSecureStudentProfile($userId, $requestingUserRole, $requestingUserId) {
        // 1. Check authorization
        $hasAccess = $this->authzSecurity->hasPermission($requestingUserRole, 'profile.view');
        
        if (!$hasAccess && $requestingUserId != $userId) {
            $this->authzSecurity->logAuthorizationAttempt(
                $requestingUserId, $requestingUserRole, 'student_profile', 'view', false
            );
            return [
                'success' => false,
                'message' => 'Access denied to student profile'
            ];
        }
        
        // 2. Get regular profile data
        require_once __DIR__ . '/../app/models/StudentProfile.php';
        $profileModel = new StudentProfile();
        $profile = $profileModel->getProfile($userId);
        
        if (!$profile) {
            return ['success' => false, 'message' => 'Profile not found'];
        }
        
        // 3. Get encrypted sensitive data
        $encryptedData = $this->dataEncryption->getDecryptedProfile($userId, $requestingUserRole);
        
        // 4. Merge and filter data based on classification
        $mergedData = array_merge($profile, $encryptedData);
        $filteredData = $this->dlp->filterSensitiveData($mergedData, 'student_profiles', $requestingUserRole);
        
        // 5. Log data access
        $this->dlp->logDataAccess($requestingUserId, 'student_profiles', 'full_profile', true, 'SENSITIVE');
        
        return [
            'success' => true,
            'profile' => $filteredData,
            'access_level' => $requestingUserRole
        ];
    }
    
    /**
     * SECURE DATA UPDATE (for student profiles)
     * Use this for updating sensitive student data
     */
    public function updateSecureStudentProfile($userId, $profileData, $requestingUserRole, $requestingUserId) {
        // 1. Check authorization
        $hasAccess = $this->authzSecurity->hasPermission($requestingUserRole, 'profile.edit');
        
        if (!$hasAccess && $requestingUserId != $userId) {
            return [
                'success' => false,
                'message' => 'Access denied to update profile'
            ];
        }
        
        // 2. Separate sensitive and regular data
        $sensitiveFields = ['gwa', 'family_monthly_income', 'father_income', 'mother_income', 'mobile_number', 'birthdate'];
        $sensitiveData = [];
        $regularData = [];
        
        foreach ($profileData as $field => $value) {
            if (in_array($field, $sensitiveFields)) {
                $sensitiveData[$field] = $value;
            } else {
                $regularData[$field] = $value;
            }
        }
        
        // 3. Update regular profile data
        if (!empty($regularData)) {
            require_once __DIR__ . '/../app/models/StudentProfile.php';
            $profileModel = new StudentProfile();
            $profileModel->updateProfile($userId, $regularData);
        }
        
        // 4. Update encrypted sensitive data
        if (!empty($sensitiveData)) {
            $this->dataEncryption->storeEncryptedProfile($userId, $sensitiveData);
        }
        
        // 5. Log the update
        $this->monitoring->logSecurityEvent(
            $requestingUserId,
            'PROFILE_UPDATE',
            'student_profile',
            $userId,
            json_encode(['updated_fields' => array_keys($profileData)]),
            json_encode(['sensitive_fields_updated' => array_keys($sensitiveData)]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'MEDIUM'
        );
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'encrypted_fields' => array_keys($sensitiveData)
        ];
    }
    
    /**
     * SESSION VALIDATION MIDDLEWARE
     * Call this on every protected page request
     */
    public function validatePageAccess($requiredPermission = null) {
        // 1. Validate session
        $sessionMiddleware = new SessionSecurityMiddleware();
        $sessionValidation = $sessionMiddleware->validateCurrentSession();
        
        if (!$sessionValidation['valid']) {
            return [
                'access_granted' => false,
                'redirect_to_login' => true,
                'reason' => $sessionValidation['reason']
            ];
        }
        
        // 2. Check specific permission if required
        if ($requiredPermission && isset($_SESSION['user_type'])) {
            $hasPermission = $this->authzSecurity->hasPermission($_SESSION['user_type'], $requiredPermission);
            
            if (!$hasPermission) {
                $this->authzSecurity->logAuthorizationAttempt(
                    $_SESSION['user_id'], $_SESSION['user_type'], $requiredPermission, 'page_access', false
                );
                
                return [
                    'access_granted' => false,
                    'redirect_to_login' => false,
                    'reason' => 'Insufficient permissions'
                ];
            }
        }
        
        return [
            'access_granted' => true,
            'session_valid' => true,
            'expires_in' => $sessionValidation['expires_in'] ?? 0
        ];
    }
    
    /**
     * API SECURITY WRAPPER
     * Use this for API endpoint protection
     */
    public function validateApiRequest($endpoint, $method = 'GET') {
        $enhancedApiAuth = new EnhancedApiAuthMiddleware();
        return $enhancedApiAuth->validateEndpointAccess($endpoint, $method);
    }
    
    /**
     * ADMIN ACTIVITY LOGGER
     * Call this for all admin actions
     */
    public function logAdminAction($userId, $actionType, $resourceType, $resourceId, $oldValues, $newValues) {
        return $this->monitoring->logAdminActivity(
            $userId,
            $actionType,
            $resourceType,
            $resourceId,
            $oldValues,
            $newValues,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }
    
    /**
     * SECURITY DASHBOARD DATA
     * Get comprehensive security metrics
     */
    public function getSecurityDashboard() {
        $realTimeMonitor = new RealTimeSecurityMonitor();
        
        return [
            'real_time_data' => $realTimeMonitor->getDashboardData(),
            'login_stats' => $this->monitoring->getLoginStatistics('24 HOUR'),
            'admin_activity' => $this->monitoring->getAdminActivitySummary('7 DAY'),
            'security_alerts' => $this->monitoring->getSecurityAlerts(10),
            'session_stats' => $this->dlp->getSessionStatistics(),
            'encryption_stats' => $this->dataEncryption->getEncryptionStats(),
            'immediate_threats' => $realTimeMonitor->checkImmediateThreats()
        ];
    }
    
    /**
     * MAINTENANCE TASKS
     * Run these periodically (cron job)
     */
    public function runSecurityMaintenance() {
        $results = [];
        
        // 1. Clean up expired sessions
        $results['session_cleanup'] = $this->dlp->cleanupExpiredSessions();
        
        // 2. Generate security report
        $results['security_report'] = $this->monitoring->generateSecurityReport('24 HOUR');
        
        // 3. Check for immediate threats
        $realTimeMonitor = new RealTimeSecurityMonitor();
        $results['threat_check'] = $realTimeMonitor->checkImmediateThreats();
        
        return $results;
    }
}

/**
 * EASY INTEGRATION HELPER
 * Simple functions to integrate with existing code
 */
class SecurityHelper {
    private static $security;
    
    private static function getSecurity() {
        if (!self::$security) {
            self::$security = new SecurityIntegration();
        }
        return self::$security;
    }
    
    /**
     * Simple login security check (call before existing login)
     */
    public static function checkLogin($email, $password) {
        return self::getSecurity()->preLoginSecurity($email, $password);
    }
    
    /**
     * Simple login success handler (call after existing login)
     */
    public static function loginSuccess($user, $email) {
        return self::getSecurity()->postLoginSuccess($user, $email);
    }
    
    /**
     * Simple login failure handler (call after existing login failure)
     */
    public static function loginFailure($email, $reason = 'Invalid credentials') {
        return self::getSecurity()->postLoginFailure($email, $reason);
    }
    
    /**
     * Simple password validation (call during registration)
     */
    public static function validatePassword($password) {
        return self::getSecurity()->validateRegistrationSecurity($password);
    }
    
    /**
     * Simple page access check (call at top of protected pages)
     */
    public static function checkPageAccess($permission = null) {
        return self::getSecurity()->validatePageAccess($permission);
    }
    
    /**
     * Simple profile data retrieval (secure)
     */
    public static function getProfile($userId, $requestingRole, $requestingUserId) {
        return self::getSecurity()->getSecureStudentProfile($userId, $requestingRole, $requestingUserId);
    }
    
    /**
     * Simple admin action logging
     */
    public static function logAdmin($userId, $action, $resource, $resourceId, $oldData, $newData) {
        return self::getSecurity()->logAdminAction($userId, $action, $resource, $resourceId, $oldData, $newData);
    }
}
?>