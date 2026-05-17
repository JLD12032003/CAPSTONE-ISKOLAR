<?php
/**
 * Provider API Controller
 */

require_once __DIR__ . '/../../app/models/Scholarship.php';

class ProviderApiController extends ApiController {
    
    public function __construct() {
        parent::__construct();
        $this->validateUser('provider');
    }
    
    // Get provider's scholarships
    public function getScholarships() {
        try {
            $scholarshipModel = new Scholarship();
            $scholarships = $scholarshipModel->getProviderScholarships($this->user['id']);
            
            ApiConfig::response($scholarships, 'Scholarships retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving scholarships', 500);
        }
    }
    
    // Create new scholarship
    public function createScholarship() {
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        $required = ['title', 'description', 'scholarship_type', 'amount', 'slots', 'application_start', 'application_end'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        // Validate scholarship type
        $validTypes = ['Full', 'Partial', 'Book Allowance', 'Tuition Only', 'Living Allowance'];
        if (!in_array($data['scholarship_type'], $validTypes)) {
            ApiConfig::response(null, 'Invalid scholarship type', 422);
        }
        
        // Validate dates
        if (strtotime($data['application_start']) >= strtotime($data['application_end'])) {
            ApiConfig::response(null, 'Application end date must be after start date', 422);
        }
        
        try {
            $scholarshipData = [
                'provider_id' => $this->user['id'],
                'school_id' => $data['school_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'scholarship_type' => $data['scholarship_type'],
                'amount' => floatval($data['amount']),
                'slots' => intval($data['slots']),
                'eligible_courses' => $data['eligible_courses'] ?? null,
                'min_gwa' => $data['min_gwa'] ?? null,
                'max_family_income' => $data['max_family_income'] ?? null,
                'year_levels' => $data['year_levels'] ?? null,
                'other_requirements' => $data['other_requirements'] ?? null,
                'application_start' => $data['application_start'],
                'application_end' => $data['application_end'],
                'status' => $data['status'] ?? 'Draft'
            ];
            
            $scholarshipModel = new Scholarship();
            $result = $scholarshipModel->createScholarship($scholarshipData);
            
            if ($result) {
                ApiConfig::response(null, 'Scholarship created successfully', 201);
            } else {
                ApiConfig::response(null, 'Failed to create scholarship', 500);
            }
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error creating scholarship', 500);
        }
    }
    
    // Update scholarship
    public function updateScholarship() {
        $scholarshipId = intval($_REQUEST['path_params'][0] ?? 0);
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        if (!$scholarshipId) {
            ApiConfig::response(null, 'Invalid scholarship ID', 422);
        }
        
        try {
            // Verify ownership
            $stmt = $this->conn->prepare("SELECT * FROM scholarships WHERE id = ? AND provider_id = ?");
            $stmt->execute([$scholarshipId, $this->user['id']]);
            $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$scholarship) {
                ApiConfig::response(null, 'Scholarship not found or access denied', 404);
            }
            
            $scholarshipModel = new Scholarship();
            $result = $scholarshipModel->updateScholarship($scholarshipId, $data);
            
            if ($result) {
                ApiConfig::response(null, 'Scholarship updated successfully');
            } else {
                ApiConfig::response(null, 'Failed to update scholarship', 500);
            }
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error updating scholarship', 500);
        }
    }
    
    // Get applications for provider's scholarships
    public function getApplications() {
        try {
            $scholarshipId = $_GET['scholarship_id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT sa.*, u.fullname as student_name, u.email as student_email,
                       sp.course, sp.year_level, sp.gwa, sp.school_name,
                       s.title as scholarship_title
                FROM scholarship_applications sa
                JOIN scholarships s ON sa.scholarship_id = s.id
                JOIN users u ON sa.student_id = u.id
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                WHERE s.provider_id = ?
            ";
            
            $params = [$this->user['id']];
            
            if ($scholarshipId) {
                $sql .= " AND sa.scholarship_id = ?";
                $params[] = intval($scholarshipId);
            }
            
            if ($status) {
                $sql .= " AND sa.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY sa.created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiConfig::response($applications, 'Applications retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving applications', 500);
        }
    }
    
    // Update application status (approve/reject)
    public function updateApplication() {
        $applicationId = intval($_REQUEST['path_params'][0] ?? 0);
        $data = $this->getRequestData();
        $data = ApiConfig::sanitizeInput($data);
        
        if (!$applicationId) {
            ApiConfig::response(null, 'Invalid application ID', 422);
        }
        
        $required = ['provider_decision'];
        $missing = ApiConfig::validateRequired($data, $required);
        
        if (!empty($missing)) {
            ApiConfig::response(null, 'Missing required fields: ' . implode(', ', $missing), 422);
        }
        
        if (!in_array($data['provider_decision'], ['Approved', 'Rejected'])) {
            ApiConfig::response(null, 'Invalid provider decision', 422);
        }
        
        try {
            // Verify ownership
            $stmt = $this->conn->prepare("
                SELECT sa.*, s.provider_id 
                FROM scholarship_applications sa
                JOIN scholarships s ON sa.scholarship_id = s.id
                WHERE sa.id = ? AND s.provider_id = ?
            ");
            $stmt->execute([$applicationId, $this->user['id']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                ApiConfig::response(null, 'Application not found or access denied', 404);
            }
            
            // Update application
            $stmt = $this->conn->prepare("
                UPDATE scholarship_applications 
                SET provider_decision = ?, provider_notes = ?, decided_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['provider_decision'],
                $data['provider_notes'] ?? null,
                $applicationId
            ]);
            
            // If approved, create scholarship award
            if ($data['provider_decision'] === 'Approved') {
                $stmt = $this->conn->prepare("
                    INSERT INTO scholarship_awards (
                        application_id, scholarship_id, student_id, 
                        amount_awarded, award_date
                    ) VALUES (?, ?, ?, ?, CURDATE())
                ");
                $stmt->execute([
                    $applicationId,
                    $application['scholarship_id'],
                    $application['student_id'],
                    $data['amount_awarded'] ?? 0
                ]);
            }
            
            ApiConfig::response(null, 'Application updated successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error updating application', 500);
        }
    }
    
    // Get provider dashboard data
    public function getDashboard() {
        try {
            $scholarshipModel = new Scholarship();
            $scholarships = $scholarshipModel->getProviderScholarships($this->user['id']);
            
            $totalScholarships = count($scholarships);
            $activeScholarships = count(array_filter($scholarships, fn($s) => $s['status'] === 'Active'));
            
            // Get statistics
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM scholarship_applications sa
                JOIN scholarships s ON sa.scholarship_id = s.id
                WHERE s.provider_id = ?
            ");
            $stmt->execute([$this->user['id']]);
            $totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM scholarship_awards saw
                JOIN scholarships s ON saw.scholarship_id = s.id
                WHERE s.provider_id = ?
            ");
            $stmt->execute([$this->user['id']]);
            $totalAwards = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(saw.amount_awarded), 0) as total
                FROM scholarship_awards saw
                JOIN scholarships s ON saw.scholarship_id = s.id
                WHERE s.provider_id = ?
            ");
            $stmt->execute([$this->user['id']]);
            $totalAwarded = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $dashboardData = [
                'statistics' => [
                    'total_scholarships' => $totalScholarships,
                    'active_scholarships' => $activeScholarships,
                    'total_applications' => intval($totalApplications),
                    'total_awards' => intval($totalAwards),
                    'total_awarded' => floatval($totalAwarded)
                ],
                'recent_scholarships' => array_slice($scholarships, 0, 5),
                'recent_applications' => [] // Would need to implement this query
            ];
            
            ApiConfig::response($dashboardData, 'Dashboard data retrieved successfully');
        } catch (Exception $e) {
            ApiConfig::response(null, 'Error retrieving dashboard data', 500);
        }
    }
}