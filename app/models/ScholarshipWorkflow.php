<?php
/**
 * Scholarship Workflow Model
 * Handles multi-level email-based approval workflow for scholarship postings
 */

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../core/Mailer.php";

class ScholarshipWorkflow {
    private $conn;
    
    // Workflow states
    const STATE_DRAFT = 'DRAFT';
    const STATE_PENDING_SCHOOL_ADMIN = 'PENDING_SCHOOL_ADMIN_REVIEW';
    const STATE_PENDING_COMMITTEE = 'PENDING_COMMITTEE_REVIEW';
    const STATE_PENDING_VP = 'PENDING_VP_REVIEW';
    const STATE_PENDING_PRESIDENT = 'PENDING_PRESIDENT_REVIEW';
    const STATE_APPROVED_FOR_PUBLICATION = 'APPROVED_FOR_PUBLICATION';
    const STATE_REJECTED_BY_SCHOOL_ADMIN = 'REJECTED_BY_SCHOOL_ADMIN';
    const STATE_REJECTED_BY_COMMITTEE = 'REJECTED_BY_COMMITTEE';
    const STATE_REJECTED_BY_VP = 'REJECTED_BY_VP';
    const STATE_REJECTED_BY_PRESIDENT = 'REJECTED_BY_PRESIDENT';
    
    // Approval stages
    const STAGE_SCHOOL_ADMIN = 'SCHOOL_ADMIN';
    const STAGE_COMMITTEE = 'COMMITTEE';
    const STAGE_VP = 'VP';
    const STAGE_PRESIDENT = 'PRESIDENT';
    
    public function __construct() {
        $this->conn = (new Database())->connect();
    }
    
