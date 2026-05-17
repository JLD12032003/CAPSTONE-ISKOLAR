<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

// Include session timeout integration (new feature)
require_once __DIR__ . '/../../../includes/session_timeout_integration.php';

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ScholarshipWorkflowController.php';

$database = new Database();
$conn = $database->connect();
$workflowController = new ScholarshipWorkflowController();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../../index.php");
    exit();
}

// Check if admin has completed identity verification
$verificationStmt = $conn->prepare("SELECT verification_status FROM admin_verifications WHERE user_id = ?");
$verificationStmt->execute([$_SESSION['user_id']]);
$verification = $verificationStmt->fetch(PDO::FETCH_ASSOC);

// If no verification record exists, redirect to identity verification
if (!$verification) {
    header("Location: identity_verification.php");
    exit();
}

// If verification is not approved, redirect to verification status page
if ($verification['verification_status'] !== 'Approved') {
    header("Location: verification_status.php");
    exit();
}

// Get school information
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$user['school_id']]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u
    WHERE u.user_type = 'student' AND u.school_id = ?
");
$stmt->execute([$user['school_id']]);
$totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as count 
    FROM scholarships s
    WHERE s.school_id = ? AND s.status = 'Active'
");
$stmt->execute([$user['school_id']]);
$activeScholarships = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT sa.id) as count 
    FROM scholarship_applications sa
    JOIN users u ON sa.student_id = u.id
    WHERE u.school_id = ?
");
$stmt->execute([$user['school_id']]);
$totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT saw.id) as count 
    FROM scholarship_awards saw
    JOIN users u ON saw.student_id = u.id
    WHERE u.school_id = ?
");
$stmt->execute([$user['school_id']]);
$totalAwards = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent scholarships
$stmt = $conn->prepare("
    SELECT s.*, u.fullname as provider_name, pp.organization_name
    FROM scholarships s
    JOIN users u ON s.provider_id = u.id
    LEFT JOIN provider_profiles pp ON u.id = pp.user_id
    WHERE s.school_id = ?
    ORDER BY s.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['school_id']]);
$recentScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending communications
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM communications 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get workflow statistics and pending approvals
$workflowStats = $workflowController->getWorkflowStatistics($user['school_id']);
$pendingApprovals = $workflowController->getAdminPendingApprovals();

