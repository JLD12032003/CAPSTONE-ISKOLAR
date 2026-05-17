<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$userModel = new User();
$database = new Database();
$conn = $database->connect();

$user = $userModel->findById($_SESSION['user_id']);

// Get verification status
$stmt = $conn->prepare("SELECT * FROM admin_verifications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$verification) {
    // Redirect to verification form if not submitted
    header("Location: identity_verification.php");
    exit();
}

// Get school information
$stmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$stmt->execute([$user['school_id']]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Status - ISKOLar</title>
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
        background-color: var(--light);
    }

    .navbar {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .main-content {
        margin-top: 80px;
        padding: 20px;
    }

    .status-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .status-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .status-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 30px;
        text-align: center;
    }

    .status-body {
        padding: 30px;
    }

    .status-badge {
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 1rem;
        display: inline-block;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-under-review { background: #d1ecf1; color: #0c5460; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }

    .info-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 20px;
        padding-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -23px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #dee2e6;
    }

    .timeline-item.completed::before {
        background: #28a745;
    }

    .timeline-item.active::before {
        background: var(--primary);
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="dashboard.php">
            <i class="bi bi-mortarboard-fill"></i> ISKOLar Admin
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['fullname']); ?>
            </span>
            <a class="btn btn-sm btn-outline-light" href="#" onclick="confirmLogout(); return false;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="status-container">
        <div class="status-card">
            <div class="status-header">
                <h1><i class="bi bi-shield-check"></i> Identity Verification Status</h1>
                <p class="mb-0">Track your administrator verification progress</p>
            </div>

            <div class="status-body">
                <!-- Current Status -->
                <div class="text-center mb-4">
                    <?php
                    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $verification['verification_status']));
                    $statusIcon = 'bi-clock';
                    if ($verification['verification_status'] === 'Approved') $statusIcon = 'bi-check-circle';
                    if ($verification['verification_status'] === 'Rejected') $statusIcon = 'bi-x-circle';
                    if ($verification['verification_status'] === 'Under Review') $statusIcon = 'bi-eye';
                    ?>
                    <i class="<?= $statusIcon; ?>" style="font-size: 4rem; color: var(--primary);"></i>
                    <h3 class="mt-3">Verification Status</h3>
                    <span class="status-badge <?= $statusClass; ?>">
                        <?= htmlspecialchars($verification['verification_status']); ?>
                    </span>
                </div>

                <!-- Status Message -->
                <?php if ($verification['verification_status'] === 'Pending'): ?>
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle"></i> Verification Submitted</h5>
                        <p class="mb-0">Your identity verification has been submitted and is waiting for review by system administrators. You will receive an email notification once the review is complete.</p>
                    </div>
                <?php elseif ($verification['verification_status'] === 'Under Review'): ?>
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-eye"></i> Under Review</h5>
                        <p class="mb-0">Your verification is currently being reviewed by our administrators. This process typically takes 24-48 hours. You will be notified via email once the review is complete.</p>
                    </div>
                <?php elseif ($verification['verification_status'] === 'Approved'): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle"></i> Verification Approved</h5>
                        <p class="mb-0">Congratulations! Your identity has been verified. You now have full access to all administrative functions in the ISKOLar system.</p>
                    </div>
                <?php elseif ($verification['verification_status'] === 'Rejected'): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle"></i> Verification Rejected</h5>
                        <p class="mb-2">Your verification was rejected for the following reason:</p>
                        <p class="mb-0"><strong><?= htmlspecialchars($verification['verification_notes'] ?? 'No specific reason provided.'); ?></strong></p>
                        <div class="mt-3">
                            <a href="identity_verification.php" class="btn btn-primary">Submit New Verification</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Verification Timeline -->
                <div class="info-section">
                    <h5 class="mb-3">Verification Process</h5>
                    <div class="timeline">
                        <div class="timeline-item completed">
                            <strong>Verification Submitted</strong>
                            <div class="text-muted"><?= date('M j, Y g:i A', strtotime($verification['submitted_at'])); ?></div>
                        </div>
                        
                        <div class="timeline-item <?= in_array($verification['verification_status'], ['Under Review', 'Approved', 'Rejected']) ? 'completed' : ''; ?>">
                            <strong>Under Review</strong>
                            <div class="text-muted">
                                <?php if (in_array($verification['verification_status'], ['Under Review', 'Approved', 'Rejected'])): ?>
                                    Review in progress
                                <?php else: ?>
                                    Waiting for review
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="timeline-item <?= $verification['verification_status'] === 'Approved' ? 'completed' : ''; ?>">
                            <strong>Verification Complete</strong>
                            <div class="text-muted">
                                <?php if ($verification['verification_status'] === 'Approved'): ?>
                                    <?= date('M j, Y g:i A', strtotime($verification['verified_at'])); ?>
                                <?php elseif ($verification['verification_status'] === 'Rejected'): ?>
                                    Rejected - <?= date('M j, Y g:i A', strtotime($verification['verified_at'])); ?>
                                <?php else: ?>
                                    Pending completion
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submitted Information -->
                <div class="info-section">
                    <h5 class="mb-3">Submitted Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong><br>
                            <?= htmlspecialchars($verification['first_name'] . ' ' . ($verification['middle_name'] ? $verification['middle_name'] . ' ' : '') . $verification['last_name'] . ($verification['suffix'] ? ' ' . $verification['suffix'] : '')); ?></p>
                            
                            <p><strong>Position:</strong><br>
                            <?= htmlspecialchars($verification['position']); ?></p>
                            
                            <p><strong>Administrative Role:</strong><br>
                            <?= ucwords(str_replace('_', ' ', $verification['admin_role'])); ?></p>
                        </div>
                        
                        <div class="col-md-6">
                            <p><strong>School:</strong><br>
                            <?= htmlspecialchars($school['school_name'] ?? 'Unknown'); ?></p>
                            
                            <p><strong>ID Type:</strong><br>
                            <?= htmlspecialchars($verification['valid_id_type']); ?></p>
                            
                            <p><strong>Contact Number:</strong><br>
                            <?= htmlspecialchars($verification['mobile_number']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <?php if ($verification['verification_status'] === 'Rejected'): ?>
                        <a href="identity_verification.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Resubmit Verification
                        </a>
                    <?php elseif ($verification['verification_status'] === 'Approved'): ?>
                        <a href="dashboard.php" class="btn btn-success">
                            <i class="bi bi-speedometer2"></i> Access Dashboard
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh Status
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h2 style="color:#012A4A; margin-bottom:20px; font-weight:700;">Confirm Logout</h2>
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your admin dashboard.</p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeLogoutModal()" style="padding:10px 20px; background-color:#e9ecef; color:#333; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Cancel</button>
            <button onclick="proceedLogout()" style="padding:10px 20px; background-color:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Logout</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'block';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function proceedLogout() {
    window.location.href = '../../../logout.php';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('logoutModal');
    if (event.target == modal) {
        closeLogoutModal();
    }
}

// Auto-refresh every 30 seconds for status updates
setInterval(() => {
    if (document.querySelector('.status-pending, .status-under-review')) {
        location.reload();
    }
}, 30000);
</script>
</body>
</html>