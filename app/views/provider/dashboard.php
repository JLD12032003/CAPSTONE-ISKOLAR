<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../index.php");
    exit();
}

// Include session timeout integration (new feature)
require_once __DIR__ . '/../../../includes/session_timeout_integration.php';

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Scholarship.php';
require_once __DIR__ . '/../../models/ProviderProfile.php';
require_once __DIR__ . '/../../models/User.php';

$database = new Database();
$conn = $database->connect();

$userModel = new User();
$scholarshipModel = new Scholarship();
$profileModel = new ProviderProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->getProfile($_SESSION['user_id']);

// Redirect to profile setup if profile doesn't exist
if (!$profile) {
    header("Location: profile_setup.php");
    exit();
}

// Get scholarships
$scholarshipModel = new Scholarship();
$scholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);

// Get statistics
$totalScholarships = count($scholarships);
$activeScholarships = count(array_filter($scholarships, fn($s) => $s['status'] == 'Active'));

$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM scholarship_applications sa
    JOIN scholarships s ON sa.scholarship_id = s.id
    WHERE s.provider_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM scholarship_awards saw
    JOIN scholarships s ON saw.scholarship_id = s.id
    WHERE s.provider_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totalAwards = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(saw.amount_awarded), 0) as total
    FROM scholarship_awards saw
    JOIN scholarships s ON saw.scholarship_id = s.id
    WHERE s.provider_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totalAwarded = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$message = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scholarship') {
    $scholarshipId = intval($_POST['scholarship_id']);
    
    try {
        if ($scholarshipModel->deleteScholarship($scholarshipId, $_SESSION['user_id'])) {
            $message = "Scholarship deleted successfully!";
            // Refresh scholarships data
            $scholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);
            
            // Recalculate statistics
            $totalScholarships = count($scholarships);
            $activeScholarships = count(array_filter($scholarships, fn($s) => $s['status'] == 'Active'));
        } else {
            $error = "Failed to delete scholarship.";
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
    <title>Provider Dashboard - ISKOLar</title>
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

    .badge-workflow {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge-draft {
        background: #e9ecef;
        color: #495057;
    }

    .badge-pending {
        background: #fff3cd;
        color: #856404;
    }

    .badge-approved {
        background: #d4edda;
        color: #155724;
    }

    .badge-rejected {
        background: #f8d7da;
        color: #721c24;
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="#">
            <i class="bi bi-mortarboard-fill"></i> ISKOLar Provider
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
                        <a class="nav-link" href="scholarships.php"><i class="bi bi-award"></i> My Scholarships</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php"><i class="bi bi-people"></i> Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_scholarship.php"><i class="bi bi-plus-circle"></i> Create Scholarship</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="communications.php"><i class="bi bi-chat-dots"></i> Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="bi bi-bar-chart"></i> Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Settings</a>
                    </li>
                </ul>
            </div>

            <!-- CONTENT -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1><i class="bi bi-hand-thumbs-up"></i> Welcome, <?= htmlspecialchars($user['fullname']); ?>!</h1>
                        <p>Manage your scholarship programs and empower deserving students</p>
                        <?php if ($profile): ?>
                            <small><i class="bi bi-building"></i> <?= htmlspecialchars($profile['organization_name']); ?></small>
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

                    <!-- Stats Row -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3><?= $activeScholarships; ?></h3>
                                <p>Active Scholarships</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3><?= $totalApplications; ?></h3>
                                <p>Total Applications</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3><?= $totalAwards; ?></h3>
                                <p>Scholars Awarded</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>₱<?= number_format($totalAwarded, 2); ?></h3>
                                <p>Total Awarded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-plus-circle text-primary"></i> Create New Scholarship</h5>
                                    <p class="card-text text-muted">Launch a new scholarship program to help more students</p>
                                    <a href="create_scholarship.php" class="btn btn-primary">Create Scholarship</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-people text-primary"></i> Review Applications</h5>
                                    <p class="card-text text-muted">Review and approve pending scholarship applications</p>
                                    <a href="applications.php" class="btn btn-primary">View Applications</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Scholarships -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-award"></i> My Scholarships</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($scholarships)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted mt-3">No scholarships yet. Create your first scholarship program!</p>
                                    <a href="create_scholarship.php" class="btn btn-primary">Create Scholarship</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Slots</th>
                                                <th>Applications</th>
                                                <th>Workflow Status</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scholarships as $scholarship): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($scholarship['title']); ?></strong></td>
                                                    <td><?= htmlspecialchars($scholarship['scholarship_type']); ?></td>
                                                    <td>₱<?= number_format($scholarship['amount'], 2); ?></td>
                                                    <td><?= $scholarship['filled_slots']; ?> / <?= $scholarship['slots']; ?></td>
                                                    <td><span class="badge bg-info"><?= $scholarship['total_applications']; ?></span></td>
                                                    <td>
                                                        <?php
                                                        $workflowStatus = $scholarship['workflow_status'] ?? 'DRAFT';
                                                        $workflowClass = 'badge-draft';
                                                        $workflowText = 'Draft';
                                                        
                                                        switch ($workflowStatus) {
                                                            case 'PENDING_SCHOOL_ADMIN_REVIEW':
                                                                $workflowClass = 'badge-pending';
                                                                $workflowText = 'Admin Review';
                                                                break;
                                                            case 'PENDING_COMMITTEE_REVIEW':
                                                                $workflowClass = 'badge-pending';
                                                                $workflowText = 'Committee Review';
                                                                break;
                                                            case 'PENDING_VP_REVIEW':
                                                                $workflowClass = 'badge-pending';
                                                                $workflowText = 'VP Review';
                                                                break;
                                                            case 'PENDING_PRESIDENT_REVIEW':
                                                                $workflowClass = 'badge-pending';
                                                                $workflowText = 'President Review';
                                                                break;
                                                            case 'APPROVED_FOR_PUBLICATION':
                                                                $workflowClass = 'badge-approved';
                                                                $workflowText = 'Approved';
                                                                break;
                                                            case 'REJECTED_BY_SCHOOL_ADMIN':
                                                            case 'REJECTED_BY_COMMITTEE':
                                                            case 'REJECTED_BY_VP':
                                                            case 'REJECTED_BY_PRESIDENT':
                                                                $workflowClass = 'badge-rejected';
                                                                $workflowText = 'Rejected';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge-workflow <?= $workflowClass; ?>"><?= $workflowText; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = 'badge-draft';
                                                        if ($scholarship['status'] == 'Active') $statusClass = 'badge-approved';
                                                        if ($scholarship['status'] == 'Closed') $statusClass = 'badge-rejected';
                                                        ?>
                                                        <span class="badge-workflow <?= $statusClass; ?>"><?= $scholarship['status']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="view_scholarship.php?id=<?= $scholarship['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit_scholarship.php?id=<?= $scholarship['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteScholarshipFromDashboard(<?= $scholarship['id']; ?>, '<?= htmlspecialchars($scholarship['title']); ?>')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card mt-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Account Created</h6>
                                        <small class="text-muted">Today</small>
                                    </div>
                                    <p class="mb-1 text-muted">Your provider account has been successfully created.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h2 style="color:#012A4A; margin-bottom:20px; font-weight:700;">Confirm Logout</h2>
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your provider dashboard.</p>
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

// Delete scholarship from dashboard
function deleteScholarshipFromDashboard(scholarshipId, scholarshipTitle) {
    if (confirm(`Are you sure you want to DELETE the scholarship "${scholarshipTitle}"?\n\nThis action cannot be undone and will permanently remove:\n- The scholarship program\n- All applications received\n- All related data\n\nType "DELETE" to confirm this action.`)) {
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