    /**
     * Submit scholarship for approval workflow
     */
    public function submitScholarshipForApproval($scholarshipId) {
        try {
            $this->conn->beginTransaction();
            
            // Get scholarship details
            $scholarship = $this->getScholarshipById($scholarshipId);
            if (!$scholarship) {
                throw new Exception("Scholarship not found");
            }
            
            // Verify scholarship is in draft state
            if ($scholarship['workflow_status'] !== self::STATE_DRAFT) {
                throw new Exception("Scholarship is not in draft state");
            }
            
            // Generate Letter of Agreement (LOA)
            $loaDocument = $this->generateLOA($scholarship);
            
            // Update scholarship status
            $stmt = $this->conn->prepare("
                UPDATE scholarships 
                SET workflow_status = ?, submitted_at = NOW(), loa_document = ?
                WHERE id = ?
            ");
            $stmt->execute([self::STATE_PENDING_SCHOOL_ADMIN, $loaDocument, $scholarshipId]);
            
            // Initialize approval workflow
            $this->initializeApprovalWorkflow($scholarshipId);
            
            // Log the submission
            $this->logAuditEvent($scholarshipId, 'SUBMITTED_FOR_REVIEW', null, 'PROVIDER', 
                               $scholarship['provider_email'], self::STATE_DRAFT, 
                               self::STATE_PENDING_SCHOOL_ADMIN, 'Scholarship submitted for approval workflow');
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Initialize the multi-level approval workflow
     */
    private function initializeApprovalWorkflow($scholarshipId) {
        $scholarship = $this->getScholarshipById($scholarshipId);
        $approvalConfig = $this->getSchoolApprovalConfig($scholarship['school_id']);
        
        if (!$approvalConfig) {
            throw new Exception("School approval configuration not found");
        }
        
        // Create school admin approval stage (Stage 1)
        $this->createApprovalStage($scholarshipId, self::STAGE_SCHOOL_ADMIN, 1, 
                                 $approvalConfig['admin_email'], 'School Administrator');
        
        // Send initial email to school admin
        $this->sendApprovalEmail($scholarshipId, self::STAGE_SCHOOL_ADMIN);
        
        // Log workflow initialization
        $this->logAuditEvent($scholarshipId, 'EMAIL_SENT', self::STAGE_SCHOOL_ADMIN, 
                           'SYSTEM', null, null, null, 
                           'Approval email sent to school administrator');
    }
    
    /**
     * Process approval/rejection from email link
     */
    public function processApproval($token, $decision, $notes = null, $ipAddress = null) {
        try {
            $this->conn->beginTransaction();
            
            // Validate token and get stage info
            $stage = $this->validateApprovalToken($token);
            if (!$stage) {
                throw new Exception("Invalid or expired approval token");
            }
            
            // Mark token as used
            $stmt = $this->conn->prepare("
                UPDATE scholarship_approval_stages 
                SET decision = ?, decided_at = NOW(), decision_notes = ?,
                    decided_by_ip = ?, token_used = 1, token_used_at = NOW()
                WHERE approval_token = ?
            ");
            $stmt->execute([$decision, $notes, $ipAddress, $token]);
            
            $scholarshipId = $stage['scholarship_id'];
            $stageName = $stage['stage_name'];
            
            // Log the decision
            $this->logAuditEvent($scholarshipId, 'STAGE_' . $decision, $stageName, 
                               $stage['recipient_role'], $stage['recipient_email'], 
                               null, null, $notes);
            
            if ($decision === 'REJECTED') {
                $this->handleRejection($scholarshipId, $stageName, $notes);
            } else {
                $this->handleApproval($scholarshipId, $stage);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Handle approval and move to next stage
     */
    private function handleApproval($scholarshipId, $stage) {
        $stageName = $stage['stage_name'];
        $nextStage = $this->getNextStage($stageName);
        
        if ($nextStage) {
            // Move to next stage
            $newStatus = $this->getStatusForStage($nextStage);
            $this->updateScholarshipStatus($scholarshipId, $newStatus);
            
            // Create next approval stage
            $approvalConfig = $this->getSchoolApprovalConfigByScholarship($scholarshipId);
            $this->createNextApprovalStage($scholarshipId, $nextStage, $approvalConfig);
            
            // Send email to next approver
            $this->sendApprovalEmail($scholarshipId, $nextStage);
            
            $this->logAuditEvent($scholarshipId, 'FORWARDED_TO_NEXT_STAGE', $nextStage, 
                               'SYSTEM', null, null, $newStatus, 
                               "Forwarded to {$nextStage} for approval");
        } else {
            // Final approval - publish scholarship
            $this->publishScholarship($scholarshipId);
        }
    }
    
    /**
     * Handle rejection at any stage
     */
    private function handleRejection($scholarshipId, $stageName, $reason) {
        $rejectionStatus = 'REJECTED_BY_' . $stageName;
        
        // Update scholarship status
        $stmt = $this->conn->prepare("
            UPDATE scholarships 
            SET workflow_status = ?, rejection_reason = ?, rejection_stage = ?
            WHERE id = ?
        ");
        $stmt->execute([$rejectionStatus, $reason, $stageName, $scholarshipId]);
        
        // Send rejection notification to provider
        $this->sendRejectionNotification($scholarshipId, $stageName, $reason);
        
        $this->logAuditEvent($scholarshipId, 'STAGE_REJECTED', $stageName, 
                           null, null, null, $rejectionStatus, 
                           "Scholarship rejected at {$stageName} stage: {$reason}");
    }
    
    /**
     * Publish scholarship after all approvals
     */
    private function publishScholarship($scholarshipId) {
        // Update scholarship status to published
        $stmt = $this->conn->prepare("
            UPDATE scholarships 
            SET workflow_status = ?, status = 'Active', published_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([self::STATE_APPROVED_FOR_PUBLICATION, $scholarshipId]);
        
        // Send publication notifications
        $this->sendPublicationNotifications($scholarshipId);
        
        $this->logAuditEvent($scholarshipId, 'PUBLISHED', null, 'SYSTEM', null, 
                           null, self::STATE_APPROVED_FOR_PUBLICATION, 
                           'Scholarship approved and published');
    }
    
    /**
     * Generate Letter of Agreement (LOA)
     */
    private function generateLOA($scholarship) {
        // Get LOA template
        $stmt = $this->conn->prepare("
            SELECT template_content, variables 
            FROM loa_templates 
            WHERE template_type = ? AND is_active = 1 
            ORDER BY created_at DESC LIMIT 1
        ");
        $templateType = $scholarship['scholarship_type'] ?? 'STANDARD';
        $stmt->execute([$templateType]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            // Fallback to STANDARD template if specific type not found
            $stmt = $this->conn->prepare("
                SELECT template_content, variables 
                FROM loa_templates 
                WHERE template_type = 'STANDARD' AND is_active = 1 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$template) {
            throw new Exception("No LOA template found in database");
        }
        
        // Replace template variables
        $loaContent = $template['template_content'];
        $variables = [
            '{provider_name}' => $scholarship['provider_name'] ?? 'Provider',
            '{organization_name}' => $scholarship['organization_name'] ?? 'Organization',
            '{school_name}' => $scholarship['school_name'] ?? 'School',
            '{scholarship_title}' => $scholarship['title'] ?? 'Scholarship',
            '{scholarship_amount}' => number_format($scholarship['amount'] ?? 0, 2),
            '{number_of_slots}' => $scholarship['slots'] ?? 1,
            '{duration_years}' => $scholarship['duration_years'] ?? 1,
            '{scholarship_type}' => $scholarship['scholarship_type'] ?? 'Standard',
            '{min_gpa}' => $scholarship['min_gwa'] ?? '2.5',
            '{application_period}' => ($scholarship['application_start'] ?? 'TBD') . ' to ' . ($scholarship['application_end'] ?? 'TBD')
        ];
        
        foreach ($variables as $placeholder => $value) {
            $loaContent = str_replace($placeholder, $value, $loaContent);
        }
        
        // Add partnership letter content
        if (!empty($scholarship['partnership_letter'])) {
            $loaContent = "PARTNERSHIP REQUEST LETTER:\n\n" . $scholarship['partnership_letter'] . "\n\n" . $loaContent;
        }
        
        // Save LOA document
        $fileName = 'loa_' . $scholarship['id'] . '_' . time() . '.txt';
        $filePath = __DIR__ . '/../../uploads/loa_documents/' . $fileName;
        
        // Create directory if it doesn't exist
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        if (file_put_contents($filePath, $loaContent) === false) {
            throw new Exception("Failed to save LOA document");
        }
        
        return $fileName;
    }
    
    /**
     * Create approval stage
     */
    private function createApprovalStage($scholarshipId, $stageName, $stageOrder, $recipientEmail, $recipientRole) {
        $token = $this->generateSecureToken($scholarshipId, $stageName);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $this->conn->prepare("
            INSERT INTO scholarship_approval_stages (
                scholarship_id, stage_name, stage_order, recipient_email, 
                recipient_role, approval_token, token_expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $scholarshipId, $stageName, $stageOrder, $recipientEmail, 
            $recipientRole, $token, $expiresAt
        ]);
    }
    
    /**
     * Send approval email
     */
    private function sendApprovalEmail($scholarshipId, $stageName) {
        $scholarship = $this->getScholarshipById($scholarshipId);
        $stage = $this->getCurrentStage($scholarshipId, $stageName);
        
        if (!$stage) {
            throw new Exception("Approval stage not found");
        }
        
        $approveUrl = $this->generateApprovalUrl($stage['approval_token'], 'APPROVED');
        $rejectUrl = $this->generateApprovalUrl($stage['approval_token'], 'REJECTED');
        
        $subject = "Scholarship Approval Required - {$scholarship['title']}";
        
        $emailBody = $this->generateApprovalEmailBody($scholarship, $stage, $approveUrl, $rejectUrl);
        
        // Send email
        Mailer::send($stage['recipient_email'], $subject, $emailBody);
        
        // Log email sent
        $this->logEmailSent($scholarshipId, $stageName, $stage['recipient_email'], 
                          'APPROVAL_REQUEST', $subject, $emailBody);
    }
    
    /**
     * Generate approval email body
     */
    private function generateApprovalEmailBody($scholarship, $stage, $approveUrl, $rejectUrl) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
                <h2 style='color: #0055ff; margin-bottom: 20px;'>Scholarship Approval Required</h2>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h3 style='margin: 0; color: #0d47a1;'>Scholarship Details</h3>
                </div>
                
                <table style='width: 100%; margin-bottom: 20px;'>
                    <tr><td><strong>Title:</strong></td><td>{$scholarship['title']}</td></tr>
                    <tr><td><strong>Provider:</strong></td><td>{$scholarship['organization_name']}</td></tr>
                    <tr><td><strong>Amount:</strong></td><td>₱" . number_format($scholarship['amount'], 2) . "</td></tr>
                    <tr><td><strong>Slots:</strong></td><td>{$scholarship['slots']}</td></tr>
                    <tr><td><strong>Type:</strong></td><td>{$scholarship['scholarship_type']}</td></tr>
                </table>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h4 style='margin: 0; color: #856404;'>Your Role: {$stage['recipient_role']}</h4>
                    <p style='margin: 5px 0 0 0;'>This scholarship requires your approval to proceed to the next stage.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$approveUrl}' style='background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-right: 10px; display: inline-block;'>APPROVE</a>
                    <a href='{$rejectUrl}' style='background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;'>REJECT</a>
                </div>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                    <p style='margin: 0; font-size: 12px; color: #666;'>
                        This approval link expires in 7 days. If you have questions, contact the school administrator at admin@davaocentralcollege.edu.ph
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get scholarship by ID with related data
     */
    private function getScholarshipById($scholarshipId) {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.fullname as provider_name, u.email as provider_email,
                   pp.organization_name, sch.school_name
            FROM scholarships s
            LEFT JOIN users u ON s.provider_id = u.id
            LEFT JOIN provider_profiles pp ON u.id = pp.user_id
            LEFT JOIN schools sch ON s.school_id = sch.id
            WHERE s.id = ?
        ");
        $stmt->execute([$scholarshipId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get school approval configuration
     */
    private function getSchoolApprovalConfig($schoolId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM school_approval_config WHERE school_id = ? AND is_active = 1
        ");
        $stmt->execute([$schoolId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate secure approval token
     */
    private function generateSecureToken($scholarshipId, $stageName) {
        return hash('sha256', $scholarshipId . $stageName . time() . random_bytes(32));
    }
    
    /**
     * Generate approval URL
     */
    private function generateApprovalUrl($token, $action) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/app/views/approval/scholarship_approval.php?token={$token}&action={$action}";
    }
    
    /**
     * Log audit event
     */
    private function logAuditEvent($scholarshipId, $actionType, $stageName, $actorRole, 
                                 $actorEmail, $previousStatus, $newStatus, $details) {
        $stmt = $this->conn->prepare("
            INSERT INTO scholarship_audit_log (
                scholarship_id, action_type, stage_name, actor_role, actor_email,
                previous_status, new_status, action_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $scholarshipId, $actionType, $stageName, $actorRole, $actorEmail,
            $previousStatus, $newStatus, $details
        ]);
    }
    
    /**
     * Log email sent
     */
    private function logEmailSent($scholarshipId, $stageName, $recipientEmail, $emailType, $subject, $body) {
        $stmt = $this->conn->prepare("
            INSERT INTO scholarship_email_log (
                scholarship_id, stage_name, recipient_email, email_type, 
                email_subject, email_body
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $scholarshipId, $stageName, $recipientEmail, $emailType, $subject, $body
        ]);
    }
    
    /**
     * Get next stage in workflow
     */
    private function getNextStage($currentStage) {
        $stageOrder = [
            self::STAGE_SCHOOL_ADMIN => self::STAGE_COMMITTEE,
            self::STAGE_COMMITTEE => self::STAGE_VP,
            self::STAGE_VP => self::STAGE_PRESIDENT,
            self::STAGE_PRESIDENT => null // Final stage
        ];
        
        return $stageOrder[$currentStage] ?? null;
    }
    
    /**
     * Get status for stage
     */
    private function getStatusForStage($stageName) {
        $statusMap = [
            self::STAGE_SCHOOL_ADMIN => self::STATE_PENDING_SCHOOL_ADMIN,
            self::STAGE_COMMITTEE => self::STATE_PENDING_COMMITTEE,
            self::STAGE_VP => self::STATE_PENDING_VP,
            self::STAGE_PRESIDENT => self::STATE_PENDING_PRESIDENT
        ];
        
        return $statusMap[$stageName] ?? self::STATE_DRAFT;
    }
    
    /**
     * Update scholarship status
     */
    private function updateScholarshipStatus($scholarshipId, $status) {
        $stmt = $this->conn->prepare("UPDATE scholarships SET workflow_status = ? WHERE id = ?");
        return $stmt->execute([$status, $scholarshipId]);
    }
    
    /**
     * Validate approval token
     */
    private function validateApprovalToken($token) {
        $stmt = $this->conn->prepare("
            SELECT * FROM scholarship_approval_stages 
            WHERE approval_token = ? AND token_expires_at > NOW() AND token_used = 0
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get current stage
     */
    private function getCurrentStage($scholarshipId, $stageName) {
        $stmt = $this->conn->prepare("
            SELECT * FROM scholarship_approval_stages 
            WHERE scholarship_id = ? AND stage_name = ?
        ");
        $stmt->execute([$scholarshipId, $stageName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get scholarship workflow summary
     */
    public function getWorkflowSummary($scholarshipId) {
        $stmt = $this->conn->prepare("SELECT * FROM scholarship_workflow_summary WHERE id = ?");
        $stmt->execute([$scholarshipId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get workflow audit log
     */
    public function getAuditLog($scholarshipId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM scholarship_audit_log 
            WHERE scholarship_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$scholarshipId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get school approval configuration by scholarship
     */
    private function getSchoolApprovalConfigByScholarship($scholarshipId) {
        $stmt = $this->conn->prepare("
            SELECT sac.* FROM school_approval_config sac
            JOIN scholarships s ON sac.school_id = s.school_id
            WHERE s.id = ? AND sac.is_active = 1
        ");
        $stmt->execute([$scholarshipId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create next approval stage
     */
    private function createNextApprovalStage($scholarshipId, $stageName, $approvalConfig) {
        $stageOrder = $this->getStageOrder($stageName);
        
        // Get recipient email based on stage
        $recipientEmail = '';
        $recipientRole = '';
        
        switch ($stageName) {
            case self::STAGE_COMMITTEE:
                $recipientEmail = $approvalConfig['committee_email'] ?? '';
                $recipientRole = 'Committee Member';
                break;
            case self::STAGE_VP:
                $recipientEmail = $approvalConfig['vp_email'] ?? '';
                $recipientRole = 'Vice President';
                break;
            case self::STAGE_PRESIDENT:
                $recipientEmail = $approvalConfig['president_email'] ?? '';
                $recipientRole = 'President';
                break;
        }
        
        if (empty($recipientEmail)) {
            throw new Exception("No email configured for {$stageName} stage");
        }
        
        return $this->createApprovalStage($scholarshipId, $stageName, $stageOrder, $recipientEmail, $recipientRole);
    }
    
    /**
     * Get stage order number
     */
    private function getStageOrder($stageName) {
        $orderMap = [
            self::STAGE_SCHOOL_ADMIN => 1,
            self::STAGE_COMMITTEE => 2,
            self::STAGE_VP => 3,
            self::STAGE_PRESIDENT => 4
        ];
        
        return $orderMap[$stageName] ?? 1;
    }
    
    /**
     * Send rejection notification to provider
     */
    private function sendRejectionNotification($scholarshipId, $stageName, $reason) {
        $scholarship = $this->getScholarshipById($scholarshipId);
        
        if (!$scholarship || empty($scholarship['provider_email'])) {
            return false;
        }
        
        $subject = "Scholarship Application Rejected - {$scholarship['title']}";
        
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
                <h2 style='color: #dc3545; margin-bottom: 20px;'>Scholarship Application Rejected</h2>
                
                <div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;'>
                    <h3 style='margin: 0; color: #721c24;'>Rejection Notice</h3>
                </div>
                
                <p>Dear {$scholarship['provider_name']},</p>
                
                <p>We regret to inform you that your scholarship application has been rejected at the <strong>{$stageName}</strong> stage.</p>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #856404;'>Scholarship Details:</h4>
                    <p style='margin: 5px 0;'><strong>Title:</strong> {$scholarship['title']}</p>
                    <p style='margin: 5px 0;'><strong>Amount:</strong> ₱" . number_format($scholarship['amount'], 2) . "</p>
                    <p style='margin: 5px 0;'><strong>Rejected by:</strong> {$stageName}</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #495057;'>Reason for Rejection:</h4>
                    <p style='margin: 0; color: #6c757d;'>{$reason}</p>
                </div>
                
                <p>You may revise your scholarship application and resubmit it for review. Please address the concerns mentioned in the rejection reason.</p>
                
                <p>If you have any questions, please contact the school administrator.</p>
                
                <p>Best regards,<br>
                {$scholarship['school_name']}<br>
                Scholarship Review Committee</p>
            </div>
        </body>
        </html>";
        
        try {
            Mailer::send($scholarship['provider_email'], $subject, $emailBody);
            
            // Log email sent
            $this->logEmailSent($scholarshipId, $stageName, $scholarship['provider_email'], 
                              'REJECTION_NOTIFICATION', $subject, $emailBody);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send rejection notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send publication notifications
     */
    private function sendPublicationNotifications($scholarshipId) {
        $scholarship = $this->getScholarshipById($scholarshipId);
        
        if (!$scholarship) {
            return false;
        }
        
        // Send notification to provider
        $this->sendProviderPublicationNotification($scholarship);
        
        // Send notification to school admin
        $this->sendAdminPublicationNotification($scholarship);
        
        return true;
    }
    
    /**
     * Send publication notification to provider
     */
    private function sendProviderPublicationNotification($scholarship) {
        if (empty($scholarship['provider_email'])) {
            return false;
        }
        
        $subject = "Scholarship Approved and Published - {$scholarship['title']}";
        
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
                <h2 style='color: #28a745; margin-bottom: 20px;'>🎉 Scholarship Approved and Published!</h2>
                
                <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;'>
                    <h3 style='margin: 0; color: #155724;'>Congratulations!</h3>
                </div>
                
                <p>Dear {$scholarship['provider_name']},</p>
                
                <p>We are pleased to inform you that your scholarship application has been <strong>approved</strong> and is now <strong>published</strong> for student applications!</p>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #0d47a1;'>Scholarship Details:</h4>
                    <p style='margin: 5px 0;'><strong>Title:</strong> {$scholarship['title']}</p>
                    <p style='margin: 5px 0;'><strong>Amount:</strong> ₱" . number_format($scholarship['amount'], 2) . "</p>
                    <p style='margin: 5px 0;'><strong>Slots Available:</strong> {$scholarship['slots']}</p>
                    <p style='margin: 5px 0;'><strong>Application Period:</strong> {$scholarship['application_start']} to {$scholarship['application_end']}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #856404;'>What's Next?</h4>
                    <ul style='margin: 10px 0; padding-left: 20px; color: #6c757d;'>
                        <li>Students can now view and apply for your scholarship</li>
                        <li>You will receive notifications when students submit applications</li>
                        <li>You can review and approve applications through your provider dashboard</li>
                        <li>The scholarship will remain active during the application period</li>
                    </ul>
                </div>
                
                <p>Thank you for partnering with us to provide educational opportunities for our students!</p>
                
                <p>Best regards,<br>
                {$scholarship['school_name']}<br>
                Scholarship Administration</p>
            </div>
        </body>
        </html>";
        
        try {
            Mailer::send($scholarship['provider_email'], $subject, $emailBody);
            
            // Log email sent
            $this->logEmailSent($scholarship['id'], 'PUBLICATION', $scholarship['provider_email'], 
                              'PUBLICATION_NOTIFICATION', $subject, $emailBody);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send provider publication notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send publication notification to admin
     */
    private function sendAdminPublicationNotification($scholarship) {
        $approvalConfig = $this->getSchoolApprovalConfig($scholarship['school_id']);
        
        if (!$approvalConfig || empty($approvalConfig['admin_email'])) {
            return false;
        }
        
        $subject = "Scholarship Published - {$scholarship['title']}";
        
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
                <h2 style='color: #0055ff; margin-bottom: 20px;'>Scholarship Successfully Published</h2>
                
                <p>Dear Administrator,</p>
                
                <p>The following scholarship has completed the approval workflow and has been published:</p>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #0d47a1;'>Scholarship Details:</h4>
                    <p style='margin: 5px 0;'><strong>Title:</strong> {$scholarship['title']}</p>
                    <p style='margin: 5px 0;'><strong>Provider:</strong> {$scholarship['organization_name']}</p>
                    <p style='margin: 5px 0;'><strong>Amount:</strong> ₱" . number_format($scholarship['amount'], 2) . "</p>
                    <p style='margin: 5px 0;'><strong>Published:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <p>Students can now view and apply for this scholarship through the system.</p>
                
                <p>Best regards,<br>
                ISKOLar System</p>
            </div>
        </body>
        </html>";
        
        try {
            Mailer::send($approvalConfig['admin_email'], $subject, $emailBody);
            
            // Log email sent
            $this->logEmailSent($scholarship['id'], 'PUBLICATION', $approvalConfig['admin_email'], 
                              'ADMIN_PUBLICATION_NOTIFICATION', $subject, $emailBody);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send admin publication notification: " . $e->getMessage());
            return false;
        }
    }
}
?>