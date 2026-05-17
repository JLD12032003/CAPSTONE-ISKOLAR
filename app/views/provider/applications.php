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
$database = new Database();
$conn = $database->connect();

$profile = $profileModel->getProfile($_SESSION['user_id']);
$message = '';
$error = '';

// Get filter parameters
$scholarshipFilter = $_GET['scholarship_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Handle application status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_application_status') {
        $applicationId = intval($_POST['application_id']);
        $newStatus = $_POST['new_status'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Verify application belongs to this provider's scholarship
        $stmt = $conn->prepare("
            SELECT sa.*, s.provider_id 
            FROM scholarship_applications sa
            JOIN scholarships s ON sa.scholarship_id = s.id
            WHERE sa.id = ? AND s.provider_id = ?
        ");
        $stmt->execute([$applicationId, $_SESSION['user_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            if ($scholarshipModel->updateApplicationStatus($applicationId, $newStatus, $notes)) {
                $message = "Application status updated successfully!";
            } else {
                $error = "Failed to update application status.";
            }
        } else {
            $error = "Application not found or access denied.";
        }
    }
}

// Get provider's scholarships for filter dropdown
$providerScholarships = $scholarshipModel->getProviderScholarships($_SESSION['user_id']);

// Build query for applications
$whereConditions = ["s.provider_id = ?"];
$params = [$_SESSION['user_id']];

if ($scholarshipFilter) {
    $whereConditions[] = "s.id = ?";
    $params[] = $scholarshipFilter;
}

if ($statusFilter) {
    $whereConditions[] = "sa.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(" AND ", $whereConditions);

// Get applications
$stmt = $conn->prepare("
    SELECT sa.*, 
           u.fullname as student_name, 
           u.email as student_email,
           sp.course, 
           sp.year_level, 
           sp.gwa, 
           sp.school_name,
           sp.family_monthly_income,
           s.title as scholarship_title,
           s.amount as scholarship_amount
    FROM scholarship_applications sa
    JOIN scholarships s ON sa.scholarship_id = s.id
    JOIN users u ON sa.student_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE {$whereClause}
    ORDER BY sa.created_at DESC
");
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => count($applications),
    'submitted' => count(array_filter($applications, fn($a) => $a['status'] == 'Submitted')),
    'under_review' => count(array_filter($applications, fn($a) => $a['status'] == 'Under Review')),
    'approved' => count(array_filter($applications, fn($a) => $a['status'] == 'Approved')),
    'rejected' => count(array_filter($applications, fn($a) => $a['status'] == 'Rejected'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - ISKOLar</title>
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
        margin-bottom: 20px;
    }

    .application-card {
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
    }

    .application-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.15);
    }

    .badge-status {
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge-submitted { background: #d1ecf1; color: #0c5460; }
    .badge-under-review { background: #fff3cd; color: #856404; }
    .badge-approved { background: #d4edda; color: #155724; }
    .badge-rejected { background: #f8d7da; color: #721c24; }

    .stats-row {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stat-item {
        text-align: center;
        padding: 15px;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .filters-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), #0066ff);
        border: none;
        border-radius: 10px;
        font-weight: 600;
    }

    .student-info {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .gwa-badge {
        background: var(--secondary);
        color: var(--dark);
        padding: 4px 8px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 0.8rem;
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
                    <h1><i class="bi bi-people"></i> Scholarship Applications</h1>
                    <p class="mb-0">Review and manage student applications for your scholarships</p>
                </div>
                <a href="scholarships.php" class="btn btn-light btn-lg">
                    <i class="bi bi-award"></i> My Scholarships
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
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['total']; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['submitted']; ?></div>
                        <div class="stat-label">New Submissions</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['under_review']; ?></div>
                        <div class="stat-label">Under Review</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['approved']; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['rejected']; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?= $stats['approved'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 1) : 0; ?>%</div>
                        <div class="stat-label">Approval Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Filter by Scholarship</label>
                    <select name="scholarship_id" class="form-select">
                        <option value="">All Scholarships</option>
                        <?php foreach ($providerScholarships as $scholarship): ?>
                            <option value="<?= $scholarship['id']; ?>" 
                                    <?= $scholarshipFilter == $scholarship['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($scholarship['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Submitted" <?= $statusFilter == 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Under Review" <?= $statusFilter == 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="Approved" <?= $statusFilter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?= $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <a href="applications.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Applications List -->
        <?php if (empty($applications)): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Applications Found</h4>
                    <p class="text-muted">
                        <?php if ($scholarshipFilter || $statusFilter): ?>
                            No applications match your current filters. Try adjusting the filters above.
                        <?php else: ?>
                            You haven't received any scholarship applications yet. Make sure your scholarships are active and visible to students.
                        <?php endif; ?>
                    </p>
                    <?php if (!$scholarshipFilter && !$statusFilter): ?>
                        <a href="scholarships.php" class="btn btn-primary">
                            <i class="bi bi-award"></i> View My Scholarships
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($applications as $application): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card application-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($application['student_name']); ?></h6>
                                    <?php
                                    $statusClass = 'badge-submitted';
                                    if ($application['status'] == 'Under Review') $statusClass = 'badge-under-review';
                                    if ($application['status'] == 'Approved') $statusClass = 'badge-approved';
                                    if ($application['status'] == 'Rejected') $statusClass = 'badge-rejected';
                                    ?>
                                    <span class="badge-status <?= $statusClass; ?>"><?= $application['status']; ?></span>
                                </div>

                                <div class="student-info">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Course</small>
                                            <div class="fw-bold"><?= htmlspecialchars($application['course'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Year Level</small>
                                            <div class="fw-bold"><?= htmlspecialchars($application['year_level'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <small class="text-muted">GWA</small>
                                            <div>
                                                <?php if ($application['gwa']): ?>
                                                    <span class="gwa-badge"><?= number_format($application['gwa'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Family Income</small>
                                            <div class="fw-bold">
                                                <?php if ($application['family_monthly_income']): ?>
                                                    ₱<?= number_format($application['family_monthly_income'], 0); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">Scholarship</small>
                                    <div class="fw-bold"><?= htmlspecialchars($application['scholarship_title']); ?></div>
                                    <small class="text-success">₱<?= number_format($application['scholarship_amount'], 0); ?></small>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">Applied on</small>
                                    <div><?= date('M j, Y g:i A', strtotime($application['created_at'])); ?></div>
                                </div>

                                <?php if ($application['personal_statement']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">About Yourself</small>
                                        <div class="small"><?= htmlspecialchars(substr($application['personal_statement'], 0, 100)) . '...'; ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm flex-fill" 
                                            onclick="viewApplication(<?= $application['id']; ?>)">
                                        <i class="bi bi-eye"></i> View Details
                                    </button>
                                    <?php if ($application['status'] == 'Submitted' || $application['status'] == 'Under Review'): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Under Review')">
                                                    <i class="bi bi-clock text-warning"></i> Mark Under Review
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Approved')">
                                                    <i class="bi bi-check-circle text-success"></i> Approve
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Rejected')">
                                                    <i class="bi bi-x-circle text-danger"></i> Reject
                                                </a></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Application Details Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" onclick="closeApplicationModal()"></button>
            </div>
            <div class="modal-body" id="applicationDetails">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Application Status</h5>
                <button type="button" class="btn-close" onclick="closeStatusModal()"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_application_status">
                    <input type="hidden" name="application_id" id="statusApplicationId">
                    <input type="hidden" name="new_status" id="statusNewStatus">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" id="statusDisplay" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Add any notes or feedback for the student..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageViewerTitle">Document Preview</h5>
                <button type="button" class="btn-close" onclick="closeImageViewer()"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imageViewerImg" src="" alt="Document" class="img-fluid" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImageViewer()">Close</button>
                <a id="imageDownloadBtn" href="" class="btn btn-primary" download>
                    <i class="bi bi-download"></i> Download
                </a>
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

<script>
function viewApplication(applicationId) {
    // Load application details via AJAX
    fetch(`view_application_details.php?id=${applicationId}`)
        .then(response => {
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Application not found');
                } else if (response.status === 403) {
                    throw new Error('Access denied');
                } else {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('applicationDetails').innerHTML = html;
            
            // Show modal using vanilla JavaScript instead of Bootstrap API
            const modal = document.getElementById('applicationModal');
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'block';
                modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
                document.body.classList.add('modal-open');
                
                // Add click outside to close
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeApplicationModal();
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading application details:', error);
            alert(`Failed to load application details: ${error.message}\n\nPlease try again or contact support if the problem persists.`);
        });
}

function closeApplicationModal() {
    const modal = document.getElementById('applicationModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

function updateApplicationStatus(applicationId, newStatus) {
    document.getElementById('statusApplicationId').value = applicationId;
    document.getElementById('statusNewStatus').value = newStatus;
    document.getElementById('statusDisplay').value = newStatus;
    
    // Close any existing modals first
    closeApplicationModal();
    
    // Show status modal using vanilla JavaScript
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.classList.add('show');
        statusModal.style.display = 'block';
        statusModal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        document.body.classList.add('modal-open');
        
        // Add click outside to close
        statusModal.addEventListener('click', function(e) {
            if (e.target === statusModal) {
                closeStatusModal();
            }
        });
    }
}

function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

function viewImage(imagePath, documentName) {
    const modal = document.getElementById('imageViewerModal');
    const img = document.getElementById('imageViewerImg');
    const title = document.getElementById('imageViewerTitle');
    const downloadBtn = document.getElementById('imageDownloadBtn');
    
    if (modal && img && title && downloadBtn) {
        // Set image source and title
        img.src = imagePath;
        title.textContent = documentName;
        downloadBtn.href = imagePath;
        
        // Show modal
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        document.body.classList.add('modal-open');
        
        // Add click outside to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeImageViewer();
            }
        });
    }
}

function closeImageViewer() {
    const modal = document.getElementById('imageViewerModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        
        // Clear image source to free memory
        const img = document.getElementById('imageViewerImg');
        if (img) {
            img.src = '';
        }
    }
}

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