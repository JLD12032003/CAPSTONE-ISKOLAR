<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Scholarship.php';

$database = new Database();
$conn = $database->connect();
$scholarshipModel = new Scholarship();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../../index.php");
    exit();
}

// Get school information
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$user['school_id']]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Handle scholarship status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['scholarship_id']) && isset($_POST['new_status'])) {
        $scholarshipId = intval($_POST['scholarship_id']);
        $newStatus = $_POST['new_status'];
        
        // Verify the scholarship belongs to this school
        $stmt = $conn->prepare("SELECT id FROM scholarships WHERE id = ? AND school_id = ?");
        $stmt->execute([$scholarshipId, $user['school_id']]);
        $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($scholarship) {
            $stmt = $conn->prepare("UPDATE scholarships SET status = ? WHERE id = ?");
            if ($stmt->execute([$newStatus, $scholarshipId])) {
                $message = "Scholarship status updated successfully!";
            } else {
                $error = "Failed to update scholarship status.";
            }
        } else {
            $error = "Scholarship not found or access denied.";
        }
    }
}

// Get published scholarships for this school
$stmt = $conn->prepare("
    SELECT s.*, u.fullname as provider_name, pp.organization_name,
           COUNT(DISTINCT sa.id) as total_applications,
           COUNT(DISTINCT saw.id) as total_awards,
           (s.slots - s.available_slots) as filled_slots
    FROM scholarships s
    JOIN users u ON s.provider_id = u.id
    LEFT JOIN provider_profiles pp ON u.id = pp.user_id
    LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
    LEFT JOIN scholarship_awards saw ON s.id = saw.scholarship_id
    WHERE s.school_id = ? AND s.workflow_status = 'APPROVED_FOR_PUBLICATION'
    GROUP BY s.id
    ORDER BY s.published_at DESC, s.created_at DESC
");
$stmt->execute([$user['school_id']]);
$publishedScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalPublished = count($publishedScholarships);
$activeScholarships = count(array_filter($publishedScholarships, fn($s) => $s['status'] == 'Active'));
$totalApplications = array_sum(array_column($publishedScholarships, 'total_applications'));
$totalAwards = array_sum(array_column($publishedScholarships, 'total_awards'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Published Scholarships - ISKOLar Admin</title>
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

    .page-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .card {
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-radius: 15px;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.15);
    }

    .scholarship-card {
        border-left: 5px solid var(--secondary);
    }

    .badge-status {
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge-active { background: #d4edda; color: #155724; }
    .badge-closed { background: #f8d7da; color: #721c24; }
    .badge-draft { background: #fff3cd; color: #856404; }

    .stats-row {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stat-item {
        text-align: center;
        padding: 20px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-weight: 500;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), #0066ff);
        border: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.3);
    }

    .filter-tabs {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .nav-pills .nav-link {
        border-radius: 10px;
        font-weight: 600;
        margin-right: 10px;
    }

    .nav-pills .nav-link.active {
        background-color: var(--primary);
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
                <span class="badge" style="background: var(--secondary); color: var(--dark);">Admin</span>
            </span>
            <a class="btn btn-sm btn-outline-light" href="#" onclick="confirmLogout(); return false;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-award-fill"></i> Published Scholarships</h1>
                    <p class="mb-0">View and manage all published scholarship programs</p>
                    <?php if ($school): ?>
                        <small><i class="bi bi-building"></i> <?= htmlspecialchars($school['school_name']); ?></small>
                    <?php endif; ?>
                </div>
                <a href="dashboard.php" class="btn btn-light btn-lg">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
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

        <!-- Statistics -->
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $totalPublished; ?></div>
                        <div class="stat-label">Published Scholarships</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $activeScholarships; ?></div>
                        <div class="stat-label">Currently Active</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $totalApplications; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $totalAwards; ?></div>
                        <div class="stat-label">Awards Given</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <ul class="nav nav-pills" id="scholarshipTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="pill" href="#all">All Published</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#active">Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#closed">Closed</a>
                </li>
            </ul>
        </div>

        <!-- Scholarships List -->
        <div class="tab-content">
            <div class="tab-pane fade show active" id="all">
                <?php if (empty($publishedScholarships)): ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                            <h4 class="mt-3">No Published Scholarships</h4>
                            <p class="text-muted">No scholarships have been published yet. Scholarships appear here after completing the approval workflow.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($publishedScholarships as $scholarship): ?>
                            <div class="col-md-6 col-lg-4 mb-4 scholarship-item" data-status="<?= strtolower($scholarship['status']); ?>">
                                <div class="card scholarship-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?= htmlspecialchars($scholarship['title']); ?></h5>
                                            <?php
                                            $statusClass = 'badge-draft';
                                            if ($scholarship['status'] == 'Active') $statusClass = 'badge-active';
                                            if ($scholarship['status'] == 'Closed') $statusClass = 'badge-closed';
                                            ?>
                                            <span class="badge-status <?= $statusClass; ?>"><?= $scholarship['status']; ?></span>
                                        </div>
                                        
                                        <p class="card-text text-muted"><?= htmlspecialchars(substr($scholarship['description'], 0, 100)) . '...'; ?></p>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <strong>Provider:</strong> <?= htmlspecialchars($scholarship['organization_name'] ?? $scholarship['provider_name']); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <small class="text-muted">Amount</small>
                                                <div class="fw-bold">₱<?= number_format($scholarship['amount'], 0); ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Slots</small>
                                                <div class="fw-bold"><?= $scholarship['filled_slots']; ?>/<?= $scholarship['slots']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Applications</small>
                                                <div class="fw-bold"><?= $scholarship['total_applications']; ?></div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                <?= date('M j, Y', strtotime($scholarship['application_start'])); ?> - 
                                                <?= date('M j, Y', strtotime($scholarship['application_end'])); ?>
                                            </small>
                                        </div>

                                        <?php if ($scholarship['published_at']): ?>
                                            <div class="mb-3">
                                                <small class="text-success">
                                                    <i class="bi bi-check-circle"></i> 
                                                    Published: <?= date('M j, Y g:i A', strtotime($scholarship['published_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2">
                                            <a href="../provider/view_scholarship.php?id=<?= $scholarship['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($scholarship['status'] == 'Active'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $scholarship['id']; ?>, 'Closed')">
                                                            <i class="bi bi-stop-circle text-warning"></i> Close Applications
                                                        </a></li>
                                                    <?php elseif ($scholarship['status'] == 'Closed'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $scholarship['id']; ?>, 'Active')">
                                                            <i class="bi bi-play-circle text-success"></i> Reopen Applications
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="../provider/applications.php?scholarship_id=<?= $scholarship['id']; ?>">
                                                        <i class="bi bi-people text-primary"></i> View Applications
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Form (Hidden) -->
<form id="statusUpdateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="scholarship_id" id="statusScholarshipId">
    <input type="hidden" name="new_status" id="statusNewStatus">
</form>

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
// Filter scholarships by status
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('#scholarshipTabs .nav-link');
    const scholarshipItems = document.querySelectorAll('.scholarship-item');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const filter = this.getAttribute('href').substring(1);
            
            scholarshipItems.forEach(item => {
                if (filter === 'all') {
                    item.style.display = 'block';
                } else {
                    const status = item.getAttribute('data-status');
                    item.style.display = status === filter ? 'block' : 'none';
                }
            });
        });
    });
});

// Update scholarship status
function updateStatus(scholarshipId, newStatus) {
    if (confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
        document.getElementById('statusScholarshipId').value = scholarshipId;
        document.getElementById('statusNewStatus').value = newStatus;
        document.getElementById('statusUpdateForm').submit();
    }
}

// Logout functions
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
</script>
</body>
</html>