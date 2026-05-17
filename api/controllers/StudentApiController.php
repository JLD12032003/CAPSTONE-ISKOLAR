<?php
/**
 * Student API Controller
 */

require_once __DIR__ . '/../../app/models/StudentProfile.php';
require_once __DIR__ . '/../../app/models/Scholarship.php';

class StudentApiController extends ApiController {
    
    public function __construct() {
        parent::__construct();
        $this->validateUser('student');
    }
    
    // Get student profile
    public function getProfile() {
        try {
            $profileModel = new StudentProfile();
            $profile = $profileModel->getProfile($this->user['id']);
            
            if (!$profile) {
                // Create empty profile if doesn't exist
                $profileModel->createProfile($this->user['id']);
                $profile = $profileModel->getProfile($this->user['id']);
            }
            
            $responseData = [
                'user' => [
                    'id' => $this->user['id'],
                    'fullname' => $this->user['fullname'],
                    'email' => $this->user['email'],
                    'profile_completed' => $this->user['profile_completed'],
                    'profile_completion_step' => $this->user['profile_completion_step']
                ],
                'profile' => $profile
            ];
            
            ApiConfig::response($responseData, 'Profile retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving profile', 500);
        }
    }
    
    // Update profile phase
    public function updateProfilePhase() {
        $phase = intval($_REQUEST['path_params'][0] ?? 0);
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        if ($phase < 1 || $phase > 5) {
            ApiConfig::response(null, 'Invalid phase number', 422);
        }
        
        try {
            $profileModel = new StudentProfile();
            $userModel = new User();
            
            // Ensure profile exists
            $profile = $profileModel->getProfile($this->user['id']);
            if (!$profile) {
                $profileModel->createProfile($this->user['id']);
            }
            
            $result = false;
            
            switch ($phase) {
                case 1:
                    $required = ['last_name', 'first_name', 'birthdate', 'place_of_birth', 'sex', 'civil_status', 'citizenship', 'mobile_number', 'present_address', 'permanent_address', 'zip_code'];
                    $missing = ApiConfig::validateRequired($data, $required);
                    if (!empty($missing)) {
                        ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
                    }
                    $result = $profileModel->updatePhase1($this->user['id'], $data);
                    break;
                    
                case 2:
                    $required = ['school_name', 'school_address', 'school_sector', 'course', 'year_level'];
                    $missing = ApiConfig::validateRequired($data, $required);
                    if (!empty($missing)) {
                        ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
                    }
                    $result = $profileModel->updatePhase2($this->user['id'], $data);
                    break;
                    
                case 3:
                    $result = $profileModel->updatePhase3($this->user['id'], $data);
                    break;
                    
                case 4:
                    $required = ['family_monthly_income', 'is_4ps_beneficiary'];
                    $missing = ApiConfig::validateRequired($data, $required);
                    if (!empty($missing)) {
                        ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
                    }
                    $result = $profileModel->updatePhase4($this->user['id'], $data);
                    break;
                    
                case 5:
                    $required = ['gwa'];
                    $missing = ApiConfig::validateRequired($data, $required);
                    if (!empty($missing)) {
                        ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
                    }
                    $result = $profileModel->updatePhase5($this->user['id'], $data);
                    // Mark profile as completed
                    $userModel->updateProfileCompletion($this->user['id'], 5, true);
                    break;
            }
            
            if ($result) {
                // Update completion step
                if ($phase < 5) {
                    $userModel->updateProfileCompletion($this->user['id'], $phase + 1);
                }
                
                ApiConfig::response(null, "Phase {$phase} updated successfully");
            } else {
                ApiConfig::response(null, 'Failed to update profile phase', 500);
            }
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error updating profile phase', 500);
        }
    }
    
