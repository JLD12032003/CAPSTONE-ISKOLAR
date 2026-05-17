<?php
/**
 * Scholarship Email Approval Page
 * Handles approval/rejection from email links
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

$database = new Database();
$conn = $database->connect();
$scholarship = null;
$workflowRecord = null;
$error = '';
$message = '';
$success = false;

// Get scholarship details by token
if ($token) {
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as provider_name, pp.organization_name,
               sch.school_name, swt.stage_name, swt.approver_name, swt.approver_email,
               swt.token_expires_at, swt.decision, swt.id as workflow_id
        FROM scholarship_workflow_tracking swt
        JOIN scholarships s ON swt.scholarship_id = s.id
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN provider_profiles pp ON u.id = pp.user_id
        LEFT JOIN schools sch ON s.school_id = sch.id
        WHERE swt.approval_token = ? 
            AND swt.token_expires_at > NOW() 
            AND swt.decision = 'PENDING'
    ");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $scholarship = $result;
        $workflowRecord = $result;
    } else {
        $error = 'Invalid or expired approval token';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $scholarship) {
    $decision = $_POST['decision'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (!in_array($decision, ['APPROVED', 'REJECTED'])) {
        $error = 'Invalid decision';
    } else {
        try {
            $conn->beginTransaction();
            
            // Update workflow tracking record
            $stmt = $conn->prepare("
                UPDATE scholarship_workflow_tracking 
                SET decision = ?, decision_at = NOW(), decision_notes = ?
                WHERE approval_token = ?
            ");
            $stmt->execute([$decision, $notes, $token]);
            
            if ($decision === 'APPROVED') {
                // Move to next stage or complete workflow
                $nextStage = getNextStage($workflowRecord['stage_name']);
                
                if ($nextStage) {
                    // Update scholarship to next stage
                    $stmt = $conn->prepare("
                        UPDATE scholarships 
                        SET workflow_status = ?, current_stage = ?
                        WHERE id = ?
                    ");
                    $newStatus = 'PENDING_' . $nextStage . '_REVIEW';
                    $stmt->execute([$newStatus, $nextStage, $scholarship['id']]);
                    
                    // Automatically create next workflow tracking record and send email
                    require_once __DIR__ . '/../../core/AutoForwarding.php';
                    $nextStageResult = AutoForwarding::createNextApprovalStage($conn, $scholarship['id'], $nextStage, $scholarship['school_id']);
                    
                    if ($nextStageResult['success']) {
                        $message = "Scholarship approved and automatically forwarded to " . ucwords(strtolower($nextStage)) . " (" . $nextStageResult['email'] . ") for review.";
                    } else {
                        $message = "Scholarship approved and moved to " . ucwords(strtolower($nextStage)) . " stage, but email forwarding failed: " . $nextStageResult['error'];
                    }
                } else {
                    // Final approval - publish scholarship
                    $stmt = $conn->prepare("
                        UPDATE scholarships 
                        SET workflow_status = 'APPROVED_FOR_PUBLICATION', 
                            current_stage = 'PUBLISHED',
                            status = 'Active', 
                            published_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$scholarship['id']]);
                    
                    $message = "Scholarship fully approved and published successfully!";
                }
            } else {
                // Rejection
                $rejectionStatus = 'REJECTED_BY_' . $workflowRecord['stage_name'];
                $stmt = $conn->prepare("
                    UPDATE scholarships 
                    SET workflow_status = ?, 
                        current_stage = 'REJECTED',
                        rejection_reason = ?, 
                        rejection_stage = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $rejectionStatus,
                    $notes, 
                    $workflowRecord['stage_name'], 
                    $scholarship['id']
                ]);
                
                $message = "Scholarship rejected at " . ucwords(strtolower($workflowRecord['stage_name'])) . " stage.";
            }
            
            // Log the action
            $actionType = ($decision === 'APPROVED') ? 'STAGE_APPROVED' : 'STAGE_REJECTED';
            $stmt = $conn->prepare("
                INSERT INTO scholarship_audit_log (
                    scholarship_id, action_type, stage_name, actor_role, actor_email,
                    action_details, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $scholarship['id'],
                $actionType,
                $workflowRecord['stage_name'],
                $workflowRecord['approver_name'] ?? 'Email Approver',
                $workflowRecord['approver_email'] ?? 'unknown@email.com',
                $notes ?: 'No notes provided'
            ]);
            
            $conn->commit();
            $success = true;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error processing approval: ' . $e->getMessage();
        }
    }
}

function getNextStage($currentStage) {
    $stageOrder = [
        'COMMITTEE' => 'VP',
        'VP' => 'PRESIDENT',
        'PRESIDENT' => null // Final stage
    ];
    
    return $stageOrder[$currentStage] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Approval - ISKOLar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #0055FF;
        --secondary: #FDC500;
        --dark: #012A4A;
        --light: #F8F9FA;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, var(--light), #e3f2fd);
        min-height: 100vh;
    }

    .approval-container {
        max-width: 900px;
        margin: 50px auto;
        padding: 20px;
    }

    .approval-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .approval-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        text-align: center;
    }

    .approval-body {
        padding: 40px;
    }

    .workflow-info {
        background: #e3f2fd;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        border-left: 4px solid var(--primary);
    }

    .scholarship-details {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .btn-approve {
        background: #28a745;
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 600;
        margin-right: 10px;
    }

    .btn-reject {
        background: #dc3545;
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 600;
    }

    .stage-badge {
        background: var(--secondary);
        color: var(--dark);
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .loa-section {
        background: #fff3cd;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #ffc107;
    }
    </style>
</head>
<body>

<div class="approval-container">
    <div class="approval-card">
        <div class="approval-header">
            <h1><i class="bi bi-award"></i> Scholarship Approval</h1>
            <p>Multi-Level Email-Based Approval System</p>
        </div>

        <div class="approval-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4><i class="bi bi-check-circle"></i> Decision Recorded</h4>
                    <p><?= htmlspecialchars($message); ?></p>
                    <p class="mb-0">Thank you for your participation in the scholarship approval process.</p>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <h4><i class="bi bi-exclamation-circle"></i> Error</h4>
                    <p class="mb-0"><?= htmlspecialchars($error); ?></p>
                </div>
            <?php elseif ($scholarship): ?>
                <div class="workflow-info">
                    <h5><i class="bi bi-info-circle text-primary"></i> Scholarship Approval Workflow</h5>
                    <p><strong>Current Stage:</strong> <span class="stage-badge"><?= ucwords(str_replace('_', ' ', $workflowRecord['stage_name'])); ?></span></p>
                    <p><strong>Your Role:</strong> <?= htmlspecialchars($workflowRecord['approver_name']); ?></p>
                    <p class="mb-0">This scholarship requires sequential approval from all levels before publication.</p>
                </div>

                <div class="scholarship-details">
                    <h4><i class="bi bi-award"></i> Scholarship Details</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Title:</strong> <?= htmlspecialchars($scholarship['title']); ?></p>
                            <p><strong>Provider:</strong> <?= htmlspecialchars($scholarship['organization_name'] ?? $scholarship['provider_name']); ?></p>
                            <p><strong>School:</strong> <?= htmlspecialchars($scholarship['school_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Amount:</strong> ₱<?= number_format($scholarship['amount'], 2); ?></p>
                            <p><strong>Number of Slots:</strong> <?= $scholarship['slots']; ?></p>
                            <p><strong>Type:</strong> <?= htmlspecialchars($scholarship['scholarship_type']); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($scholarship['description'])): ?>
                    <div class="mt-3">
                        <h6>Description:</h6>
                        <p><?= nl2br(htmlspecialchars($scholarship['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="loa-section">
                    <h5><i class="bi bi-file-text text-warning"></i> Letter of Agreement (LOA)</h5>
                    <p>A comprehensive Letter of Agreement has been generated for this scholarship, containing:</p>
                    <ul class="mb-0">
                        <li>Scholarship terms and conditions</li>
                        <li>Provider responsibilities</li>
                        <li>School obligations</li>
                        <li>Eligibility rules and implementation details</li>
                    </ul>
                </div>

                <div class="workflow-stages mb-4">
                    <h5><i class="bi bi-diagram-3"></i> Approval Workflow Stages</h5>
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="p-3 border rounded">
                                <i class="bi bi-person-gear" style="font-size: 2rem; color: var(--primary);"></i>
                                <h6 class="mt-2">School Admin</h6>
                                <small>Initial Review</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 border rounded">
                                <i class="bi bi-people" style="font-size: 2rem; color: var(--primary);"></i>
                                <h6 class="mt-2">Committee</h6>
                                <small>Evaluation & Vote</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 border rounded">
                                <i class="bi bi-person-badge" style="font-size: 2rem; color: var(--primary);"></i>
                                <h6 class="mt-2">Vice President</h6>
                                <small>Review & Approval</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 border rounded">
                                <i class="bi bi-award" style="font-size: 2rem; color: var(--primary);"></i>
                                <h6 class="mt-2">President</h6>
                                <small>Final Approval</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="approval-actions">
                    <h5>Your Decision</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Decision Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Add any comments, conditions, or feedback regarding this scholarship..."></textarea>
                            <small class="text-muted">Your notes will be included in the audit trail and may be shared with other approvers.</small>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="submit" name="decision" value="APPROVED" class="btn-approve">
                                <i class="bi bi-check-circle"></i> Approve Scholarship
                            </button>
                            <button type="submit" name="decision" value="REJECTED" class="btn-reject">
                                <i class="bi bi-x-circle"></i> Reject Scholarship
                            </button>
                        </div>
                    </form>
                </div>

                <div class="alert alert-info mt-4">
                    <h6><i class="bi bi-info-circle"></i> Important Notes</h6>
                    <ul class="mb-0">
                        <li><strong>Sequential Approval:</strong> All stages must approve for publication</li>
                        <li><strong>No Bypass:</strong> No shortcuts or manual overrides allowed</li>
                        <li><strong>Audit Trail:</strong> All decisions are logged with timestamps</li>
                        <li><strong>Token Expires:</strong> This approval link expires on <?= date('M j, Y g:i A', strtotime($workflowRecord['token_expires_at'])); ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Invalid or Expired Link</h5>
                    <p>This approval link is either invalid or has expired. Please contact the school administrator if you believe this is an error.</p>
                </div>
            <?php endif; ?>

            <div class="contact-info mt-4">
                <h6>Need Help?</h6>
                <p>Contact the school administrator at: <strong>admin@davaocentralcollege.edu.ph</strong></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirmation before submitting decision
document.querySelectorAll('button[name="decision"]').forEach(button => {
    button.addEventListener('click', function(e) {
        const decision = this.value;
        const action = decision === 'APPROVED' ? 'approve' : 'reject';
        
        if (!confirm(`Are you sure you want to ${action} this scholarship? This decision cannot be undone.`)) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>