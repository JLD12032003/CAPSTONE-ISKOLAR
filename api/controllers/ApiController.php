<?php
/**
 * Base API Controller
 */

class ApiController {
    protected $conn;
    protected $user;
    
    public function __construct() {
        $this->conn = ApiConfig::getDatabase();
        $this->user = $_REQUEST['user'] ?? null;
    }
    
    protected function getRequestData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    protected function validateUser($requiredRole = null) {
        if (!$this->user) {
            ApiConfig::response(null, 'Authentication required', 401);
        }
        
        if ($requiredRole && $this->user['user_type'] !== $requiredRole) {
            ApiConfig::response(null, 'Insufficient permissions', 403);
        }
    }
    
    // Get schools
    public function getSchools() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM schools WHERE is_active = 1 ORDER BY school_name");
            $stmt->execute();
            $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiConfig::response($schools, 'Schools retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving schools', 500);
        }
    }
    
    // Get user profile
    public function getUserProfile() {
        $this->validateUser();
        
        try {
            $userData = [
                'id' => $this->user['id'],
                'fullname' => $this->user['fullname'],
                'email' => $this->user['email'],
                'user_type' => $this->user['user_type'],
                'is_verified' => $this->user['is_verified'],
                'profile_completed' => $this->user['profile_completed'],
                'profile_completion_step' => $this->user['profile_completion_step'],
                'created_at' => $this->user['created_at']
            ];
            
            ApiConfig::response($userData, 'User profile retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving user profile', 500);
        }
    }
    
    // Update user profile
    public function updateUserProfile() {
        $this->validateUser();
        
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['fullname'])) {
                $updateFields[] = "fullname = ?";
                $params[] = $data['fullname'];
            }
            
            if (isset($data['email'])) {
                // Check if email already exists
                $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $this->user['id']]);
                if ($stmt->rowCount() > 0) {
                    ApiConfig::response(null, 'Email already exists', 422);
                }
                
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (empty($updateFields)) {
                ApiConfig::response(null, 'No valid fields to update', 422);
            }
            
            $params[] = $this->user['id'];
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            ApiConfig::response(null, 'Profile updated successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error updating profile', 500);
        }
    }
    
    // Logout (invalidate token - in a real implementation, you'd maintain a blacklist)
    public function logout() {
        $this->validateUser();
        
        // In a production system, you would add the token to a blacklist
        // For now, we'll just return success
        ApiConfig::response(null, 'Logged out successfully');
    }
}