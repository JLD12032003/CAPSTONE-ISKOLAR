<?php
/**
 * Authentication API Controller
 */

require_once __DIR__ . '/../../app/models/User.php';

class AuthApiController extends ApiController {
    
    // Login
    public function login() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['email', 'password'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        try {
            $userModel = new User();
            $user = $userModel->findByEmail($data['email']);
            
            if (!$user || !password_verify($data['password'], $user['password'])) {
                ApiConfig::response(null, 'Invalid credentials', 401);
            }
            
            if (!$user['is_verified']) {
                ApiConfig::response(null, 'Email not verified', 401);
            }
            
            // Generate JWT token
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'user_type' => $user['user_type']
            ];
            
            $token = ApiConfig::generateJWT($payload);
            
            $responseData = [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email'],
                    'user_type' => $user['user_type'],
                    'profile_completed' => $user['profile_completed'],
                    'profile_completion_step' => $user['profile_completion_step']
                ]
            ];
            
            ApiConfig::response($responseData, 'Login successful');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Login failed', 500);
        }
    }
    
    // Register
    public function register() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['fullname', 'email', 'password', 'user_type'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        // Validate user type
        if (!in_array($data['user_type'], ['student', 'provider'])) {
            ApiConfig::response(null, 'Invalid user type', 422);
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiConfig::response(null, 'Invalid email format', 422);
        }
        
        // Validate password length
        if (strlen($data['password']) < 6) {
            ApiConfig::response(null, 'Password must be at least 6 characters', 422);
        }
        
        try {
            $userModel = new User();
            
            // Check if email exists
            if ($userModel->findByEmail($data['email'])) {
                ApiConfig::response(null, 'Email already exists', 422);
            }
            
            // Register user
            $result = $userModel->register($data['fullname'], $data['email'], $data['password'], $data['user_type']);
            
            if (!$result) {
                ApiConfig::response(null, 'Registration failed', 500);
            }
            
            // Get the created user
            $user = $userModel->findByEmail($data['email']);
            
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            $userModel->saveToken($user['id'], $token);
            
            $responseData = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'verification_token' => $token,
                'message' => 'Registration successful. Please verify your email.'
            ];
            
            ApiConfig::response($responseData, 'Registration successful', 201);
        } catch (Exception $e) {
            ApiConfig::response(null, 'Registration failed', 500);
        }
    }
    
    // Verify email
    public function verifyEmail() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['token'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        try {
            $userModel = new User();
            $result = $userModel->verifyEmail($data['token']);
            
            if ($result) {
                ApiConfig::response(null, 'Email verified successfully');
            } else {
                ApiConfig::response(null, 'Invalid or expired verification token', 400);
            }
        } catch (Exception $e) {
            ApiConfig::response(null, 'Email verification failed', 500);
        }
    }
    
    // Forgot password
    public function forgotPassword() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['email'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        try {
            $userModel = new User();
            $user = $userModel->findByEmail($data['email']);
            
            if (!$user) {
                // Don't reveal if email exists or not
                ApiConfig::response(null, 'If the email exists, a reset link has been sent');
                return;
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            
            // Store reset token (you'd need to add this to User model)
            $stmt = $this->conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$user['id'], $token]);
            
            $responseData = [
                'reset_token' => $token,
                'message' => 'Password reset token generated'
            ];
            
            ApiConfig::response($responseData, 'If the email exists, a reset link has been sent');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Password reset request failed', 500);
        }
    }
    
    // Reset password
    public function resetPassword() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['token', 'password'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        if (strlen($data['password']) < 6) {
            ApiConfig::response(null, 'Password must be at least 6 characters', 422);
        }
        
        try {
            // Verify reset token
            $stmt = $this->conn->prepare("
                SELECT user_id FROM password_resets 
                WHERE token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$data['token']]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset) {
                ApiConfig::response(null, 'Invalid or expired reset token', 400);
            }
            
            // Update password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Delete used reset token
            $stmt = $this->conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$data['token']]);
            
            ApiConfig::response(null, 'Password reset successful');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Password reset failed', 500);
        }
    }
}