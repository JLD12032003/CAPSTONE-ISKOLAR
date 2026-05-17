<?php
/**
 * Authentication API Routes
 */

require_once __DIR__ . '/../../app/models/User.php';

class AuthApi {
    
    /**
     * Register new user
     * POST /api/auth/register
     */
    public static function register() {
        $data = ApiValidator::getJsonInput();
        
        // Validate required fields
        $errors = ApiValidator::required($data, ['fullname', 'email', 'password', 'user_type']);
        
        // Validate email
        if (isset($data['email'])) {
            $emailError = ApiValidator::email($data['email']);
            if ($emailError) {
                $errors['email'] = $emailError;
            }
        }
        
        // Validate password
        if (isset($data['password'])) {
            $passwordError = ApiValidator::password($data['password']);
            if ($passwordError) {
                $errors['password'] = $passwordError;
            }
        }
        
        // Validate user type
        if (isset($data['user_type'])) {
            $userTypeError = ApiValidator::userType($data['user_type']);
            if ($userTypeError) {
                $errors['user_type'] = $userTypeError;
            }
        }
        
        // Validate password confirmation
        if (isset($data['password']) && isset($data['confirm_password'])) {
            if ($data['password'] !== $data['confirm_password']) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Check if email exists
        $userModel = new User();
        if ($userModel->findByEmail($data['email'])) {
            ApiResponse::error('Email already exists', 409);
        }
        
        // Create user
        try {
            $success = $userModel->register(
                $data['fullname'],
                $data['email'],
                $data['password'],
                $data['user_type']
            );
            
            if ($success) {
                $user = $userModel->findByEmail($data['email']);
                
                // Generate verification token
                $token = bin2hex(random_bytes(32));
                $userModel->saveToken($user['id'], $token);
                
                ApiResponse::success([
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'user_type' => $user['user_type'],
                    'verification_token' => $token,
                    'requires_verification' => true
                ], 'User registered successfully', 201);
            } else {
                ApiResponse::error('Registration failed', 500);
            }
        } catch (Exception $e) {
            ApiResponse::error('Registration failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Login user
     * POST /api/auth/login
     */
    public static function login() {
        $data = ApiValidator::getJsonInput();
        
        // Validate required fields
        $errors = ApiValidator::required($data, ['email', 'password']);
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        $userModel = new User();
        $user = $userModel->findByEmail($data['email']);
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            ApiResponse::error('Invalid credentials', 401);
        }
        
        if (!$user['is_verified']) {
            ApiResponse::error('Email not verified', 403, [
                'requires_verification' => true,
                'user_id' => $user['id']
            ]);
        }
        
        // Generate JWT token
        $token = ApiAuth::generateToken($user['id'], $user['user_type'], $user['email']);
        
        ApiResponse::success([
            'user' => [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'profile_completed' => (bool)$user['profile_completed']
            ],
            'token' => $token,
            'expires_in' => 24 * 60 * 60 // 24 hours in seconds
        ], 'Login successful');
    }
    
    /**
     * Verify email
     * POST /api/auth/verify
     */
    public static function verifyEmail() {
        $data = ApiValidator::getJsonInput();
        
        $errors = ApiValidator::required($data, ['token']);
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        $userModel = new User();
        $success = $userModel->verifyEmail($data['token']);
        
        if ($success) {
            ApiResponse::success(null, 'Email verified successfully');
        } else {
            ApiResponse::error('Invalid or expired verification token', 400);
        }
    }
    
    /**
     * Refresh token
     * POST /api/auth/refresh
     */
    public static function refreshToken() {
        $user = ApiAuth::requireAuth();
        
        // Generate new token
        $token = ApiAuth::generateToken($user['user_id'], $user['user_type'], $user['email']);
        
        ApiResponse::success([
            'token' => $token,
            'expires_in' => 24 * 60 * 60
        ], 'Token refreshed successfully');
    }
    
    /**
     * Logout user
     * POST /api/auth/logout
     */
    public static function logout() {
        ApiAuth::requireAuth();
        
        // In a real implementation, you might want to blacklist the token
        // For now, we'll just return success
        ApiResponse::success(null, 'Logged out successfully');
    }
}