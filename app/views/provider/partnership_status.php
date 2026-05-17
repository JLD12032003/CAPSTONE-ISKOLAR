<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/ProviderProfile.php';
require_once __DIR__ . '/../../controllers/PartnershipController.php';

$profileModel = new ProviderProfile();
$partnershipController = new PartnershipController();

$profile = $profileModel->getProfile($_SESSION['user_id']);
if (!$profile) {
    header("Location: profile_setup.php");
    exit();
}

// Get provider's partnership requests
$result = $partnershipController->getProviderRequests();
$requests = $result['success'] ? $result['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Status - ISKOLar</title>
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
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.15);
    }

    .request-card {
        border-left: 5px solid var(--primary);
    }

    .status-badge {
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-committee-review { background: #d1ecf1; color: #0c5460; }
    .status-vp-review { background: #e2e3ff; color: #383d41; }
    .status-president-review { background: #f8d7da; color: #721c24; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }

    .progress-bar-custom {
        height: 8px;
        border-radius: 10px;
        background: #e9ecef;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 10px;
        transition: width 0.3s ease;
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

    .timeline-item.active::before {
        background: var(--primary);
    }

    .timeline-item.completed::before {
        background: #28a745;
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
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($profile['organization_name']); ?>
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
                    <h1><i class="bi bi-clipboard-check"></i> Partnership Status</h1>
                    <p class="mb-0">Track your partnership requests and approval progress</p>
                </div>
                <a href="partnership_request.php" class="btn btn-light btn-lg">
                    <i class="bi bi-plus-circle"></i> New Partnership Request
                </a>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <!-- No Requests -->
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Partnership Requests</h4>
                    <p class="text-muted">You haven't submitted any partnership requests yet.</p>
                    <a href="partnership_request.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-handshake"></i> Submit Your First Request
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Partnership Requests -->
            <div class="row">
                <?php foreach ($requests as $request): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card request-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($request['partnership_title']); ?></h6>
                                    <?php
                                    $statusClass = 'status-' . strtolower(str_replace('_', '-', $request['current_stage']));
                                    $statusText = ucwords(str_replace('_', ' ', $request['current_stage']));
                                    ?>
                                    <span class="status-badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">School Partner</small>
                                    <div class="fw-bold"><?= htmlspecialchars($request['school_name']); ?></div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Amount</small>
                                        <div class="fw-bold text-success">₱<?= number_format($request['proposed_scholarship_amount'], 0); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Slots</small>
                                        <div class="fw-bold"><?= $request['proposed_scholarship_slots']; ?></div>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">Progress</small>
                                        <small class="text-muted"><?= $request['progress_percentage']; ?>%</small>
                                    </div>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill" style="width: <?= $request['progress_percentage']; ?>%"></div>
                                    </div>
                                </div>

                                <!-- Timeline -->
                                <div class="timeline">
                                    <div class="timeline-item <?= in_array($request['current_stage'], ['COMMITTEE_REVIEW', 'VP_REVIEW', 'PRESIDENT_REVIEW', 'APPROVED']) ? 'active' : ''; ?> <?= $request['progress_percentage'] > 25 ? 'completed' : ''; ?>">
                                        <small class="text-muted">Committee Review</small>
                                    </div>
                                    <div class="timeline-item <?= in_array($request['current_stage'], ['VP_REVIEW', 'PRESIDENT_REVIEW', 'APPROVED']) ? 'active' : ''; ?> <?= $request['progress_percentage'] > 50 ? 'completed' : ''; ?>">
                                        <small class="text-muted">VP Approval</small>
                                    </div>
                                    <div class="timeline-item <?= in_array($request['current_stage'], ['PRESIDENT_REVIEW', 'APPROVED']) ? 'active' : ''; ?> <?= $request['progress_percentage'] > 75 ? 'completed' : ''; ?>">
                                        <small class="text-muted">President Approval</small>
                                    </div>
                                    <div class="timeline-item <?= $request['current_stage'] === 'APPROVED' ? 'completed' : ''; ?>">
                                        <small class="text-muted">Partnership Active</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">Submitted</small>
                                    <div><?= date('M j, Y g:i A', strtotime($request['submitted_at'])); ?></div>
                                </div>

                                <?php if ($request['current_stage'] === 'REJECTED'): ?>
                                    <div class="alert alert-danger alert-sm">
                                        <small><strong>Rejected:</strong> <?= htmlspecialchars($request['rejected_by_stage']); ?></small>
                                        <?php if ($request['rejection_reason']): ?>
                                            <br><small><?= htmlspecialchars($request['rejection_reason']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($request['current_stage'] === 'APPROVED'): ?>
                                    <div class="alert alert-success alert-sm">
                                        <small><i class="bi bi-check-circle"></i> <strong>Partnership Approved!</strong></small>
                                        <br><small>You can now create scholarship programs.</small>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm flex-fill" 
                                            onclick="viewDetails(<?= $request['id']; ?>)">
                                        <i class="bi bi-eye"></i> View Details
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" 
                                            onclick="viewLogs(<?= $request['id']; ?>)">
                                        <i class="bi bi-clock-history"></i> Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Partnership Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Audit Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewDetails(requestId) {
    fetch(`/api/partnerships/${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const request = data.data;
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Organization Information</h6>
                            <p><strong>Name:</strong> ${request.organization_name}</p>
                            <p><strong>Type:</strong> ${request.organization_type}</p>
                            <p><strong>Contact:</strong> ${request.contact_person}</p>
                            <p><strong>Email:</strong> ${request.contact_email}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Partnership Details</h6>
                            <p><strong>School:</strong> ${request.school_name}</p>
                            <p><strong>Amount:</strong> ₱${parseFloat(request.proposed_scholarship_amount).toLocaleString()}</p>
                            <p><strong>Slots:</strong> ${request.proposed_scholarship_slots}</p>
                            <p><strong>Duration:</strong> ${request.partnership_duration_years} year(s)</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6 class="text-primary">Description</h6>
                        <p>${request.partnership_description}</p>
                    </div>
                    <div class="mt-3">
                        <h6 class="text-primary">Status Information</h6>
                        <p><strong>Current Stage:</strong> ${request.current_stage.replace('_', ' ')}</p>
                        <p><strong>Submitted:</strong> ${new Date(request.submitted_at).toLocaleString()}</p>
                        ${request.final_decision_at ? `<p><strong>Final Decision:</strong> ${new Date(request.final_decision_at).toLocaleString()}</p>` : ''}
                    </div>
                `;
                document.getElementById('detailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            } else {
                alert('Failed to load request details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load request details');
        });
}

function viewLogs(requestId) {
    fetch(`/api/partnerships/${requestId}/logs`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const logs = data.data;
                let content = '<div class="timeline">';
                
                logs.forEach(log => {
                    content += `
                        <div class="timeline-item">
                            <div class="d-flex justify-content-between">
                                <strong>${log.event_type.replace('_', ' ')}</strong>
                                <small class="text-muted">${new Date(log.logged_at).toLocaleString()}</small>
                            </div>
                            ${log.event_description ? `<p class="mb-1">${log.event_description}</p>` : ''}
                            ${log.stage_name ? `<small class="text-muted">Stage: ${log.stage_name}</small>` : ''}
                        </div>
                    `;
                });
                
                content += '</div>';
                document.getElementById('logsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('logsModal')).show();
            } else {
                alert('Failed to load audit logs');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load audit logs');
        });
}

// Auto-refresh every 30 seconds for status updates
setInterval(() => {
    location.reload();
}, 30000);
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