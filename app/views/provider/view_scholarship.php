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
$scholarshipId = intval($_GET['id'] ?? 0);

$message = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scholarship') {
    try {
        if ($scholarshipModel->deleteScholarship($scholarshipId, $_SESSION['user_id'])) {
            // Redirect to scholarships page with success message
            header("Location: scholarships.php?message=" . urlencode("Scholarship deleted successfully!"));
            exit();
        } else {
            $error = "Failed to delete scholarship.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if (!$scholarshipId) {
    header("Location: scholarships.php");
    exit();
}

// Get scholarship details
$scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
if (!$scholarship || $scholarship['provider_id'] != $_SESSION['user_id']) {
    header("Location: scholarships.php");
    exit();
}

// Get applications for this scholarship
$applications = $scholarshipModel->getScholarshipApplications($scholarshipId, $_SESSION['user_id']);

// Calculate statistics
$stats = [
    'total_applications' => count($applications),
    'submitted' => count(array_filter($applications, fn($a) => $a['status'] == 'Submitted')),
    'under_review' => count(array_filter($applications, fn($a) => $a['status'] == 'Under Review')),
    'approved' => count(array_filter($applications, fn($a) => $a['status'] == 'Approved')),
    'rejected' => count(array_filter($applications, fn($a) => $a['status'] == 'Rejected')),
    'filled_slots' => $scholarship['slots'] - $scholarship['available_slots']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($scholarship['title']); ?> - ISKOLar</title>
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

    .scholarship-header {
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
        margin-bottom: 20px;
    }

    .badge-status {
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .badge-active { background: #d4edda; color: #155724; }
    .badge-draft { background: #fff3cd; color: #856404; }
    .badge-closed { background: #f8d7da; color: #721c24; }
    .badge-pending { background: #d1ecf1; color: #0c5460; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.15);
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

    .info-section {
        background: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
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

    .application-item {
        border-left: 4px solid var(--primary);
        background: #f8f9fa;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 0 10px 10px 0;
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
        <!-- Scholarship Header -->
        <div class="scholarship-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1><?= htmlspecialchars($scholarship['title']); ?></h1>
                    <p class="mb-2"><?= htmlspecialchars($scholarship['description']); ?></p>
                    <div class="d-flex align-items-center gap-3">
                        <?php
                        $statusClass = 'badge-draft';
                        if ($scholarship['status'] == 'Active') $statusClass = 'badge-active';
                        if ($scholarship['status'] == 'Closed') $statusClass = 'badge-closed';
                        if ($scholarship['status'] == 'Pending Approval') $statusClass = 'badge-pending';
                        ?>
                        <span class="badge-status <?= $statusClass; ?>"><?= $scholarship['status']; ?></span>
                        <span class="text-light">
                            <i class="bi bi-calendar"></i> 
                            <?= date('M j, Y', strtotime($scholarship['application_start'])); ?> - 
                            <?= date('M j, Y', strtotime($scholarship['application_end'])); ?>
                        </span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-light mb-2">
                        <i class="bi bi-currency-peso"></i> 
                        <span class="h4">₱<?= number_format($scholarship['amount'], 0); ?></span>
                    </div>
                    <small class="text-light"><?= $scholarship['scholarship_type']; ?></small>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $scholarship['slots']; ?></div>
                <div class="stat-label">Total Slots</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['filled_slots']; ?></div>
                <div class="stat-label">Filled Slots</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_applications']; ?></div>
                <div class="stat-label">Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['submitted'] + $stats['under_review']; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>

        <div class="row">
            <!-- Scholarship Details -->
            <div class="col-md-8">
                <!-- Eligibility Criteria -->
                <div class="info-section">
                    <h4 class="section-title">Eligibility Criteria</h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?php if ($scholarship['eligible_courses']): ?>
                            <div class="mb-3">
                                <strong>Eligible Courses:</strong><br>
                                <?= nl2br(htmlspecialchars($scholarship['eligible_courses'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($scholarship['min_gwa']): ?>
                            <div class="mb-3">
                                <strong>Minimum GWA:</strong><br>
                                <span class="badge bg-warning text-dark"><?= number_format($scholarship['min_gwa'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($scholarship['year_levels']): ?>
                            <div class="mb-3">
                                <strong>Year Levels:</strong><br>
                                <?= htmlspecialchars(str_replace(',', ', ', $scholarship['year_levels'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <?php if ($scholarship['max_family_income']): ?>
                            <div class="mb-3">
                                <strong>Maximum Family Income:</strong><br>
                                ₱<?= number_format($scholarship['max_family_income'], 2); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($scholarship['school_name']): ?>
                            <div class="mb-3">
                                <strong>Partner School:</strong><br>
                                <?= htmlspecialchars($scholarship['school_name']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($scholarship['other_requirements']): ?>
                    <div class="mb-3">
                        <strong>Other Requirements:</strong><br>
                        <?= nl2br(htmlspecialchars($scholarship['other_requirements'])); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Applications -->
                <div class="info-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="section-title mb-0">Recent Applications</h4>
                        <a href="applications.php?scholarship_id=<?= $scholarshipId; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i> View All Applications
                        </a>
                    </div>
                    
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No applications received yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($applications, 0, 5) as $application): ?>
                            <div class="application-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($application['student_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($application['course'] ?? 'N/A'); ?> - 
                                            <?= htmlspecialchars($application['year_level'] ?? 'N/A'); ?>
                                            <?php if ($application['gwa']): ?>
                                                | GWA: <?= number_format($application['gwa'], 2); ?>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">Applied: <?= date('M j, Y', strtotime($application['created_at'])); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <?php
                                        $statusClass = 'bg-secondary';
                                        if ($application['status'] == 'Under Review') $statusClass = 'bg-warning';
                                        if ($application['status'] == 'Approved') $statusClass = 'bg-success';
                                        if ($application['status'] == 'Rejected') $statusClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $statusClass; ?>"><?= $application['status']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($applications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="applications.php?scholarship_id=<?= $scholarshipId; ?>" class="btn btn-outline-primary">
                                    View All <?= count($applications); ?> Applications
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Sidebar -->
            <div class="col-md-4">
                <div class="info-section">
                    <h4 class="section-title">Actions</h4>
                    
                    <div class="d-grid gap-2">
                        <a href="edit_scholarship.php?id=<?= $scholarshipId; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil-square"></i> Edit Scholarship
                        </a>
                        
                        <a href="applications.php?scholarship_id=<?= $scholarshipId; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-people"></i> Manage Applications
                        </a>
                        
                        <?php if ($scholarship['status'] == 'Draft'): ?>
                            <button class="btn btn-success" onclick="updateStatus('Active')">
                                <i class="bi bi-play-circle"></i> Activate Scholarship
                            </button>
                        <?php elseif ($scholarship['status'] == 'Active'): ?>
                            <button class="btn btn-warning" onclick="updateStatus('Closed')">
                                <i class="bi bi-stop-circle"></i> Close Applications
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-danger" onclick="deleteScholarship()">
                            <i class="bi bi-trash"></i> Delete Scholarship
                        </button>
                        
                        <a href="scholarships.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to My Scholarships
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="info-section">
                    <h4 class="section-title">Quick Stats</h4>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Approval Rate:</span>
                            <strong>
                                <?= $stats['total_applications'] > 0 ? round(($stats['approved'] / $stats['total_applications']) * 100, 1) : 0; ?>%
                            </strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Available Slots:</span>
                            <strong><?= $scholarship['available_slots']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Days Remaining:</span>
                            <strong>
                                <?php
                                $daysLeft = max(0, floor((strtotime($scholarship['application_end']) - time()) / (60 * 60 * 24)));
                                echo $daysLeft;
                                ?>
                            </strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Created:</span>
                            <strong><?= date('M j, Y', strtotime($scholarship['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Form (Hidden) -->
<form id="statusUpdateForm" method="POST" action="scholarships.php" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="scholarship_id" value="<?= $scholarshipId; ?>">
    <input type="hidden" name="new_status" id="newStatus">
</form>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_scholarship">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateStatus(newStatus) {
    if (confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
        document.getElementById('newStatus').value = newStatus;
        document.getElementById('statusUpdateForm').submit();
    }
}

function deleteScholarship() {
    const scholarshipTitle = "<?= htmlspecialchars($scholarship['title']); ?>";
    if (confirm(`Are you sure you want to DELETE the scholarship "${scholarshipTitle}"?\n\nThis action cannot be undone and will permanently remove:\n- The scholarship program\n- All applications received\n- All related data\n\nType "DELETE" to confirm this action.`)) {
        const confirmation = prompt('Please type "DELETE" to confirm:');
        if (confirmation === 'DELETE') {
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