<?php
/**
 * Partnership Request Model
 * Handles sequential approval workflow for provider-school partnerships
 */

require_once __DIR__ . "/../../config/database.php";

class PartnershipRequest {
    private $conn;
    
    // Workflow states
    const STATE_PENDING = 'PENDING';
    const STATE_COMMITTEE_REVIEW = 'COMMITTEE_REVIEW';
    const STATE_VP_REVIEW = 'VP_REVIEW';
    const STATE_PRESIDENT_REVIEW = 'PRESIDENT_REVIEW';
    const STATE_APPROVED = 'APPROVED';
    const STATE_REJECTED = 'REJECTED';
    
    // Approval stages
    const STAGE_COMMITTEE = 'committee';
    const STAGE_VP = 'vp';
    const STAGE_PRESIDENT = 'president';
    
    public function __construct() {
        $this->conn = (new Database())->connect();
    }
    
    /**
     * Submit a new partnership request
     */
    public function submitRequest($data) {
        try {
            $this->conn->beginTransaction();
            
            // Insert partnership request
            $stmt = $this->conn->prepare("
                INSERT INTO partnership_requests (
                    provider_id, school_id, organization_name, organization_type,
                    contact_person, contact_email, contact_phone,
                    partnership_title, partnership_description,
                    proposed_scholarship_amount, proposed_scholarship_slots,
                    partnership_duration_years, registration_documents,
                    financial_statements, partnership_proposal_document
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['provider_id'],
                $data['school_id'],
                $data['organization_name'],
                $data['organization_type'],
                $data['contact_person'],
                $data['contact_email'],
                $data['contact_phone'] ?? null,
                $data['partnership_title'],
                $data['partnership_description'],
                $data['proposed_scholarship_amount'],
                $data['proposed_scholarship_slots'],
                $data['partnership_duration_years'] ?? 1,
                json_encode($data['registration_documents'] ?? []),
                json_encode($data['financial_statements'] ?? []),
                $data['partnership_proposal_document'] ?? null
            ]);
            
            $requestId = $this->conn->lastInsertId();
            
            // Initialize workflow
            $this->initializeWorkflow($requestId);
            
            $this->conn->commit();
            return $requestId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Initialize the approval workflow
     */
    private function initializeWorkflow($requestId) {
        // Get school roles for email-based approval
        $request = $this->getRequestById($requestId);
        $schoolRoles = $this->getSchoolRoles($request['school_id']);
        
        if (empty($schoolRoles[self::STAGE_COMMITTEE])) {
            throw new Exception("Committee email not configured for this school");
        }
        
        // Update request to committee review stage
        $stmt = $this->conn->prepare("
            UPDATE partnership_requests 
            SET current_stage = ?, committee_notified_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([self::STATE_COMMITTEE_REVIEW, $requestId]);
        
        // Create committee approval stage
        $this->createApprovalStage($requestId, self::STATE_COMMITTEE_REVIEW, 1, $schoolRoles[self::STAGE_COMMITTEE]);
        
        // Log event
        $this->logEvent($requestId, 'REQUEST_SUBMITTED', self::STATE_COMMITTEE_REVIEW, 
                       self::STAGE_COMMITTEE, 'Partnership request submitted and committee notified');
        
        // Send committee email
        $this->sendApprovalEmail($requestId, self::STAGE_COMMITTEE);
    }
    
    /**
     * Get school roles for email-based approval
     */
    private function getSchoolRoles($schoolId) {
        // Return predefined email addresses for approval workflow
        // These are email addresses, not user accounts
        return [
            self::STAGE_COMMITTEE => [
                'email_address' => 'committee@davaocentralcollege.edu.ph',
                'role_name' => 'Scholarship Committee',
                'role_title' => 'Committee Chairperson'
            ],
            self::STAGE_VP => [
                'email_address' => 'vp@davaocentralcollege.edu.ph', 
                'role_name' => 'Vice President',
                'role_title' => 'Vice President for Academic Affairs'
            ],
            self::STAGE_PRESIDENT => [
                'email_address' => 'president@davaocentralcollege.edu.ph',
                'role_name' => 'School President',
                'role_title' => 'School President'
            ]
        ];
    }
        
        // Create committee approval stage
        $this->createApprovalStage($requestId, self::STATE_COMMITTEE_REVIEW, 1, $schoolRoles[self::STAGE_COMMITTEE]);
        
        // Log event
        $this->logEvent($requestId, 'REQUEST_SUBMITTED', self::STATE_COMMITTEE_REVIEW, 
                       self::STAGE_COMMITTEE, 'Partnership request submitted and committee notified');
        
        // Send committee email
        $this->sendApprovalEmail($requestId, self::STAGE_COMMITTEE);
    }
    
    /**
     * Create an approval stage
     */
    private function createApprovalStage($requestId, $stageName, $stageOrder, $roleData) {
        // Generate secure token
        $token = $this->generateSecureToken($requestId, $stageName);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $this->conn->prepare("
            INSERT INTO approval_stages (
                partnership_request_id, stage_name, stage_order,
                recipient_email, recipient_role, approval_token, token_expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $requestId,
            $stageName,
            $stageOrder,
            $roleData['email_address'],
            $roleData['role_name'],
            $token,
            $expiresAt
        ]);
    }
    
    /**
     * Process approval/rejection from email link
     */
    public function processApproval($token, $decision, $notes = null, $ipAddress = null) {
        try {
            $this->conn->beginTransaction();
            
            // Validate token
            $stage = $this->validateToken($token);
            if (!$stage) {
                throw new Exception("Invalid or expired approval token");
            }
            
            // Mark token as used
            $stmt = $this->conn->prepare("
                UPDATE approval_stages 
                SET decision = ?, decided_at = NOW(), decision_notes = ?,
                    decided_by_ip = ?, token_used = 1, token_used_at = NOW()
                WHERE approval_token = ?
            ");
            $stmt->execute([$decision, $notes, $ipAddress, $token]);
            
            $requestId = $stage['partnership_request_id'];
            $currentStage = $stage['stage_name'];
            
            if ($decision === 'REJECTED') {
                // Handle rejection
                $this->handleRejection($requestId, $currentStage, $notes);
            } else {
                // Handle approval - move to next stage
                $this->handleApproval($requestId, $stage);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Handle rejection at any stage
     */
    private function handleRejection($requestId, $stageName, $reason) {
        // Update request status
        $stmt = $this->conn->prepare("
            UPDATE partnership_requests 
            SET current_stage = ?, rejected_by_stage = ?, 
                rejection_reason = ?, final_decision_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([self::STATE_REJECTED, $stageName, $reason, $requestId]);
        
        // Log rejection
        $this->logEvent($requestId, 'STAGE_REJECTED', $stageName, 
                       $this->getStageRole($stageName), "Request rejected at {$stageName} stage");
        
        // Send rejection notification to provider
        $this->sendRejectionNotification($requestId, $stageName, $reason);
    }
    
    /**
     * Handle approval and move to next stage
     */
    private function handleApproval($requestId, $currentStage) {
        $stageOrder = $currentStage['stage_order'];
        $nextStage = $this->getNextStage($stageOrder);
        
        if ($nextStage === self::STATE_APPROVED) {
            // Final approval
            $this->handleFinalApproval($requestId);
        } else {
            // Move to next approval stage
            $this->moveToNextStage($requestId, $nextStage, $stageOrder + 1);
        }
        
        // Log approval
        $this->logEvent($requestId, 'STAGE_APPROVED', $currentStage['stage_name'],
                       $currentStage['recipient_role'], "Stage approved, moving to next stage");
    }
    
    /**
     * Handle final approval
     */
    private function handleFinalApproval($requestId) {
        // Update request to approved
        $stmt = $this->conn->prepare("
            UPDATE partnership_requests 
            SET current_stage = ?, partnership_active = 1,
                partnership_start_date = CURDATE(), final_decision_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([self::STATE_APPROVED, $requestId]);
        
        // Log completion
        $this->logEvent($requestId, 'WORKFLOW_COMPLETED', null, null, 
                       'Partnership request fully approved and activated');
        
        // Send approval notification to provider
        $this->sendApprovalNotification($requestId);
    }
    
    /**
     * Move to next approval stage
     */
    private function moveToNextStage($requestId, $nextStage, $nextOrder) {
        $request = $this->getRequestById($requestId);
        $schoolRoles = $this->getSchoolRoles($request['school_id']);
        
        $roleKey = $this->getStageRoleKey($nextStage);
        if (empty($schoolRoles[$roleKey])) {
            throw new Exception("Email not configured for {$roleKey} role");
        }
        
        // Update request stage
        $stmt = $this->conn->prepare("
            UPDATE partnership_requests 
            SET current_stage = ?
            WHERE id = ?
        ");
        $stmt->execute([$nextStage, $requestId]);
        
        // Create next approval stage
        $this->createApprovalStage($requestId, $nextStage, $nextOrder, $schoolRoles[$roleKey]);
        
        // Send email to next approver
        $this->sendApprovalEmail($requestId, $roleKey);
    }
    
    /**
     * Get next stage based on current order
     */
    private function getNextStage($currentOrder) {
        switch ($currentOrder) {
            case 1: return self::STATE_VP_REVIEW;
            case 2: return self::STATE_PRESIDENT_REVIEW;
            case 3: return self::STATE_APPROVED;
            default: throw new Exception("Invalid stage order");
        }
    }
    
    /**
     * Get stage role key for email lookup
     */
    private function getStageRoleKey($stage) {
        switch ($stage) {
            case self::STATE_COMMITTEE_REVIEW: return self::STAGE_COMMITTEE;
            case self::STATE_VP_REVIEW: return self::STAGE_VP;
            case self::STATE_PRESIDENT_REVIEW: return self::STAGE_PRESIDENT;
            default: throw new Exception("Invalid stage");
        }
    }
    
    /**
     * Validate approval token
     */
    private function validateToken($token) {
        $stmt = $this->conn->prepare("
            SELECT * FROM approval_stages 
            WHERE approval_token = ? 
            AND token_expires_at > NOW() 
            AND token_used = 0
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate secure approval token
     */
    private function generateSecureToken($requestId, $stage) {
        $data = $requestId . $stage . microtime(true) . random_bytes(16);
        return hash('sha256', $data);
    }
    
    /**
     * Get school roles configuration
     */
    private function getSchoolRoles($schoolId) {
        $stmt = $this->conn->prepare("
            SELECT role_name, email_address, contact_person, approval_order
            FROM school_roles 
            WHERE school_id = ? AND is_active = 1
            ORDER BY approval_order
        ");
        $stmt->execute([$schoolId]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['role_name']] = $role;
        }
        
        return $roleMap;
    }
    
    /**
     * Send approval email
     */
    private function sendApprovalEmail($requestId, $role) {
        $request = $this->getRequestById($requestId);
        $stage = $this->getCurrentStage($requestId);
        
        if (!$stage) {
            throw new Exception("No active stage found for request");
        }
        
        // Get email template
        $template = $this->getEmailTemplate($role . '_approval');
        
        // Generate approval/rejection links
        $approveLink = $this->generateApprovalLink($stage['approval_token'], 'APPROVED');
        $rejectLink = $this->generateApprovalLink($stage['approval_token'], 'REJECTED');
        
        // Replace template variables
        $subject = str_replace('{{organization_name}}', $request['organization_name'], $template['subject_template']);
        $body = str_replace([
            '{{organization_name}}',
            '{{partnership_title}}',
            '{{proposed_amount}}',
            '{{approve_link}}',
            '{{reject_link}}'
        ], [
            $request['organization_name'],
            $request['partnership_title'],
            number_format($request['proposed_scholarship_amount'], 2),
            $approveLink,
            $rejectLink
        ], $template['body_template']);
        
        // Send email (integrate with your email system)
        $this->sendEmail($stage['recipient_email'], $subject, $body);
        
        // Update email sent timestamp
        $stmt = $this->conn->prepare("
            UPDATE approval_stages 
            SET email_sent_at = NOW(), email_subject = ?
            WHERE id = ?
        ");
        $stmt->execute([$subject, $stage['id']]);
        
        // Log email sent
        $this->logEvent($requestId, 'EMAIL_SENT', $stage['stage_name'], 
                       $stage['recipient_role'], "Approval email sent to {$stage['recipient_email']}");
    }
    
    /**
     * Generate approval link
     */
    private function generateApprovalLink($token, $action) {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "https://{$baseUrl}/api/partnerships/approve/{$token}?action={$action}";
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($templateName) {
        $stmt = $this->conn->prepare("
            SELECT * FROM email_templates 
            WHERE template_name = ? AND is_active = 1
        ");
        $stmt->execute([$templateName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send email (placeholder - integrate with your email service)
     */
    private function sendEmail($to, $subject, $body) {
        // Integrate with PHPMailer or your email service
        // For now, just log the email
        error_log("EMAIL TO: {$to}, SUBJECT: {$subject}");
        return true;
    }
    
    /**
     * Get partnership request by ID
     */
    public function getRequestById($id) {
        $stmt = $this->conn->prepare("
            SELECT pr.*, s.school_name, u.fullname as provider_name, u.email as provider_email
            FROM partnership_requests pr
            JOIN schools s ON pr.school_id = s.id
            JOIN users u ON pr.provider_id = u.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get current active stage
     */
    private function getCurrentStage($requestId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM approval_stages 
            WHERE partnership_request_id = ? 
            AND decision = 'PENDING'
            ORDER BY stage_order DESC
            LIMIT 1
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get provider's partnership requests
     */
    public function getProviderRequests($providerId) {
        $stmt = $this->conn->prepare("
            SELECT pr.*, s.school_name,
                   CASE 
                       WHEN pr.current_stage = 'PENDING' THEN 0
                       WHEN pr.current_stage = 'COMMITTEE_REVIEW' THEN 25
                       WHEN pr.current_stage = 'VP_REVIEW' THEN 50
                       WHEN pr.current_stage = 'PRESIDENT_REVIEW' THEN 75
                       WHEN pr.current_stage = 'APPROVED' THEN 100
                       ELSE 0
                   END as progress_percentage
            FROM partnership_requests pr
            JOIN schools s ON pr.school_id = s.id
            WHERE pr.provider_id = ?
            ORDER BY pr.submitted_at DESC
        ");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get requests for school admin review
     */
    public function getSchoolRequests($schoolId, $stage = null) {
        $sql = "
            SELECT pr.*, u.fullname as provider_name, u.email as provider_email,
                   DATEDIFF(NOW(), pr.submitted_at) as days_pending
            FROM partnership_requests pr
            JOIN users u ON pr.provider_id = u.id
            WHERE pr.school_id = ?
        ";
        
        $params = [$schoolId];
        
        if ($stage) {
            $sql .= " AND pr.current_stage = ?";
            $params[] = $stage;
        }
        
        $sql .= " ORDER BY pr.submitted_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log workflow events
     */
    private function logEvent($requestId, $eventType, $stageName = null, $role = null, $description = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO approval_logs (
                partnership_request_id, event_type, stage_name,
                recipient_role, event_description, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        return $stmt->execute([
            $requestId, $eventType, $stageName, 
            $role, $description, $ipAddress
        ]);
    }
    
    /**
     * Get audit logs for a request
     */
    public function getAuditLogs($requestId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM approval_logs 
            WHERE partnership_request_id = ?
            ORDER BY logged_at DESC
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send rejection notification to provider
     */
    private function sendRejectionNotification($requestId, $stage, $reason) {
        $request = $this->getRequestById($requestId);
        
        $subject = "Partnership Request Rejected - {$request['organization_name']}";
        $body = "Dear {$request['provider_name']},\n\n";
        $body .= "Your partnership request '{$request['partnership_title']}' has been rejected at the {$stage} stage.\n\n";
        $body .= "Reason: {$reason}\n\n";
        $body .= "You may submit a new request after addressing the concerns mentioned above.\n\n";
        $body .= "Best regards,\n{$request['school_name']}";
        
        $this->sendEmail($request['provider_email'], $subject, $body);
    }
    
    /**
     * Send approval notification to provider
     */
    private function sendApprovalNotification($requestId) {
        $request = $this->getRequestById($requestId);
        
        $subject = "Partnership Request Approved - {$request['organization_name']}";
        $body = "Dear {$request['provider_name']},\n\n";
        $body .= "Congratulations! Your partnership request '{$request['partnership_title']}' has been fully approved.\n\n";
        $body .= "You can now create and publish scholarship programs through our platform.\n\n";
        $body .= "Partnership Details:\n";
        $body .= "- Organization: {$request['organization_name']}\n";
        $body .= "- School Partner: {$request['school_name']}\n";
        $body .= "- Proposed Amount: ₱" . number_format($request['proposed_scholarship_amount'], 2) . "\n";
        $body .= "- Proposed Slots: {$request['proposed_scholarship_slots']}\n\n";
        $body .= "Welcome to the ISKOLAR partnership program!\n\n";
        $body .= "Best regards,\n{$request['school_name']}";
        
        $this->sendEmail($request['provider_email'], $subject, $body);
    }
    
    /**
     * Get stage role from stage name
     */
    private function getStageRole($stageName) {
        switch ($stageName) {
            case self::STATE_COMMITTEE_REVIEW: return self::STAGE_COMMITTEE;
            case self::STATE_VP_REVIEW: return self::STAGE_VP;
            case self::STATE_PRESIDENT_REVIEW: return self::STAGE_PRESIDENT;
            default: return null;
        }
    }
}
?>