<?php
/**
 * Students API Routes
 */

require_once __DIR__ . '/../../app/models/StudentProfile.php';
require_once __DIR__ . '/../../app/models/Scholarship.php';

class StudentsApi {
    
    /**
     * Get student profile
     * GET /api/students/profile
     */
    public static function getProfile() {
        $user = ApiAuth::requireAuth(['student']);
        
        $profileModel = new StudentProfile();
        $profile = $profileModel->getProfile($user['user_id']);
        
        if (!$profile) {
            // Create empty profile if doesn't exist
            $profileModel->createProfile($user['user_id']);
            $profile = $profileModel->getProfile($user['user_id']);
        }
        
        // Get user info
        $userModel = new User();
        $userInfo = $userModel->findById($user['user_id']);
        
        ApiResponse::success([
            'user' => [
                'id' => $userInfo['id'],
                'fullname' => $userInfo['fullname'],
                'email' => $userInfo['email'],
                'profile_completed' => (bool)$userInfo['profile_completed'],
                'profile_completion_step' => (int)$userInfo['profile_completion_step']
            ],
            'profile' => $profile
        ]);
    }
    
    /**
     * Update profile phase
     * POST /api/students/profile/phase/{phase}
     */
    public static function updatePhase($phase) {
        $user = ApiAuth::requireAuth(['student']);
        $data = ApiValidator::getJsonInput();
        
        $phase = (int)$phase;
        if ($phase < 1 || $phase > 5) {
            ApiResponse::error('Invalid phase. Must be between 1 and 5', 400);
        }
        
        $profileModel = new StudentProfile();
        $userModel = new User();
        
        try {
            switch ($phase) {
                case 1:
                    $required = ['last_name', 'first_name', 'birthdate', 'sex', 'civil_status', 'citizenship', 'mobile_number', 'present_address', 'permanent_address'];
                    $errors = ApiValidator::required($data, $required);
                    
                    if (!empty($errors)) {
                        ApiResponse::validationError($errors);
                    }
                    
                    $profileModel->updatePhase1($user['user_id'], $data);
                    $userModel->updateProfileCompletion($user['user_id'], 2);
                    break;
                    
                case 2:
                    $required = ['school_name', 'school_address', 'school_sector', 'course', 'year_level'];
                    $errors = ApiValidator::required($data, $required);
                    
                    if (!empty($errors)) {
                        ApiResponse::validationError($errors);
                    }
                    
                    $profileModel->updatePhase2($user['user_id'], $data);
                    $userModel->updateProfileCompletion($user['user_id'], 3);
                    break;
                    
                case 3:
                    $profileModel->updatePhase3($user['user_id'], $data);
                    $userModel->updateProfileCompletion($user['user_id'], 4);
                    break;
                    
                case 4:
                    $required = ['family_monthly_income', 'is_4ps_beneficiary'];
                    $errors = ApiValidator::required($data, $required);
                    
                    if (!empty($errors)) {
                        ApiResponse::validationError($errors);
                    }
                    
                    $profileModel->updatePhase4($user['user_id'], $data);
                    $userModel->updateProfileCompletion($user['user_id'], 5);
                    break;
                    
                case 5:
                    $required = ['gwa'];
                    $errors = ApiValidator::required($data, $required);
                    
                    if (!empty($errors)) {
                        ApiResponse::validationError($errors);
                    }
                    
                    $profileModel->updatePhase5($user['user_id'], $data);
                    $userModel->updateProfileCompletion($user['user_id'], 5, true);
                    break;
            }
            
            ApiResponse::success(null, "Phase {$phase} updated successfully");
            
        } catch (Exception $e) {
            ApiResponse::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get available scholarships
     * GET /api/students/scholarships
     */
    public static function getScholarships() {
        $user = ApiAuth::requireAuth(['student']);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $search = $_GET['search'] ?? '';
        
        $errors = ApiValidator::pagination($page, $limit);
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        $scholarshipModel = new Scholarship();
        $scholarships = $scholarshipModel->getActiveScholarships();
        
        // Apply search filter if provided
        if ($search) {
            $scholarships = array_filter($scholarships, function($scholarship) use ($search) {
                return stripos($scholarship['title'], $search) !== false ||
                       stripos($scholarship['description'], $search) !== false ||
                       stripos($scholarship['provider_name'], $search) !== false;
            });
        }
        
        // Apply pagination
        $total = count($scholarships);
        $offset = ($page - 1) * $limit;
        $scholarships = array_slice($scholarships, $offset, $limit);
        
        ApiResponse::paginated($scholarships, $total, $page, $limit);
    }
    
    /**
     * Apply for scholarship
     * POST /api/students/apply/{scholarshipId}
     */
    public static function applyScholarship($scholarshipId) {
        $user = ApiAuth::requireAuth(['student']);
        $data = ApiValidator::getJsonInput();
        
        $required = ['personal_statement', 'why_deserve_scholarship'];
        $errors = ApiValidator::required($data, $required);
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Check if user profile is completed
        $userModel = new User();
        $userInfo = $userModel->findById($user['user_id']);
        
        if (!$userInfo['profile_completed']) {
            ApiResponse::error('Profile must be completed before applying', 400, [
                'requires_profile_completion' => true
            ]);
        }
        
        $scholarshipModel = new Scholarship();
        
        // Check if scholarship exists and is active
        $scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
        if (!$scholarship) {
            ApiResponse::notFound('Scholarship not found');
        }
        
        if ($scholarship['status'] !== 'Active') {
            ApiResponse::error('Scholarship is not accepting applications', 400);
        }
        
        // Check if already applied
        $applications = $scholarshipModel->getStudentApplications($user['user_id']);
        foreach ($applications as $app) {
            if ($app['scholarship_id'] == $scholarshipId) {
                ApiResponse::error('Already applied for this scholarship', 409);
            }
        }
        
        try {
            $success = $scholarshipModel->applyForScholarship($scholarshipId, $user['user_id'], $data);
            
            if ($success) {
                ApiResponse::success(null, 'Application submitted successfully', 201);
            } else {
                ApiResponse::error('Failed to submit application', 500);
            }
        } catch (Exception $e) {
            ApiResponse::error('Application failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get student applications
     * GET /api/students/applications
     */
    public static function getApplications() {
        $user = ApiAuth::requireAuth(['student']);
        
        $scholarshipModel = new Scholarship();
        $applications = $scholarshipModel->getStudentApplications($user['user_id']);
        
        ApiResponse::success($applications);
    }
}