$message = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scholarship') {
    $scholarshipId = intval($_POST['scholarship_id']);
    
    require_once __DIR__ . '/../../models/Scholarship.php';
    $scholarshipModel = new Scholarship();
    
    try {
        // Verify the scholarship belongs to this school
        $scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
        if ($scholarship && $scholarship['school_id'] == $user['school_id']) {
            if ($scholarshipModel->deleteScholarship($scholarshipId, $scholarship['provider_id'])) {
                $message = "Scholarship deleted successfully!";
                // Refresh data
                $workflowStats = $workflowController->getWorkflowStatistics($user['school_id']);
                $pendingApprovals = $workflowController->getAdminPendingApprovals();
            } else {
                $error = "Failed to delete scholarship.";
            }
        } else {
            $error = "Scholarship not found or access denied.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISKOLar</title>
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

    .sidebar {
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        height: 100vh;
        position: sticky;
        top: 0;
        padding-top: 20px;
    }

    .sidebar .nav-link {
        color: #555;
        padding: 15px 20px;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .sidebar .nav-link:hover, .sidebar .nav-link.active {
        color: var(--primary);
        background: #f0f5ff;
        border-left-color: var(--primary);
    }

    .dashboard-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
    }

    .card {
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-radius: 12px;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.15);
    }

    .stats-box {
        text-align: center;
        padding: 30px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stats-box h3 {
        color: var(--primary);
        font-weight: 700;
        font-size: 2.5rem;
        margin-bottom: 10px;
    }

    .stats-box p {
        color: #666;
        margin: 0;
        font-weight: 500;
    }

    .badge-admin {
        background: var(--secondary);
        color: var(--dark);
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="#">
            <i class="bi bi-shield-check"></i> ISKOLar Admin
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['fullname']); ?>
                <span class="badge-admin ms-2">Admin</span>
            </span>
            <a class="btn btn-sm btn-outline-light" href="#" onclick="confirmLogout(); return false;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div style="margin-top: 70px;">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-2 sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-house-fill"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#workflow-section"><i class="bi bi-diagram-3"></i> Scholarship Approvals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="published_scholarships.php"><i class="bi bi-award-fill"></i> Published Scholarships</a>
                    </li>
                </ul>
            </div>

            <!-- CONTENT -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1><i class="bi bi-shield-check"></i> Welcome, <?= htmlspecialchars($user['fullname']); ?>!</h1>
                        <p>School Administration Dashboard</p>
                        <?php if ($school): ?>
                            <h5 class="mt-3"><i class="bi bi-building"></i> <?= htmlspecialchars($school['school_name']); ?></h5>
                            <small><?= htmlspecialchars($school['address']); ?>, <?= htmlspecialchars($school['city']); ?></small>
                        <?php endif; ?>
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

                    <!-- Essential Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stats-box">
                                <h3><?= $activeScholarships; ?></h3>
                                <p>Active Scholarships</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-box">
                                <h3><?= $totalApplications; ?></h3>
                                <p>Total Applications</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions - Enhanced -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-diagram-3" style="font-size: 3rem; color: var(--primary);"></i>
                                    <h5 class="mt-3">Scholarship Approval Workflow</h5>
                                    <p class="text-muted">Review and approve scholarship applications</p>
                                    <?php 
                                    $pendingCount = 0;
                                    if ($pendingApprovals['success']) {
                                        $pendingCount = count($pendingApprovals['pending_approvals']);
                                    }
                                    ?>
                                    <a href="#workflow-section" class="btn btn-primary btn-lg">
                                        <i class="bi bi-eye"></i> Review Applications
                                        <?php if ($pendingCount > 0): ?>
                                            <span class="badge bg-warning text-dark ms-2"><?= $pendingCount; ?> Pending</span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-award-fill" style="font-size: 3rem; color: var(--secondary);"></i>
                                    <h5 class="mt-3">Published Scholarships</h5>
                                    <p class="text-muted">View all active and published scholarship programs</p>
                                    <a href="published_scholarships.php" class="btn btn-warning btn-lg">
                                        <i class="bi bi-list-ul"></i> View Published Scholarships
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scholarship Workflow Monitoring -->
                    <div id="workflow-section" class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Scholarship Approval Workflow</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($workflowStats['success']): ?>
                                <!-- Workflow Statistics -->
                                <div class="row mb-4">
                                    <?php 
                                    $stats = [];
                                    foreach ($workflowStats['statistics'] as $stat) {
                                        $stats[$stat['workflow_status']] = $stat['count'];
                                    }
                                    ?>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 border rounded">
                                            <h4 class="text-warning"><?= $stats['PENDING_SCHOOL_ADMIN_REVIEW'] ?? 0; ?></h4>
                                            <small>Pending Admin Review</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 border rounded">
                                            <h4 class="text-info"><?= $stats['PENDING_COMMITTEE_REVIEW'] ?? 0; ?></h4>
                                            <small>Committee Review</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 border rounded">
                                            <h4 class="text-primary"><?= $stats['PENDING_VP_REVIEW'] ?? 0; ?></h4>
                                            <small>VP Review</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 border rounded">
                                            <h4 class="text-success"><?= $stats['APPROVED_FOR_PUBLICATION'] ?? 0; ?></h4>
                                            <small>Published</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Approvals -->
                                <?php if ($pendingApprovals['success'] && !empty($pendingApprovals['pending_approvals'])): ?>
                                    <h6><i class="bi bi-clock"></i> Pending Approvals</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Scholarship</th>
                                                    <th>Provider</th>
                                                    <th>Current Stage</th>
                                                    <th>Submitted</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingApprovals['pending_approvals'] as $approval): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($approval['title']); ?></strong></td>
                                                        <td><?= htmlspecialchars($approval['organization_name'] ?? $approval['provider_name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-warning">
                                                                <?= ucwords(str_replace('_', ' ', $approval['stage_name'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M j, Y', strtotime($approval['stage_created_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="review_scholarship.php?id=<?= $approval['id']; ?>" class="btn btn-sm btn-primary">
                                                                    <i class="bi bi-eye"></i> Review & Forward
                                                                </a>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteScholarshipAdmin(<?= $approval['id']; ?>, '<?= htmlspecialchars($approval['title']); ?>')">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-check-circle" style="font-size: 2rem; color: #28a745;"></i>
                                        <p class="text-muted mt-2">No pending approvals at this time.</p>
                                    </div>
                                <?php endif; ?>

                                <!-- Recent Workflow Activity -->
                                <?php if (!empty($workflowStats['recent_activity'])): ?>
                                    <h6 class="mt-4"><i class="bi bi-activity"></i> Recent Activity</h6>
                                    <div class="list-group">
                                        <?php foreach (array_slice($workflowStats['recent_activity'], 0, 5) as $activity): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($activity['scholarship_title']); ?></h6>
                                                    <small><?= date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-1"><?= ucwords(str_replace('_', ' ', $activity['action_type'])); ?></p>
                                                <?php if ($activity['action_details']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($activity['action_details']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Unable to load workflow statistics.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- School Information - Simplified -->
                    <?php if ($school): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-building"></i> School Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <p><strong>School:</strong> <?= htmlspecialchars($school['school_name']); ?></p>
                                    <p><strong>Contact:</strong> <?= htmlspecialchars($school['contact_number']); ?> | <?= htmlspecialchars($school['email']); ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($school['address']); ?>, <?= htmlspecialchars($school['city']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- System Notice - Removed -->
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

// Delete scholarship from admin dashboard
function deleteScholarshipAdmin(scholarshipId, scholarshipTitle) {
    if (confirm(`Are you sure you want to DELETE the scholarship "${scholarshipTitle}"?\n\nThis action cannot be undone and will permanently remove:\n- The scholarship program\n- All applications received\n- All workflow records\n- All related data\n\nType "DELETE" to confirm this action.`)) {
        const confirmation = prompt('Please type "DELETE" to confirm:');
        if (confirmation === 'DELETE') {
            // Create and submit delete form
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_scholarship';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'scholarship_id';
            idInput.value = scholarshipId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Deletion cancelled. You must type "DELETE" exactly to confirm.');
        }
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('logoutModal');
    if (event.target == modal) {
        closeLogoutModal();
    }
}
</script>
</body>
</html>