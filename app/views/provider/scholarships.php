<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Scholarship.php';
require_once __DIR__ . '/../../models/ProviderProfile.php';

$scholarshipModel = new Scholarship();
$profileModel = new ProviderProfile();

$profile = $profileModel->getProfile($_SESSION['user_id']);
$scholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);

$message = '';
$error = '';

// Check for URL parameters (success messages)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Handle status updates and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['scholarship_id']) && isset($_POST['new_status'])) {
        $scholarshipId = intval($_POST['scholarship_id']);
        $newStatus = $_POST['new_status'];
        
        // Verify scholarship belongs to this provider
        $scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
        if ($scholarship && $scholarship['provider_id'] == $_SESSION['user_id']) {
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("UPDATE scholarships SET status = ? WHERE id = ?");
            if ($stmt->execute([$newStatus, $scholarshipId])) {
                $message = "Scholarship status updated successfully!";
                // Refresh scholarships data
                $scholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);
            } else {
                $error = "Failed to update scholarship status.";
            }
        } else {
            $error = "Scholarship not found or access denied.";
        }
    } elseif ($_POST['action'] === 'delete_scholarship' && isset($_POST['scholarship_id'])) {
        $scholarshipId = intval($_POST['scholarship_id']);
        
        try {
            if ($scholarshipModel->deleteScholarship($scholarshipId, $_SESSION['user_id'])) {
                $message = "Scholarship deleted successfully!";
                // Refresh scholarships data
                $scholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);
            } else {
                $error = "Failed to delete scholarship.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Scholarships - ISKOLar</title>
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
        border-left: 5px solid var(--primary);
    }

    .badge-status {
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge-active { background: #d4edda; color: #155724; }
    .badge-draft { background: #fff3cd; color: #856404; }
    .badge-closed { background: #f8d7da; color: #721c24; }
    .badge-pending { background: #d1ecf1; color: #0c5460; }

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
            <i class="bi bi-mortarboard-fill"></i> ISKOLar Provider
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($profile['organization_name'] ?? 'Provider'); ?>
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
                    <h1><i class="bi bi-award"></i> My Scholarships</h1>
                    <p class="mb-0">Manage your scholarship programs and track applications</p>
                </div>
                <a href="create_scholarship.php" class="btn btn-light btn-lg">
                    <i class="bi bi-plus-circle"></i> Create New Scholarship
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
        <?php
        $totalScholarships = count($scholarships);
        $activeScholarships = count(array_filter($scholarships, fn($s) => $s['status'] == 'Active'));
        $draftScholarships = count(array_filter($scholarships, fn($s) => $s['status'] == 'Draft'));
        $totalApplications = array_sum(array_column($scholarships, 'total_applications'));
        ?>
        
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $totalScholarships; ?></div>
                        <div class="stat-label">Total Scholarships</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $activeScholarships; ?></div>
                        <div class="stat-label">Active Scholarships</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $draftScholarships; ?></div>
                        <div class="stat-label">Draft Scholarships</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= $totalApplications; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <ul class="nav nav-pills" id="scholarshipTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="pill" href="#all">All Scholarships</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#active">Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#draft">Draft</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#closed">Closed</a>
                </li>
            </ul>
        </div>

        <!-- Scholarships List -->
        <div class="tab-content">
            <div class="tab-pane fade show active" id="all">
                <?php if (empty($scholarships)): ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                            <h4 class="mt-3">No Scholarships Yet</h4>
                            <p class="text-muted">Create your first scholarship program to start helping students!</p>
                            <a href="create_scholarship.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle"></i> Create Your First Scholarship
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($scholarships as $scholarship): ?>
                            <div class="col-md-6 col-lg-4 mb-4 scholarship-item" data-status="<?= strtolower($scholarship['status']); ?>">
                                <div class="card scholarship-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?= htmlspecialchars($scholarship['title']); ?></h5>
                                            <?php
                                            $statusClass = 'badge-draft';
                                            if ($scholarship['status'] == 'Active') $statusClass = 'badge-active';
                                            if ($scholarship['status'] == 'Closed') $statusClass = 'badge-closed';
                                            if ($scholarship['status'] == 'Pending Approval') $statusClass = 'badge-pending';
                                            ?>
                                            <span class="badge-status <?= $statusClass; ?>"><?= $scholarship['status']; ?></span>
                                        </div>
                                        
                                        <p class="card-text text-muted"><?= htmlspecialchars(substr($scholarship['description'], 0, 100)) . '...'; ?></p>
                                        
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

                                        <div class="d-flex gap-2">
                                            <a href="view_scholarship.php?id=<?= $scholarship['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="edit_scholarship.php?id=<?= $scholarship['id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-info btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($scholarship['status'] == 'Draft'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $scholarship['id']; ?>, 'Active')">
                                                            <i class="bi bi-play-circle text-success"></i> Activate
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <?php if ($scholarship['status'] == 'Active'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $scholarship['id']; ?>, 'Closed')">
                                                            <i class="bi bi-stop-circle text-warning"></i> Close
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <?php if ($scholarship['status'] != 'Draft'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $scholarship['id']; ?>, 'Draft')">
                                                            <i class="bi bi-file-earmark text-secondary"></i> Move to Draft
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="applications.php?scholarship_id=<?= $scholarship['id']; ?>">
                                                        <i class="bi bi-people text-primary"></i> View Applications
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteScholarship(<?= $scholarship['id']; ?>, '<?= htmlspecialchars($scholarship['title']); ?>')">
                                                        <i class="bi bi-trash"></i> Delete Scholarship
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

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_scholarship">
    <input type="hidden" name="scholarship_id" id="deleteScholarshipId">
</form>

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

// Delete scholarship
function deleteScholarship(scholarshipId, scholarshipTitle) {
    if (confirm(`Are you sure you want to DELETE the scholarship "${scholarshipTitle}"?\n\nThis action cannot be undone and will permanently remove:\n- The scholarship program\n- All applications received\n- All related data\n\nType "DELETE" to confirm this action.`)) {
        const confirmation = prompt('Please type "DELETE" to confirm:');
        if (confirmation === 'DELETE') {
            document.getElementById('deleteScholarshipId').value = scholarshipId;
            document.getElementById('deleteForm').submit();
        } else {
            alert('Deletion cancelled. You must type "DELETE" exactly to confirm.');
        }
    }
}
</script>

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