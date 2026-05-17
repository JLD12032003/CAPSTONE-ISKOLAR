<?php
/**
 * Partnership Controller
 * Handles partnership request workflow and approval process
 */

require_once __DIR__ . '/../models/PartnershipRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../config/database.php';

class PartnershipController {
    private $partnershipModel;
    private $userModel;
    
    public function __construct() {
        $this->partnershipModel = new PartnershipRequest();
        $this->userModel = new User();
    }
    
    /**
     * Submit a new partnership request
     */
    public function submitRequest() {
        try {
            // Validate user is logged in as provider
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
                throw new Exception('Unauthorized access');
            }
            
            // Validate required fields
            $requiredFields = [
                'school_id', 'organization_name', 'organization_type',
                'contact_person', 'contact_email', 'partnership_title',
                'partnership_description', 'proposed_scholarship_amount',
                'proposed_scholarship_slots'
            ];
            
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '{$field}' is required");
                }
            }
            
            // Prepare data
            $data = [
                'provider_id' => $_SESSION['user_id'],
                'school_id' => intval($_POST['school_id']),
                'organization_name' => trim($_POST['organization_name']),
                'organization_type' => $_POST['organization_type'],
                'contact_person' => trim($_POST['contact_person']),
                'contact_email' => trim($_POST['contact_email']),
                'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                'partnership_title' => trim($_POST['partnership_title']),
                'partnership_description' => trim($_POST['partnership_description']),
                'proposed_scholarship_amount' => floatval($_POST['proposed_scholarship_amount']),
                'proposed_scholarship_slots' => intval($_POST['proposed_scholarship_slots']),
                'partnership_duration_years' => intval($_POST['partnership_duration_years'] ?? 1)
            ];
            
            // Validate email format
            if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Validate amounts
            if ($data['proposed_scholarship_amount'] <= 0) {
                throw new Exception('Scholarship amount must be greater than 0');
            }
            
            if ($data['proposed_scholarship_slots'] <= 0) {
                throw new Exception('Number of slots must be greater than 0');
            }
            
            // Handle file uploads
            $data['registration_documents'] = $this->handleFileUploads('registration_documents');
            $data['financial_statements'] = $this->handleFileUploads('financial_statements');
            
            if (isset($_FILES['partnership_proposal']) && $_FILES['partnership_proposal']['error'] === UPLOAD_ERR_OK) {
                $data['partnership_proposal_document'] = $this->handleSingleFileUpload('partnership_proposal');
            }
            
            // Submit request
            $requestId = $this->partnershipModel->submitRequest($data);
            
            return [
                'success' => true,
                'message' => 'Partnership request submitted successfully',
                'request_id' => $requestId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process approval from email link
     */
    public function processApproval($token, $action) {
        try {
            // Validate action
            if (!in_array($action, ['APPROVED', 'REJECTED'])) {
                throw new Exception('Invalid action');
            }
            
            // Get notes if provided
            $notes = $_POST['notes'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Process the approval
            $result = $this->partnershipModel->processApproval($token, $action, $notes, $ipAddress);
            
            if ($result) {
                $message = $action === 'APPROVED' ? 'Request approved successfully' : 'Request rejected';
                return [
                    'success' => true,
                    'message' => $message
                ];
            } else {
                throw new Exception('Failed to process approval');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get partnership request details
     */
    public function getRequest($id) {
        try {
            $request = $this->partnershipModel->getRequestById($id);
            
            if (!$request) {
                throw new Exception('Request not found');
            }
            
            // Check access permissions
            if (!$this->canAccessRequest($request)) {
                throw new Exception('Access denied');
            }
            
            return [
                'success' => true,
                'data' => $request
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get provider's partnership requests
     */
    public function getProviderRequests() {
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
                throw new Exception('Unauthorized access');
            }
            
            $requests = $this->partnershipModel->getProviderRequests($_SESSION['user_id']);
            
            return [
                'success' => true,
                'data' => $requests
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get school's partnership requests (for admin)
     */
    public function getSchoolRequests($schoolId = null, $stage = null) {
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
                throw new Exception('Unauthorized access');
            }
            
            // If no school ID provided, use admin's school
            if (!$schoolId) {
                $user = $this->userModel->findById($_SESSION['user_id']);
                $schoolId = $user['school_id'];
            }
            
            $requests = $this->partnershipModel->getSchoolRequests($schoolId, $stage);
            
            return [
                'success' => true,
                'data' => $requests
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get audit logs for a request
     */
    public function getAuditLogs($requestId) {
        try {
            $request = $this->partnershipModel->getRequestById($requestId);
            
            if (!$request) {
                throw new Exception('Request not found');
            }
            
            // Check access permissions
            if (!$this->canAccessRequest($request)) {
                throw new Exception('Access denied');
            }
            
            $logs = $this->partnershipModel->getAuditLogs($requestId);
            
            return [
                'success' => true,
                'data' => $logs
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if current user can access the request
     */
    private function canAccessRequest($request) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $userType = $_SESSION['user_type'];
        $userId = $_SESSION['user_id'];
        
        // Provider can access their own requests
        if ($userType === 'provider' && $request['provider_id'] == $userId) {
            return true;
        }
        
        // Admin can access requests for their school
        if ($userType === 'admin') {
            $user = $this->userModel->findById($userId);
            if ($user['school_id'] == $request['school_id']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle multiple file uploads
     */
    private function handleFileUploads($fieldName) {
        $uploadedFiles = [];
        
        if (isset($_FILES[$fieldName]) && is_array($_FILES[$fieldName]['name'])) {
            $files = $_FILES[$fieldName];
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadedFile = $this->processFileUpload([
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size' => $files['size'][$i]
                    ]);
                    
                    if ($uploadedFile) {
                        $uploadedFiles[] = $uploadedFile;
                    }
                }
            }
        }
        
        return $uploadedFiles;
    }
    
    /**
     * Handle single file upload
     */
    private function handleSingleFileUpload($fieldName) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            return $this->processFileUpload($_FILES[$fieldName]);
        }
        
        return null;
    }
    
    /**
     * Process individual file upload
     */
    private function processFileUpload($file) {
        // Validate file
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds 10MB limit');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG');
        }
        
        // Generate unique filename
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadDir = __DIR__ . '/../../uploads/partnerships/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'original_name' => $file['name'],
                'stored_name' => $fileName,
                'file_path' => 'uploads/partnerships/' . $fileName,
                'file_size' => $file['size'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }
        
        throw new Exception('Failed to upload file: ' . $file['name']);
    }
    
    /**
     * Get partnership statistics
     */
    public function getStatistics($schoolId = null) {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            $whereClause = '';
            $params = [];
            
            if ($schoolId) {
                $whereClause = 'WHERE school_id = ?';
                $params[] = $schoolId;
            }
            
            // Get overall statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN current_stage = 'APPROVED' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN current_stage = 'REJECTED' THEN 1 ELSE 0 END) as rejected_requests,
                    SUM(CASE WHEN current_stage IN ('COMMITTEE_REVIEW', 'VP_REVIEW', 'PRESIDENT_REVIEW') THEN 1 ELSE 0 END) as pending_requests,
                    AVG(CASE WHEN current_stage = 'APPROVED' THEN DATEDIFF(final_decision_at, submitted_at) END) as avg_approval_days
                FROM partnership_requests 
                {$whereClause}
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get stage breakdown
            $stmt = $conn->prepare("
                SELECT 
                    current_stage,
                    COUNT(*) as count
                FROM partnership_requests 
                {$whereClause}
                GROUP BY current_stage
            ");
            $stmt->execute($params);
            $stageBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => [
                    'overall' => $stats,
                    'by_stage' => $stageBreakdown
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>