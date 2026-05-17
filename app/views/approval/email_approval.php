<?php
/**
 * Email Approval Page
 * Handles approval/rejection from email links
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/PartnershipController.php';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

if (!$token) {
    $error = "Invalid approval link - no token provided";
} else {
    // Validate token and get request details
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $stmt = $conn->prepare("
            SELECT as_stage.*, pr.organization_name, pr.partnership_title, pr.contact_person
            FROM approval_stages as_stage
            JOIN partnership_requests pr ON as_stage.partnership_request_id = pr.id
            WHERE as_stage.approval_token = ? 
            AND as_stage.token_expires_at > NOW() 
            AND as_stage.token_used = 0
        ");
        $stmt->execute([$token]);
        $stage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stage) {
            $error = "Invalid or expired approval link";
        }
    } catch (Exception $e) {
        $error = "System error: " . $e->getMessage();
    }
}

$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $controller = new PartnershipController();
    $decision = $_POST['decision'];
    $notes = trim($_POST['notes'] ?? '');
    
    $result = $controller->processApproval($token, $decision, $notes, $_SERVER['REMOTE_ADDR']);
    
    if ($result['success']) {
        $success = true;
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Approval - ISKOLar</title>
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
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .approval-container {
        max-width: 600px;
        width: 100%;
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
        padding: 30px;
        text-align: center;
    }

    .approval-body {
        padding: 30px;
    }

    .btn-approve {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn-approve:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        color: white;
    }

    .btn-reject {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        color: white;
    }

    .form-control {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
    }

    .info-box {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary);
    }

    .stage-badge {
        background: var(--secondary);
        color: var(--dark);
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    </style>
</head>
<body>

<div class="approval-container">
    <div class="approval-card">
        <div class="approval-header">
            <h1><i class="bi bi-clipboard-check"></i> Partnership Approval</h1>
            <p class="mb-0">ISKOLar Partnership Request Review</p>
        </div>

        <div class="approval-body">
            <?php if (isset($error)): ?>
                <!-- Error State -->
                <div class="text-center">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                    <h4 class="text-danger mt-3">Access Error</h4>
                    <p class="text-muted"><?= htmlspecialchars($error); ?></p>
                    <a href="mailto:support@iskolar.edu.ph" class="btn btn-outline-primary">
                        <i class="bi bi-envelope"></i> Contact Support
                    </a>
                </div>

            <?php elseif ($success): ?>
                <!-- Success State -->
                <div class="text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="text-success mt-3">Decision Recorded</h4>
                    <p class="text-muted"><?= htmlspecialchars($message); ?></p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        The provider and relevant parties have been notified of your decision.
                    </div>
                </div>

            <?php else: ?>
                <!-- Approval Form -->
                <div class="info-box">
                    <h5 class="mb-3">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($stage['organization_name']); ?>
                        <span class="stage-badge ms-2"><?= ucwords(str_replace('_', ' ', $stage['stage_name'])); ?></span>
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Partnership Title:</strong><br>
                            <?= htmlspecialchars($stage['partnership_title']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Contact Person:</strong><br>
                            <?= htmlspecialchars($stage['contact_person']); ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <strong>Your Role:</strong> <?= ucwords($stage['recipient_role']); ?><br>
                        <strong>Expires:</strong> <?= date('M j, Y g:i A', strtotime($stage['token_expires_at'])); ?>
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Decision *</label>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-approve" onclick="setDecision('APPROVED')">
                                <i class="bi bi-check-circle"></i> Approve Partnership Request
                            </button>
                            <button type="button" class="btn btn-reject" onclick="setDecision('REJECTED')">
                                <i class="bi bi-x-circle"></i> Reject Partnership Request
                            </button>
                        </div>
                        <input type="hidden" name="decision" id="decision" required>
                    </div>

                    <div class="mb-4" id="notesSection" style="display: none;">
                        <label class="form-label fw-bold">Notes/Comments</label>
                        <textarea name="notes" class="form-control" rows="4" 
                                  placeholder="Add any comments or feedback (optional for approval, recommended for rejection)"></textarea>
                        <small class="text-muted">Your comments will be shared with the provider and other stakeholders.</small>
                    </div>

                    <div class="d-grid" id="submitSection" style="display: none;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Submit Decision
                        </button>
                    </div>
                </form>

                <div class="mt-4">
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Important Information</h6>
                        <ul class="mb-0">
                            <li>This decision is final and cannot be changed</li>
                            <li>Approval moves the request to the next stage</li>
                            <li>Rejection terminates the entire approval process</li>
                            <li>All parties will be notified of your decision</li>
                        </ul>
                    </div>
                </div>

                <!-- Partnership Details (Expandable) -->
                <div class="mt-3">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#partnershipDetails">
                        <i class="bi bi-info-circle"></i> View Full Partnership Details
                    </button>
                    
                    <div class="collapse mt-3" id="partnershipDetails">
                        <div class="card">
                            <div class="card-body">
                                <p><strong>Request ID:</strong> #<?= $stage['partnership_request_id']; ?></p>
                                <p><strong>Stage:</strong> <?= ucwords(str_replace('_', ' ', $stage['stage_name'])); ?></p>
                                <p><strong>Recipient Email:</strong> <?= htmlspecialchars($stage['recipient_email']); ?></p>
                                <p><strong>Email Sent:</strong> <?= date('M j, Y g:i A', strtotime($stage['email_sent_at'])); ?></p>
                                
                                <div class="mt-3">
                                    <a href="mailto:support@iskolar.edu.ph?subject=Partnership Request #<?= $stage['partnership_request_id']; ?>" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-envelope"></i> Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setDecision(decision) {
    document.getElementById('decision').value = decision;
    
    // Show notes section and submit button
    document.getElementById('notesSection').style.display = 'block';
    document.getElementById('submitSection').style.display = 'block';
    
    // Update notes label based on decision
    const notesLabel = document.querySelector('label[for="notes"]');
    if (decision === 'REJECTED') {
        notesLabel.innerHTML = 'Rejection Reason *';
        document.querySelector('textarea[name="notes"]').required = true;
        document.querySelector('textarea[name="notes"]').placeholder = 'Please provide a reason for rejection (required)';
    } else {
        notesLabel.innerHTML = 'Approval Notes';
        document.querySelector('textarea[name="notes"]').required = false;
        document.querySelector('textarea[name="notes"]').placeholder = 'Add any approval notes or conditions (optional)';
    }
    
    // Scroll to notes section
    document.getElementById('notesSection').scrollIntoView({ behavior: 'smooth' });
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const decision = document.getElementById('decision').value;
    const notes = document.querySelector('textarea[name="notes"]').value.trim();
    
    if (!decision) {
        e.preventDefault();
        alert('Please select a decision (Approve or Reject)');
        return;
    }
    
    if (decision === 'REJECTED' && !notes) {
        e.preventDefault();
        alert('Please provide a reason for rejection');
        document.querySelector('textarea[name="notes"]').focus();
        return;
    }
    
    // Confirm decision
    const confirmMessage = decision === 'APPROVED' 
        ? 'Are you sure you want to APPROVE this partnership request?' 
        : 'Are you sure you want to REJECT this partnership request?';
        
    if (!confirm(confirmMessage)) {
        e.preventDefault();
    }
});
</script>
</body>
</html>