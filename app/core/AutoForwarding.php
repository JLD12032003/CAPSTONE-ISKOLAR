<?php
/**
 * Automatic Forwarding Functions for Scholarship Approval Workflow
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Mailer.php';

class AutoForwarding {
    
    /**
     * Create next approval stage and send email automatically
     */
    public static function createNextApprovalStage($conn, $scholarshipId, $nextStage, $schoolId) {
        try {
            // Get school email configuration
            $stmt = $conn->prepare("
                SELECT committee_email, vp_email, president_email, school_name 
                FROM schools WHERE id = ?
            ");
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$school) {
                return ['success' => false, 'error' => 'School configuration not found'];
            }
            
            // Get the appropriate email for the next stage
            $emailField = strtolower($nextStage) . '_email';
            $recipientEmail = $school[$emailField] ?? null;
            
            if (!$recipientEmail) {
                return ['success' => false, 'error' => "No email configured for $nextStage"];
            }
            
            $recipientName = ucwords(strtolower($nextStage));
            if ($nextStage === 'VP') {
                $recipientName = 'Vice President';
            }
            
            // Generate approval token
            $approvalToken = hash('sha256', $scholarshipId . $nextStage . time() . random_bytes(16));
            
            // Create workflow tracking record
            $stageOrder = ['COMMITTEE' => 1, 'VP' => 2, 'PRESIDENT' => 3];
            $stmt = $conn->prepare("
                INSERT INTO scholarship_workflow_tracking (
                    scholarship_id, stage_name, stage_order, approver_email, 
                    approver_name, approval_token, token_expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute([
                $scholarshipId, 
                $nextStage, 
                $stageOrder[$nextStage], 
                $recipientEmail, 
                $recipientName, 
                $approvalToken
            ]);
            
            // Get scholarship details for email
            $stmt = $conn->prepare("
                SELECT s.*, u.fullname as provider_name, pp.organization_name
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                WHERE s.id = ?
            ");
            $stmt->execute([$scholarshipId]);
            $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate approval URLs
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = '/ISKOLAR_3RD_YEAR_EDITION';
            
            $approveUrl = "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=APPROVED";
            $rejectUrl = "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=REJECTED";
            
            // Create email content
            $emailSubject = "Scholarship Approval Required - " . $scholarship['title'];
            $emailBody = self::generateAutoForwardEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl, $nextStage);
            
            // Send email
            $emailSent = Mailer::send($recipientEmail, $emailSubject, $emailBody);
            
            if ($emailSent) {
                // Log the auto-forwarding action
                $stmt = $conn->prepare("
                    INSERT INTO scholarship_audit_log (
                        scholarship_id, action_type, stage_name, actor_role, actor_email,
                        action_details, created_at
                    ) VALUES (?, 'EMAIL_FORWARDED', ?, 'SYSTEM', 'system@iskolar.edu', ?, NOW())
                ");
                $stmt->execute([
                    $scholarshipId,
                    $nextStage,
                    "Auto-forwarded to $nextStage ($recipientEmail) after previous stage approval"
                ]);
                
                return ['success' => true, 'email' => $recipientEmail];
            } else {
                return ['success' => false, 'error' => 'Failed to send email to ' . $recipientEmail];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate email body for auto-forwarded approvals
     */
    private static function generateAutoForwardEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl, $stage) {
        $stageDescription = [
            'VP' => 'Vice President Review',
            'PRESIDENT' => 'Presidential Final Approval'
        ];
        
        $currentStageDesc = $stageDescription[$stage] ?? $stage . ' Review';
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 700px; background: white; padding: 30px; border-radius: 12px; margin: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #0055ff; margin-bottom: 10px;'>ISKOLar Scholarship System</h1>
                    <h2 style='color: #012A4A; margin: 0;'>$currentStageDesc Required</h2>
                </div>
                
                <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                    <h3 style='margin: 0 0 15px 0; color: #0d47a1;'>Dear {$recipientName},</h3>
                    <p style='margin: 0; color: #333; line-height: 1.6;'>
                        A scholarship application has been approved by the previous stage and now requires your review and approval.
                    </p>
                </div>
                
                <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h4 style='color: #155724; margin: 0 0 10px 0;'>✅ Previous Stage Approved</h4>
                    <p style='margin: 0; color: #155724;'>This scholarship has been reviewed and approved by the previous approval stage and is now forwarded to you for the next level of review.</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                    <h3 style='color: #0d47a1; margin-bottom: 15px;'>Scholarship Details</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr style='border-bottom: 1px solid #dee2e6;'>
                            <td style='padding: 8px 0; font-weight: bold; width: 30%;'>Title:</td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scholarship['title']) . "</td>
                        </tr>
                        <tr style='border-bottom: 1px solid #dee2e6;'>
                            <td style='padding: 8px 0; font-weight: bold;'>Provider:</td>
                            <td style='padding: 8px 0;'>" . htmlspecialchars($scholarship['organization_name'] ?? $scholarship['provider_name']) . "</td>
                        </tr>
                        <tr style='border-bottom: 1px solid #dee2e6;'>
                            <td style='padding: 8px 0; font-weight: bold;'>Amount:</td>
                            <td style='padding: 8px 0;'>₱" . number_format($scholarship['amount'], 2) . "</td>
                        </tr>
                        <tr style='border-bottom: 1px solid #dee2e6;'>
                            <td style='padding: 8px 0; font-weight: bold;'>Slots:</td>
                            <td style='padding: 8px 0;'>" . $scholarship['slots'] . " recipients</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; font-weight: bold; vertical-align: top;'>Description:</td>
                            <td style='padding: 8px 0; line-height: 1.5;'>" . nl2br(htmlspecialchars($scholarship['description'])) . "</td>
                        </tr>
                    </table>
                </div>
                
                <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                    <h3 style='color: #155724; margin-bottom: 15px;'>Required Action</h3>
                    <p style='margin: 0 0 15px 0; color: #333; line-height: 1.6;'>
                        Please review this scholarship application and make your decision. Click one of the buttons below:
                    </p>
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='{$approveUrl}' style='background: #28a745; color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-right: 15px; display: inline-block; font-size: 16px;'>
                            ✓ APPROVE SCHOLARSHIP
                        </a>
                        <a href='{$rejectUrl}' style='background: #dc3545; color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; font-size: 16px;'>
                            ✗ REJECT SCHOLARSHIP
                        </a>
                    </div>
                </div>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 25px;'>
                    <p style='margin: 0; font-size: 12px; color: #666; text-align: center;'>
                        <strong>Important:</strong> This approval link expires in 7 days. 
                        If you have questions, contact the school administrator at admin@davaocentralcollege.edu.ph
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0; color: #666; font-size: 12px;'>
                        ISKOLar Scholarship Management System<br>
                        Davao Central College - Automated Workflow
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>