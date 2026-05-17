<?php
/**
 * Authorization Security Module
 * Enhanced RBAC with granular permissions and API protection
 */

class AuthorizationSecurity {
    private $conn;
    private $permissions;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
        $this->initializePermissions();
    }
    
    /**
     * Feature 1: Enhanced RBAC with Granular Permissions
     * Extends existing role system with detailed permissions
     */
    private function initializePermissions() {
        $this->permissions = [
            'student' => [
                // Profile management
                'profile.view' => true,
                'profile.edit' => true,
                'profile.delete' => false,
                
                // Scholarship access
                'scholarship.view' => true,
                'scholarship.apply' => true,
                'scholarship.create' => false,
                'scholarship.edit' => false,
                'scholarship.delete' => false,
                
                // Application management
                'application.view_own' => true,
                'application.view_all' => false,
                'application.edit_own' => true,
                'application.approve' => false,
                
                // Data access
                'data.view_public' => true,
                'data.view_sensitive' => false,
                'data.view_confidential' => false,
                
                // Admin functions
                'admin.reports' => false,
                'admin.user_management' => false,
                'admin.system_settings' => false
            ],
            
            'provider' => [
                // Profile management
                'profile.view' => true,
                'profile.edit' => true,
                'profile.delete' => false,
                
                // Scholarship management
                'scholarship.view' => true,
                'scholarship.apply' => false,
                'scholarship.create' => true,
                'scholarship.edit' => true,
                'scholarship.delete' => true,
                
                // Application management
                'application.view_own' => true,
                'application.view_all' => false,
                'application.edit_own' => false,
                'application.approve' => true,
                
                // Student data access (limited)
                'data.view_public' => true,
                'data.view_sensitive' => true,
                'data.view_confidential' => false,
                
                // Admin functions
                'admin.reports' => false,
                'admin.user_management' => false,
                'admin.system_settings' => false
            ],
            
            'admin' => [
                // Full profile access
                'profile.view' => true,
                'profile.edit' => true,
                'profile.delete' => true,
                
                // Full scholarship access
                'scholarship.view' => true,
                'scholarship.apply' => false,
                'scholarship.create' => true,
                'scholarship.edit' => true,
                'scholarship.delete' => true,
                
                // Full application access
                'application.view_own' => true,
                'application.view_all' => true,
                'application.edit_own' => true,
                'application.approve' => true,
                
                // Full data access
                'data.view_public' => true,
                'data.view_sensitive' => true,
                'data.view_confidential' => true,
                
                // Admin functions
                'admin.reports' => true,
                'admin.user_management' => true,
                'admin.system_settings' => true
            ]
        ];
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($userRole, $permission) {
        return isset($this->permissions[$userRole][$permission]) && 
               $this->permissions[$userRole][$permission] === true;
    }
    
    /**
     * Get all permissions for a role
     */
    public function getRolePermissions($userRole) {
        return $this->permissions[$userRole] ?? [];
    }
    
    /**
     * Feature 2: API Endpoint Protection with Role Validation
     * Middleware for protecting API routes based on permissions
     */
    public function validateApiAccess($userRole, $endpoint, $method = 'GET') {
        $endpointPermissions = $this->getEndpointPermissions();
        
        $key = strtoupper($method) . ':' . $endpoint;
        
        if (!isset($endpointPermissions[$key])) {
            return [
                'allowed' => false,
                'message' => 'Endpoint not found or not configured',
                'required_permission' => null
            ];
        }
        
        $requiredPermission = $endpointPermissions[$key];
        $hasAccess = $this->hasPermission($userRole, $requiredPermission);
        
        return [
            'allowed' => $hasAccess,
            'message' => $hasAccess ? 'Access granted' : 'Insufficient permissions',
            'required_permission' => $requiredPermission
        ];
    }
    
    /**
     * Define API endpoint permissions mapping
     */
    private function getEndpointPermissions() {
        return [
            // Student endpoints
            'GET:/api/student/profile' => 'profile.view',
            'PUT:/api/student/profile' => 'profile.edit',
            'POST:/api/student/profile/phase' => 'profile.edit',
            'GET:/api/student/applications' => 'application.view_own',
            'POST:/api/student/applications' => 'scholarship.apply',
            
            // Provider endpoints
            'GET:/api/provider/scholarships' => 'scholarship.view',
            'POST:/api/provider/scholarships' => 'scholarship.create',
            'PUT:/api/provider/scholarships' => 'scholarship.edit',
            'DELETE:/api/provider/scholarships' => 'scholarship.delete',
            'GET:/api/provider/applications' => 'application.view_own',
            'PUT:/api/provider/applications' => 'application.approve',
            
            // Admin endpoints
            'GET:/api/admin/students' => 'admin.user_management',
            'GET:/api/admin/providers' => 'admin.user_management',
            'GET:/api/admin/scholarships' => 'scholarship.view',
            'GET:/api/admin/reports' => 'admin.reports',
            'POST:/api/admin/reports' => 'admin.reports',
            'GET:/api/admin/dashboard' => 'admin.reports',
            
            // Public endpoints (no permission required)
            'GET:/api/scholarships' => 'data.view_public',
            'GET:/api/schools' => 'data.view_public'
        ];
    }
    
    /**
     * Log authorization attempts for audit trail
     */
    public function logAuthorizationAttempt($userId, $userRole, $resource, $action, $granted, $ipAddress = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO security_audit_logs (
                    user_id, action_type, resource_type, resource_id, 
                    old_values, new_values, ip_address, risk_level
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $actionType = $granted ? 'AUTHORIZATION_GRANTED' : 'AUTHORIZATION_DENIED';
            $riskLevel = $granted ? 'LOW' : 'MEDIUM';
            
            $oldValues = json_encode(['user_role' => $userRole]);
            $newValues = json_encode([
                'resource' => $resource,
                'action' => $action,
                'granted' => $granted
            ]);
            
            return $stmt->execute([
                $userId, $actionType, 'authorization', null,
                $oldValues, $newValues, $ipAddress, $riskLevel
            ]);
        } catch (Exception $e) {
            error_log("Failed to log authorization attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check data classification access
     */
    public function canAccessDataClassification($userRole, $classification) {
        $accessMatrix = [
            'student' => ['PUBLIC'],
            'provider' => ['PUBLIC', 'SENSITIVE'],
            'admin' => ['PUBLIC', 'SENSITIVE', 'CONFIDENTIAL']
        ];
        
        return in_array($classification, $accessMatrix[$userRole] ?? []);
    }
    
    /**
     * Get accessible fields for user role based on data classification
     */
    public function getAccessibleFields($userRole, $tableName) {
        try {
            $stmt = $this->conn->prepare("
                SELECT column_name, classification, access_roles
                FROM data_classification
                WHERE table_name = ?
            ");
            $stmt->execute([$tableName]);
            
            $accessibleFields = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $accessRoles = json_decode($row['access_roles'], true) ?? [];
                
                if (in_array($userRole, $accessRoles) || 
                    $this->canAccessDataClassification($userRole, $row['classification'])) {
                    $accessibleFields[] = $row['column_name'];
                }
            }
            
            return $accessibleFields;
        } catch (Exception $e) {
            error_log("Failed to get accessible fields: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Enhanced API Authorization Middleware
 * Integrates with existing API middleware for enhanced security
 */
class EnhancedApiAuthMiddleware {
    private $authSecurity;
    
    public function __construct() {
        $this->authSecurity = new AuthorizationSecurity();
    }
    
    /**
     * Enhanced API authentication with permission checking
     */
    public function authenticate($requiredPermission = null) {
        // Use existing AuthMiddleware for basic auth
        $authMiddleware = new AuthMiddleware();
        $authResult = $authMiddleware->authenticate();
        
        if (!$authResult['success']) {
            return $authResult;
        }
        
        $user = $authResult['user'];
        $userRole = $user['user_type'];
        
        // If specific permission required, check it
        if ($requiredPermission) {
            $hasPermission = $this->authSecurity->hasPermission($userRole, $requiredPermission);
            
            if (!$hasPermission) {
                // Log unauthorized access attempt
                $this->authSecurity->logAuthorizationAttempt(
                    $user['id'], $userRole, $requiredPermission, 'API_ACCESS', false, 
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
                
                return [
                    'success' => false,
                    'message' => 'Insufficient permissions for this operation',
                    'required_permission' => $requiredPermission
                ];
            }
            
            // Log successful authorization
            $this->authSecurity->logAuthorizationAttempt(
                $user['id'], $userRole, $requiredPermission, 'API_ACCESS', true,
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        }
        
        return [
            'success' => true,
            'user' => $user,
            'permissions' => $this->authSecurity->getRolePermissions($userRole)
        ];
    }
    
    /**
     * Validate API endpoint access
     */
    public function validateEndpointAccess($endpoint, $method = 'GET') {
        $authResult = $this->authenticate();
        
        if (!$authResult['success']) {
            return $authResult;
        }
        
        $user = $authResult['user'];
        $accessResult = $this->authSecurity->validateApiAccess($user['user_type'], $endpoint, $method);
        
        if (!$accessResult['allowed']) {
            return [
                'success' => false,
                'message' => $accessResult['message'],
                'required_permission' => $accessResult['required_permission']
            ];
        }
        
        return [
            'success' => true,
            'user' => $user,
            'permissions' => $authResult['permissions']
        ];
    }
}
?>