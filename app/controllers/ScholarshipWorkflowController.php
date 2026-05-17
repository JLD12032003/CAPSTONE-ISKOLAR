<?php
/**
 * Scholarship Workflow Controller
 * Handles scholarship approval workflow operations
 */

require_once __DIR__ . '/../models/ScholarshipWorkflow.php';
require_once __DIR__ . '/../models/Scholarship.php';
require_once __DIR__ . '/../../config/database.php';

class ScholarshipWorkflowController {
    private $workflowModel;
    private $scholarshipModel;
    
    public function __construct() {
        $this->workflowModel = new ScholarshipWorkflow();
        $this->scholarshipModel = new Scholarship();
    }
    
    /**
     * Submit scholarship for approval workflow
     */
    public function submitForApproval() {
        try {
            // Validate user is logged in as provider
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
                throw new Exception('Unauthorized access');
            }
            
            $scholarshipId = intval($_POST['scholarship_id'] ?? 0);
            
            // If no scholarship_id in POST, get the last created scholarship by this provider
            if (!$scholarshipId) {
                $database = new Database();
                $conn = $database->connect();
                $stmt = $conn->prepare("
                    SELECT id FROM scholarships 
                    WHERE provider_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $scholarshipId = $result ? $result['id'] : 0;
            }
            
            if (!$scholarshipId) {
                throw new Exception('Scholarship ID is required');
            }
            
            // Verify provider owns this scholarship
            $scholarship = $this->scholarshipModel->getById($scholarshipId);
            if (!$scholarship || $scholarship['provider_id'] != $_SESSION['user_id']) {
                throw new Exception('Scholarship not found or access denied');
            }
            
            // Submit for approval
            $result = $this->workflowModel->submitScholarshipForApproval($scholarshipId);
            
            return [
                'success' => true,
                'message' => 'Scholarship submitted for approval workflow successfully',
                'scholarship_id' => $scholarshipId
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
    public function processEmailApproval($token, $action, $notes = null) {
        try {
            // Validate action
            if (!in_array($action, ['APPROVED', 'REJECTED'])) {
                throw new Exception('Invalid action');
            }
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Process the approval
            $result = $this->workflowModel->processApproval($token, $action, $notes, $ipAddress);
            
            if ($result) {
                $message = $action === 'APPROVED' ? 'Scholarship approved successfully' : 'Scholarship rejected';
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
     * Get scholarship workflow status
     */
    public function getWorkflowStatus($scholarshipId) {
        try {
            // Check access permissions
            if (!$this->canAccessScholarship($scholarshipId)) {
                throw new Exception('Access denied');
            }
            
            $summary = $this->workflowModel->getWorkflowSummary($scholarshipId);
            $auditLog = $this->workflowModel->getAuditLog($scholarshipId);
            
            return [
                'success' => true,
                'summary' => $summary,
                'audit_log' => $auditLog
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get provider's scholarships with workflow status
     */
    public function getProviderScholarships() {
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
                throw new Exception('Unauthorized access');
            }
            
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("
                SELECT s.*, sws.total_stages, sws.approved_stages, sws.rejected_stages, sws.pending_stages
                FROM scholarships s
                LEFT JOIN scholarship_workflow_summary sws ON s.id = sws.id
                WHERE s.provider_id = ?
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'scholarships' => $scholarships
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get admin's pending approvals
     */
    public function getAdminPendingApprovals() {
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
                throw new Exception('Unauthorized access');
            }
            
            $database = new Database();
            $conn = $database->connect();
            
            // Get user's school
            $userStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ?");
            $userStmt->execute([$_SESSION['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get pending scholarships for this school
            $stmt = $conn->prepare("
                SELECT s.*, u.fullname as provider_name, pp.organization_name,
                       sas.stage_name, sas.created_at as stage_created_at
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN scholarship_approval_stages sas ON s.id = sas.scholarship_id 
                    AND sas.decision IS NULL
                WHERE s.school_id = ? 
                    AND s.workflow_status IN (
                        'PENDING_SCHOOL_ADMIN_REVIEW',
                        'PENDING_COMMITTEE_REVIEW', 
                        'PENDING_VP_REVIEW',
                        'PENDING_PRESIDENT_REVIEW'
                    )
                ORDER BY s.submitted_at DESC
            ");
            $stmt->execute([$user['school_id']]);
            $pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'pending_approvals' => $pendingApprovals
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get scholarship details for approval
     */
    public function getScholarshipForApproval($token) {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            // Get scholarship details by token
            $stmt = $conn->prepare("
                SELECT s.*, u.fullname as provider_name, pp.organization_name,
                       sch.school_name, sas.stage_name, sas.recipient_role,
                       sas.token_expires_at
                FROM scholarship_approval_stages sas
                JOIN scholarships s ON sas.scholarship_id = s.id
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN schools sch ON s.school_id = sch.id
                WHERE sas.approval_token = ? 
                    AND sas.token_expires_at > NOW() 
                    AND sas.token_used = 0
            ");
            $stmt->execute([$token]);
            $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$scholarship) {
                throw new Exception('Invalid or expired approval token');
            }
            
            return [
                'success' => true,
                'scholarship' => $scholarship
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get workflow statistics for dashboard
     */
    public function getWorkflowStatistics($schoolId = null) {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            $whereClause = $schoolId ? "WHERE s.school_id = ?" : "";
            $params = $schoolId ? [$schoolId] : [];
            
            $stmt = $conn->prepare("
                SELECT 
                    s.workflow_status,
                    COUNT(*) as count
                FROM scholarships s
                {$whereClause}
                GROUP BY s.workflow_status
            ");
            $stmt->execute($params);
            $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent activity
            $recentStmt = $conn->prepare("
                SELECT sal.*, s.title as scholarship_title
                FROM scholarship_audit_log sal
                JOIN scholarships s ON sal.scholarship_id = s.id
                {$whereClause}
                ORDER BY sal.created_at DESC
                LIMIT 10
            ");
            $recentStmt->execute($params);
            $recentActivity = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'statistics' => $statistics,
                'recent_activity' => $recentActivity
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if user can access scholarship
     */
    private function canAccessScholarship($scholarshipId) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $database = new Database();
        $conn = $database->connect();
        
        if ($_SESSION['user_type'] === 'provider') {
            // Provider can access their own scholarships
            $stmt = $conn->prepare("SELECT id FROM scholarships WHERE id = ? AND provider_id = ?");
            $stmt->execute([$scholarshipId, $_SESSION['user_id']]);
            return $stmt->rowCount() > 0;
        } elseif ($_SESSION['user_type'] === 'admin') {
            // Admin can access scholarships for their school
            $userStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ?");
            $userStmt->execute([$_SESSION['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $stmt = $conn->prepare("SELECT id FROM scholarships WHERE id = ? AND school_id = ?");
                $stmt->execute([$scholarshipId, $user['school_id']]);
                return $stmt->rowCount() > 0;
            }
        }
        
        return false;
    }
}
?>