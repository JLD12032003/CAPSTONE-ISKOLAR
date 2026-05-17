<?php
session_start();

// Check if user is logged in and is a donor or foundation
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['donor', 'foundation'])) {
    header("Location: ../../index.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->connect();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - ISKOLar</title>
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

    .navbar-brand {
        font-weight: 700;
        font-size: 1.3rem;
    }

    .sidebar {
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        height: 100vh;
        position: sticky;
        top: 0;
    }

    .sidebar .nav-link {
        color: #555;
        padding: 15px 20px;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .sidebar .nav-link:hover {
        color: var(--primary);
        background: #f0f5ff;
        border-left-color: var(--primary);
    }

    .sidebar .nav-link.active {
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

    .dashboard-header h1 {
        font-weight: 700;
        margin-bottom: 10px;
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

    .card-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        border: none;
        border-radius: 12px 12px 0 0;
    }

    .btn-logout {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white !important;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.3);
    }

    .stats-box {
        text-align: center;
        padding: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stats-box h3 {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 10px;
    }

    .stats-box p {
        color: #666;
        margin: 0;
    }

    .badge-donor {
        background: var(--secondary);
        color: var(--dark);
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="#">
            <i class="bi bi-mortarboard-fill"></i> ISKOLar
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['fullname']); ?> 
                        <span class="badge badge-donor ms-2"><?= ucfirst(htmlspecialchars($user['user_type'])); ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="#" onclick="openLogoutModal(); return false;">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
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
                        <a class="nav-link" href="#"><i class="bi bi-plus-circle"></i> Create Scholarship</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-list-check"></i> My Scholarships</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-people"></i> Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-bar-chart"></i> Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-gear"></i> Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-question-circle"></i> Help & Support</a>
                    </li>
                </ul>
            </div>

            <!-- CONTENT -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1><i class="bi bi-hand-thumbs-up"></i> Welcome, <?= htmlspecialchars($user['fullname']); ?>!</h1>
                        <p>Manage your scholarship programs and reach deserving students.</p>
                    </div>

                    <!-- Stats Row -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>3</h3>
                                <p>Active Scholarships</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>12</h3>
                                <p>Applications Received</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>5</h3>
                                <p>Awardees</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>₱500,000</h3>
                                <p>Total Awarded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Create Scholarship Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Scholarship</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Start a new scholarship program and help deserving students achieve their dreams.</p>
                            <button class="btn btn-primary"><i class="bi bi-plus"></i> Create Scholarship</button>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Account Created</h6>
                                        <small class="text-muted">Today</small>
                                    </div>
                                    <p class="mb-1 text-muted">Your account has been successfully created and verified.</p>
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
<div id="logoutModal" style="display:none; position:fixed; z-index:1; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h2 style="color:#012A4A; margin-bottom:20px; font-weight:700;">Confirm Logout</h2>
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your dashboard.</p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeLogoutModal()" style="padding:10px 20px; background-color:#e9ecef; color:#333; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Cancel</button>
            <button onclick="confirmLogout()" style="padding:10px 20px; background-color:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Logout</button>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'block';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    window.location.href = '../logout.php';
}
</script>

</body>
</html>
