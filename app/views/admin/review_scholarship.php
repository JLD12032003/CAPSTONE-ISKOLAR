<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ScholarshipWorkflowController.php';

$database = new Database();
$conn = $database->connect();
$workflowController = new ScholarshipWorkflowController();

$scholarshipId = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

if (!$scholarshipId) {
    header("Location: dashboard.php");
    exit();
}

// Get scholarship details
$result = $workflowController->getScholarshipForApproval('dummy_token');
if (!$result['success']) {
    // Get scholarship directly for admin review
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as provider_name, pp.organization_name, pp.contact_person,
               pp.contact_number, u.email as provider_email, sch.school_name
        FROM scholarships s
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN provider_profiles pp ON u.id = pp.user_id
        LEFT JOIN schools sch ON s.school_id = sch.id
        WHERE s.id = ?
    ");
    $stmt->execute([$scholarshipId]);
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $scholarship = $result['scholarship'];
}

if (!$scholarship) {
    header("Location: dashboard.php");
    exit();
}

// Handle email forwarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'forward_email') {
        $recipientEmail = trim($_POST['recipient_email']);
        $recipientName = trim($_POST['recipient_name']);
        $notes = trim($_POST['notes']);
        
        if (empty($recipientEmail)) {
            $error = "Recipient email is required.";
        } elseif (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Save email to favorites if requested
                if (isset($_POST['save_to_favorites']) && !empty($recipientName)) {
                    $stmt = $conn->prepare("
                        INSERT IGNORE INTO admin_email_favorites (admin_id, email, name, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $recipientEmail, $recipientName]);
                }
                
                // Create workflow tracking record FIRST
                $approvalToken = generateApprovalToken($scholarshipId, 'COMMITTEE');
                $stmt = $conn->prepare("
                    INSERT INTO scholarship_workflow_tracking (
                        scholarship_id, stage_name, stage_order, approver_email, 
                        approver_name, approval_token, token_expires_at
                    ) VALUES (?, 'COMMITTEE', 1, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                ");
                $stmt->execute([$scholarshipId, $recipientEmail, $recipientName, $approvalToken]);
                
                // Generate approval URLs using the token we just created
                $approveUrl = generateApprovalUrlWithToken($approvalToken, 'APPROVED');
                $rejectUrl = generateApprovalUrlWithToken($approvalToken, 'REJECTED');
                
                // Create email content
                $emailSubject = "Scholarship Approval Required - " . $scholarship['title'];
                $emailBody = generateApprovalEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl, $notes);
                
                // Send email using Mailer class
                require_once __DIR__ . '/../../core/Mailer.php';
                
                try {
                    $emailSent = Mailer::send($recipientEmail, $emailSubject, $emailBody);
                    
                    if ($emailSent) {
                        // Update scholarship workflow status to indicate it's been forwarded
                        $stmt = $conn->prepare("
                            UPDATE scholarships 
                            SET workflow_status = 'PENDING_COMMITTEE_REVIEW', 
                                current_stage = 'COMMITTEE',
                                forwarded_at = NOW(),
                                forwarded_to = ?,
                                forwarded_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$recipientEmail, $_SESSION['user_id'], $scholarshipId]);
                        
                        // Log the forwarding action
                        $stmt = $conn->prepare("
                            INSERT INTO scholarship_audit_log (
                                scholarship_id, action_type, stage_name, actor_role, actor_email,
                                action_details, created_at
                            ) VALUES (?, 'EMAIL_FORWARDED', 'COMMITTEE', 'Admin', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $scholarshipId, 
                            $_SESSION['email'] ?? 'admin@school.edu',
                            "Forwarded to: $recipientEmail ($recipientName). Notes: $notes"
                        ]);
                        
                        $message = "Scholarship application forwarded successfully to $recipientEmail. They will receive an email with approval links.";
                    } else {
                        // Get detailed error message
                        $errorDetails = Mailer::getLastError($recipientEmail, $emailSubject, $emailBody);
                        $error = "Failed to send email to $recipientEmail. Error: $errorDetails";
                    }
                } catch (Exception $emailError) {
                    $error = "Email sending failed: " . $emailError->getMessage();
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get favorite emails for this admin
$stmt = $conn->prepare("
    SELECT email, name FROM admin_email_favorites 
    WHERE admin_id = ? 
    ORDER BY name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$favoriteEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions for email approval system
function generateApprovalToken($scholarshipId, $stageName) {
    return hash('sha256', $scholarshipId . $stageName . time() . random_bytes(32));
}

function generateApprovalUrl($scholarshipId, $action) {
    global $conn;
    
    // Get the latest approval token for this scholarship
    $stmt = $conn->prepare("
        SELECT approval_token FROM scholarship_workflow_tracking 
        WHERE scholarship_id = ? AND decision = 'PENDING' 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$scholarshipId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $token = $result ? $result['approval_token'] : generateApprovalToken($scholarshipId, 'COMMITTEE');
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/ISKOLAR_3RD_YEAR_EDITION'; // Add the correct base path
    return "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$token}&action={$action}";
}

function generateApprovalUrlWithToken($token, $action) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/ISKOLAR_3RD_YEAR_EDITION'; // Add the correct base path
    return "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$token}&action={$action}";
}

function generateApprovalEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl, $notes) {
    $partnershipLetter = !empty($scholarship['partnership_letter']) ? 
        "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <h4 style='color: #0d47a1; margin-bottom: 10px;'>Partnership Request Letter:</h4>
            <div style='white-space: pre-line; color: #333;'>" . htmlspecialchars($scholarship['partnership_letter']) . "</div>
        </div>" : "";
    
    $adminNotes = !empty($notes) ? 
        "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0;'>
            <h4 style='color: #856404; margin-bottom: 10px;'>Admin Notes:</h4>
            <p style='margin: 0; color: #333;'>" . htmlspecialchars($notes) . "</p>
        </div>" : "";
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='max-width: 700px; background: white; padding: 30px; border-radius: 12px; margin: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #0055ff; margin-bottom: 10px;'>ISKOLar Scholarship System</h1>
                <h2 style='color: #012A4A; margin: 0;'>Scholarship Approval Required</h2>
            </div>
            
            <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                <h3 style='margin: 0 0 15px 0; color: #0d47a1;'>Dear {$recipientName},</h3>
                <p style='margin: 0; color: #333; line-height: 1.6;'>
                    A new scholarship application requires your approval. Please review the details below and make your decision.
                </p>
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
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Type:</td>
                        <td style='padding: 8px 0;'>" . htmlspecialchars($scholarship['scholarship_type']) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold; vertical-align: top;'>Description:</td>
                        <td style='padding: 8px 0; line-height: 1.5;'>" . nl2br(htmlspecialchars($scholarship['description'])) . "</td>
                    </tr>
                </table>
            </div>
            
            {$partnershipLetter}
            {$adminNotes}
            
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
                    Davao Central College
                </p>
            </div>
        </div>
    </body>
    </html>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Scholarship Application - ISKOLar Admin</title>
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

    .navbar {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .review-container {
        max-width: 1200px;
        margin: 100px auto 50px;
        padding: 20px;
    }

    .review-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .review-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 30px;
    }

    .summary-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        border-left: 5px solid var(--primary);
    }

    .forward-section {
        background: #fff3cd;
        border-radius: 15px;
        padding: 25px;
        border-left: 5px solid var(--secondary);
    }

    .btn-forward {
        background: linear-gradient(135deg, var(--secondary), #ffb300);
        border: none;
        color: var(--dark);
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 10px;
    }

    .favorite-email {
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        margin: 2px;
        background: #e3f2fd;
        border: 1px solid #bbdefb;
        display: inline-block;
        font-size: 0.9rem;
    }

    .favorite-email:hover {
        background: var(--primary);
        color: white;
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="dashboard.php">
            <i class="bi bi-shield-check"></i> ISKOLar Admin
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['fullname']); ?>
            </span>
            <a class="btn btn-sm btn-outline-light" href="dashboard.php">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="review-container">
    <!-- Header -->
    <div class="review-card">
        <div class="review-header">
            <h1><i class="bi bi-file-earmark-check"></i> Scholarship Application Review</h1>
            <p class="mb-0">Review and forward scholarship application for approval</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Scholarship Summary -->
        <div class="col-md-8">
            <div class="summary-card">
                <h4><i class="bi bi-award text-primary"></i> Scholarship Summary</h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Title:</strong><br>
                        <?= htmlspecialchars($scholarship['title']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Provider:</strong><br>
                        <?= htmlspecialchars($scholarship['organization_name'] ?? $scholarship['provider_name']); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Amount:</strong><br>
                        ₱<?= number_format($scholarship['amount'], 2); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Slots:</strong><br>
                        <?= $scholarship['slots']; ?> recipients
                    </div>
                    <div class="col-md-4">
                        <strong>Type:</strong><br>
                        <?= htmlspecialchars($scholarship['scholarship_type']); ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?= nl2br(htmlspecialchars($scholarship['description'])); ?>
                </div>
                
                <?php if (!empty($scholarship['eligible_courses'])): ?>
                <div class="mb-3">
                    <strong>Eligible Courses:</strong><br>
                    <?= htmlspecialchars(str_replace(',', ', ', $scholarship['eligible_courses'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($scholarship['year_levels'])): ?>
                <div class="mb-3">
                    <strong>Year Levels:</strong><br>
                    <?= htmlspecialchars(str_replace(',', ', ', $scholarship['year_levels'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($scholarship['other_requirements'])): ?>
                <div class="mb-3">
                    <strong>Requirements:</strong><br>
                    <?= htmlspecialchars(str_replace(',', ', ', $scholarship['other_requirements'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>Application Period:</strong><br>
                        <?= date('M j, Y', strtotime($scholarship['application_start'])); ?> - 
                        <?= date('M j, Y', strtotime($scholarship['application_end'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Submitted:</strong><br>
                        <?= $scholarship['submitted_at'] ? date('M j, Y g:i A', strtotime($scholarship['submitted_at'])) : 'Not submitted'; ?>
                    </div>
                </div>
            </div>

            <!-- Partnership Letter -->
            <?php if (!empty($scholarship['partnership_letter'])): ?>
            <div class="summary-card">
                <h4><i class="bi bi-envelope text-primary"></i> Partnership Request Letter</h4>
                <div style="background: white; padding: 20px; border-radius: 10px; border: 1px solid #dee2e6;">
                    <?= nl2br(htmlspecialchars($scholarship['partnership_letter'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Provider Information -->
            <div class="summary-card">
                <h4><i class="bi bi-building text-primary"></i> Provider Information</h4>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Contact Person:</strong><br>
                        <?= htmlspecialchars($scholarship['contact_person'] ?? $scholarship['provider_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong><br>
                        <?= htmlspecialchars($scholarship['provider_email']); ?>
                    </div>
                </div>
                <?php if (!empty($scholarship['contact_number'])): ?>
                <div class="mt-2">
                    <strong>Phone:</strong> <?= htmlspecialchars($scholarship['contact_number']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Forward Section -->
        <div class="col-md-4">
            <div class="forward-section">
                <h4><i class="bi bi-send text-warning"></i> Forward Application</h4>
                <p>Forward this scholarship application to the appropriate personnel for approval.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="forward_email">
                    
                    <div class="mb-3">
                        <label class="form-label">Recipient Name</label>
                        <input type="text" name="recipient_name" class="form-control" 
                               placeholder="e.g., Committee Chair" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Recipient Email *</label>
                        <input type="email" name="recipient_email" id="recipientEmail" class="form-control" 
                               placeholder="email@example.com" required>
                    </div>
                    
                    <?php if (!empty($favoriteEmails)): ?>
                    <div class="mb-3">
                        <label class="form-label">Quick Select (Favorites)</label>
                        <div>
                            <?php foreach ($favoriteEmails as $fav): ?>
                                <span class="favorite-email" onclick="selectFavoriteEmail('<?= htmlspecialchars($fav['email']); ?>', '<?= htmlspecialchars($fav['name']); ?>')">
                                    <?= htmlspecialchars($fav['name']); ?><br>
                                    <small><?= htmlspecialchars($fav['email']); ?></small>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Add any notes or instructions for the recipient..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="save_to_favorites" id="saveToFavorites">
                            <label class="form-check-label" for="saveToFavorites">
                                Save email to favorites for future use
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-forward w-100">
                        <i class="bi bi-send"></i> Forward Application
                    </button>
                </form>
            </div>

            <!-- Workflow Status -->
            <div class="summary-card mt-3">
                <h5><i class="bi bi-diagram-3 text-primary"></i> Workflow Status</h5>
                <div class="mb-2">
                    <strong>Current Stage:</strong><br>
                    <span class="badge bg-warning">Admin Review</span>
                </div>
                <div class="mb-2">
                    <strong>Next Steps:</strong><br>
                    <small class="text-muted">
                        1. Review application details<br>
                        2. Forward to appropriate personnel<br>
                        3. Track approval progress
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectFavoriteEmail(email, name) {
    document.getElementById('recipientEmail').value = email;
    document.querySelector('input[name="recipient_name"]').value = name;
    
    // Highlight selected favorite
    document.querySelectorAll('.favorite-email').forEach(el => {
        el.style.background = '#e3f2fd';
        el.style.color = 'inherit';
    });
    event.target.style.background = 'var(--primary)';
    event.target.style.color = 'white';
}
</script>
</body>
</html>