    // Update entire profile
    public function updateProfile() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        try {
            $profileModel = new StudentProfile();
            
            // Update each phase if data is provided
            if (isset($data['phase1'])) {
                $profileModel->updatePhase1($this->user['id'], $data['phase1']);
            }
            if (isset($data['phase2'])) {
                $profileModel->updatePhase2($this->user['id'], $data['phase2']);
            }
            if (isset($data['phase3'])) {
                $profileModel->updatePhase3($this->user['id'], $data['phase3']);
            }
            if (isset($data['phase4'])) {
                $profileModel->updatePhase4($this->user['id'], $data['phase4']);
            }
            if (isset($data['phase5'])) {
                $profileModel->updatePhase5($this->user['id'], $data['phase5']);
            }
            
            ApiConfig::response(null, 'Profile updated successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error updating profile', 500);
        }
    }
    
    // Get student applications
    public function getApplications() {
        try {
            $scholarshipModel = new Scholarship();
            $applications = $scholarshipModel->getStudentApplications($this->user['id']);
            
            ApiConfig::response($applications, 'Applications retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving applications', 500);
        }
    }
    
    // Submit scholarship application
    public function submitApplication() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['scholarship_id', 'personal_statement', 'why_deserve_scholarship'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        try {
            // Check if profile is completed
            if (!$this->user['profile_completed']) {
                ApiConfig::response(null, 'Please complete your profile before applying', 422);
            }
            
            $scholarshipModel = new Scholarship();
            
            // Check if scholarship exists and is active
            $scholarship = $scholarshipModel->getScholarshipById($data['scholarship_id']);
            if (!$scholarship || $scholarship['status'] !== 'Active') {
                ApiConfig::response(null, 'Scholarship not found or not active', 404);
            }
            
            // Check if already applied
            $existingApplications = $scholarshipModel->getStudentApplications($this->user['id']);
            foreach ($existingApplications as $app) {
                if ($app['scholarship_id'] == $data['scholarship_id']) {
                    ApiConfig::response(null, 'You have already applied for this scholarship', 422);
                }
            }
            
            $result = $scholarshipModel->applyForScholarship(
                $data['scholarship_id'],
                $this->user['id'],
                $data
            );
            
            if ($result) {
                ApiConfig::response(null, 'Application submitted successfully', 201);
            } else {
                ApiConfig::response(null, 'Failed to submit application', 500);
            }
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error submitting application', 500);
        }
    }
    
    // Get student awards
    public function getAwards() {
        try {
            $stmt = $this->conn->prepare("
                SELECT saw.*, s.title, s.scholarship_type, s.amount,
                       u.fullname as provider_name
                FROM scholarship_awards saw
                JOIN scholarships s ON saw.scholarship_id = s.id
                JOIN users u ON s.provider_id = u.id
                WHERE saw.student_id = ?
                ORDER BY saw.created_at DESC
            ");
            $stmt->execute([$this->user['id']]);
            $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiConfig::response($awards, 'Awards retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving awards', 500);
        }
    }
    
    // Get student dashboard data
    public function getDashboard() {
        try {
            $scholarshipModel = new Scholarship();
            
            // Get statistics
            $scholarships = $scholarshipModel->getActiveScholarships(5);
            $applications = $scholarshipModel->getStudentApplications($this->user['id']);
            
            $totalApplications = count($applications);
            $approvedCount = count(array_filter($applications, fn($app) => $app['provider_decision'] === 'Approved'));
            
            // Get total awarded amount
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(saw.amount_awarded), 0) as total
                FROM scholarship_awards saw
                WHERE saw.student_id = ?
            ");
            $stmt->execute([$this->user['id']]);
            $totalAwarded = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $dashboardData = [
                'statistics' => [
                    'available_scholarships' => count($scholarships),
                    'total_applications' => $totalApplications,
                    'approved_applications' => $approvedCount,
                    'total_awarded' => floatval($totalAwarded)
                ],
                'recent_scholarships' => $scholarships,
                'recent_applications' => array_slice($applications, 0, 5),
                'profile_status' => [
                    'completed' => $this->user['profile_completed'],
                    'completion_step' => $this->user['profile_completion_step']
                ]
            ];
            
            ApiConfig::response($dashboardData, 'Dashboard data retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving dashboard data', 500);
        }
    }
}