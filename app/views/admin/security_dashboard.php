<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../core/ActivityLogger.php';
require_once __DIR__ . '/../../core/LogEncryption.php';

$logger = new ActivityLogger();
$encryption = new LogEncryption();

// Check if admin has permission to view security logs
if (!$encryption->canReadLogCategory($_SESSION['user_id'], 'security_events')) {
    die('Access Denied: You do not have permission to view security logs.');
}

$message = '';
$error = '';

// Handle security event resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve_event') {
        $eventId = intval($_POST['event_id']);
        
        try {
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("
                UPDATE security_events 
                SET resolved = TRUE, resolved_by = ?, resolved_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$_SESSION['user_id'], $eventId])) {
                $message = "Security event resolved successfully.";
                
                // Log the resolution
                $logger->logAdminActivity(
                    $_SESSION['user_id'],
                    'SYSTEM_CONFIG',
                    'security_event',
                    $eventId,
                    "Resolved security event ID: $eventId",
                    null,
                    'MEDIUM'
                );
            } else {
                $error = "Failed to resolve security event.";
            }
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

try {
    $securityData = $logger->getSecurityDashboard($_SESSION['user_id']);
} catch (Exception $e) {
    $error = "Failed to load security dashboard: " . $e->getMessage();
    $securityData = ['security_events' => [], 'failed_logins' => [], 'high_risk_activities' => []];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - ISKOLar Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #0055FF;
        --secondary: #FDC500;
        --dark: #012A4A;
        --light: #F8F9FA;
        --danger: #dc3545;
        --warning: #ffc107;
        --success: #28a745;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--light);
    }

    .navbar {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        margin-bottom: 20px;
    }

    .security-card {
        border-left: 5px solid var(--danger);
    }

    .security-card.critical {
        border-left-color: #dc3545;
        background: linear-gradient(135deg, #fff5f5, #ffffff);
    }

    .security-card.high {
        border-left-color: #fd7e14;
        background: linear-gradient(135deg, #fff8f0, #ffffff);
    }

    .security-card.medium {
        border-left-color: #ffc107;
        background: linear-gradient(135deg, #fffbf0, #ffffff);
    }

    .security-card.low {
        border-left-color: #28a745;
        background: linear-gradient(135deg, #f0fff4, #ffffff);
    }

    .severity-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .severity-critical { background: #dc3545; color: white; }
    .severity-high { background: #fd7e14; color: white; }
    .severity-medium { background: #ffc107; color: #212529; }
    .severity-low { background: #28a745; color: white; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        text-align: center;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .stat-label {
        color: #666;
        font-weight: 500;
    }

    .activity-timeline {
        position: relative;
        padding-left: 30px;
    }

    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .activity-item {
        position: relative;
        margin-bottom: 20px;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .activity-item::before {
        content: '';
        position: absolute;
        left: -22px;
        top: 20px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--primary);
        border: 3px solid white;
        box-shadow: 0 0 0 2px var(--primary);
    }

    .log-table {
        font-size: 0.9rem;
    }

    .log-table th {
        background: var(--light);
        font-weight: 600;
        border: none;
    }

    .log-table td {
        border: none;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }

    .ip-address {
        font-family: 'Courier New', monospace;
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.85rem;
    }

    .risk-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    .risk-low { background: #28a745; }
    .risk-medium { background: #ffc107; }
    .risk-high { background: #fd7e14; }
    .risk-critical { background: #dc3545; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="../admin/dashboard.php">
            <i class="bi bi-shield-check"></i> ISKOLar Security
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> Security Admin
            </span>
            <a class="btn btn-sm btn-outline-light" href="../admin/dashboard.php">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div style="margin-top: 70px;">
    <div class="container-fluid">
        <div class="p-4">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1><i class="bi bi-shield-exclamation"></i> Security Dashboard</h1>
                <p>Monitor system security, track suspicious activities, and manage security events</p>
                <small><i class="bi bi-clock"></i> Last updated: <?= date('F j, Y g:i A'); ?></small>
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

            <!-- Security Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number text-danger"><?= count($securityData['security_events']); ?></div>
                    <div class="stat-label">Unresolved Security Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-warning"><?= count($securityData['failed_logins']); ?></div>
                    <div class="stat-label">Recent Failed Logins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-info"><?= count($securityData['high_risk_activities']); ?></div>
                    <div class="stat-label">High-Risk Activities (24h)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-success">
                        <?php 
                        $criticalCount = count(array_filter($securityData['security_events'], fn($e) => $e['severity'] === 'CRITICAL'));
                        echo $criticalCount === 0 ? '✓' : $criticalCount;
                        ?>
                    </div>
                    <div class="stat-label">Critical Threats</div>
                </div>
            </div>

            <!-- Security Events -->
            <div class="card security-card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Active Security Events</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($securityData['security_events'])): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check" style="font-size: 3rem; color: #28a745;"></i>
                            <h5 class="mt-3 text-success">All Clear!</h5>
                            <p class="text-muted">No unresolved security events at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table log-table">
                                <thead>
                                    <tr>
                                        <th>Severity</th>
                                        <th>Event Type</th>
                                        <th>Description</th>
                                        <th>User</th>
                                        <th>IP Address</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($securityData['security_events'] as $event): ?>
                                        <tr class="<?= strtolower($event['severity']); ?>">
                                            <td>
                                                <span class="severity-badge severity-<?= strtolower($event['severity']); ?>">
                                                    <?= $event['severity']; ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($event['event_type']); ?></td>
                                            <td><?= htmlspecialchars($event['event_description']); ?></td>
                                            <td>
                                                <?php if ($event['user_name']): ?>
                                                    <?= htmlspecialchars($event['user_name']); ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($event['user_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="ip-address"><?= htmlspecialchars($event['ip_address']); ?></span></td>
                                            <td>
                                                <?= date('M j, Y g:i A', strtotime($event['created_at'])); ?><br>
                                                <small class="text-muted"><?= $this->timeAgo($event['created_at']); ?></small>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="resolve_event">
                                                    <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" 
                                                            onclick="return confirm('Mark this security event as resolved?')">
                                                        <i class="bi bi-check"></i> Resolve
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Failed Logins -->
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-person-x"></i> Recent Failed Login Attempts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($securityData['failed_logins'])): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-person-check" style="font-size: 3rem; color: #28a745;"></i>
                            <p class="text-muted mt-2">No recent failed login attempts.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table log-table">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>IP Address</th>
                                        <th>Failure Reason</th>
                                        <th>User Agent</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($securityData['failed_logins'] as $login): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($login['email']); ?></td>
                                            <td><span class="ip-address"><?= htmlspecialchars($login['ip_address']); ?></span></td>
                                            <td><?= htmlspecialchars($login['failure_reason'] ?? 'Invalid credentials'); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($login['user_agent'], 0, 50)); ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <?= date('M j, g:i A', strtotime($login['created_at'])); ?><br>
                                                <small class="text-muted"><?= $login['recency']; ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- High-Risk Admin Activities -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-person-gear"></i> High-Risk Admin Activities (24h)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($securityData['high_risk_activities'])): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check" style="font-size: 3rem; color: #28a745;"></i>
                            <p class="text-muted mt-2">No high-risk admin activities in the last 24 hours.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($securityData['high_risk_activities'] as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="risk-indicator risk-<?= strtolower($activity['risk_level']); ?>"></span>
                                                <?= htmlspecialchars($activity['action_type']); ?>
                                            </h6>
                                            <p class="mb-2"><?= htmlspecialchars($activity['action_description']); ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-person"></i> <?= htmlspecialchars($activity['admin_name']); ?> |
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($activity['ip_address']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="severity-badge severity-<?= strtolower($activity['risk_level']); ?>">
                                                <?= $activity['risk_level']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('g:i A', strtotime($activity['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Security Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-download"></i> Export Security Report
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-warning w-100 mb-2">
                                <i class="bi bi-key"></i> Rotate Encryption Keys
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-graph-up"></i> View Full Audit Trail
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-shield-plus"></i> Security Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh dashboard every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);

// Add timestamp to show last refresh
document.addEventListener('DOMContentLoaded', function() {
    const lastUpdated = document.querySelector('.dashboard-header small');
    if (lastUpdated) {
        setInterval(function() {
            const now = new Date();
            lastUpdated.innerHTML = '<i class="bi bi-clock"></i> Last updated: ' + now.toLocaleString();
        }, 1000);
    }
});
</script>
</body>
</html>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return floor($time/86400) . ' days ago';
}
?>