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
if (!$profile) {
    header("Location: profile_setup.php");
    exit();
}

$scholarshipId = intval($_GET['id'] ?? 0);
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

$message = '';
$error = '';

// Get schools for dropdown
$database = new Database();
$conn = $database->connect();
$stmt = $conn->query("SELECT * FROM schools WHERE is_active = 1 ORDER BY school_name");
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'scholarship_type' => $_POST['scholarship_type'],
        'amount' => floatval($_POST['amount']),
        'slots' => intval($_POST['slots']),
        'eligible_courses' => trim($_POST['eligible_courses']),
        'min_gwa' => !empty($_POST['min_gwa']) ? floatval($_POST['min_gwa']) : null,
        'max_family_income' => !empty($_POST['max_family_income']) ? floatval($_POST['max_family_income']) : null,
        'year_levels' => implode(',', $_POST['year_levels'] ?? []),
        'other_requirements' => trim($_POST['other_requirements']),
        'application_start' => $_POST['application_start'],
        'application_end' => $_POST['application_end'],
        'status' => $_POST['status']
    ];

    // Validation
    if (empty($data['title']) || empty($data['description']) || empty($data['scholarship_type']) || 
        $data['amount'] <= 0 || $data['slots'] <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } elseif (strtotime($data['application_start']) >= strtotime($data['application_end'])) {
        $error = "Application end date must be after start date.";
    } else {
        try {
            if ($scholarshipModel->updateScholarship($scholarshipId, $data)) {
                $message = "Scholarship updated successfully!";
                // Refresh scholarship data
                $scholarship = $scholarshipModel->getScholarshipById($scholarshipId);
            } else {
                $error = "Failed to update scholarship.";
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Convert year_levels string back to array for form
$selectedYearLevels = !empty($scholarship['year_levels']) ? explode(',', $scholarship['year_levels']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Scholarship - ISKOLar</title>
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
        background: linear-gradient(135deg, var(--light), #e3f2fd);
        min-height: 100vh;
    }

    .navbar {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .scholarship-container {
        max-width: 900px;
        margin: 100px auto 50px;
        padding: 20px;
    }

    .scholarship-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .scholarship-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        text-align: center;
    }

    .scholarship-body {
        padding: 40px;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), #0066ff);
        border: none;
        border-radius: 10px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.3);
    }

    .btn-secondary {
        background: #6c757d;
        border: none;
        border-radius: 10px;
        padding: 12px 30px;
        font-weight: 600;
    }

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
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

<div class="scholarship-container">
    <div class="scholarship-card">
        <div class="scholarship-header">
            <h1><i class="bi bi-pencil-square"></i> Edit Scholarship</h1>
            <p>Update your scholarship program details</p>
        </div>

        <div class="scholarship-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
                    <div class="mt-2">
                        <a href="scholarships.php" class="btn btn-sm btn-success">Back to My Scholarships</a>
                        <a href="view_scholarship.php?id=<?= $scholarshipId; ?>" class="btn btn-sm btn-outline-success">View Scholarship</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Basic Information -->
                <div class="mb-4">
                    <h4 class="section-title">Basic Information</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Scholarship Title *</label>
                        <input type="text" name="title" class="form-control" 
                               value="<?= htmlspecialchars($scholarship['title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($scholarship['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Scholarship Type *</label>
                            <select name="scholarship_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Full" <?= $scholarship['scholarship_type'] == 'Full' ? 'selected' : ''; ?>>Full Scholarship</option>
                                <option value="Partial" <?= $scholarship['scholarship_type'] == 'Partial' ? 'selected' : ''; ?>>Partial Scholarship</option>
                                <option value="Book Allowance" <?= $scholarship['scholarship_type'] == 'Book Allowance' ? 'selected' : ''; ?>>Book Allowance</option>
                                <option value="Tuition Only" <?= $scholarship['scholarship_type'] == 'Tuition Only' ? 'selected' : ''; ?>>Tuition Only</option>
                                <option value="Living Allowance" <?= $scholarship['scholarship_type'] == 'Living Allowance' ? 'selected' : ''; ?>>Living Allowance</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount (₱) *</label>
                            <input type="number" name="amount" class="form-control" 
                                   value="<?= $scholarship['amount']; ?>" 
                                   min="1" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Number of Slots *</label>
                            <input type="number" name="slots" class="form-control" 
                                   value="<?= $scholarship['slots']; ?>" 
                                   min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partner School (Optional)</label>
                        <select name="school_id" class="form-select">
                            <option value="">Select School (Optional)</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id']; ?>" 
                                        <?= $scholarship['school_id'] == $school['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($school['school_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Eligibility Criteria -->
                <div class="mb-4">
                    <h4 class="section-title">Eligibility Criteria</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Eligible Courses</label>
                        <textarea name="eligible_courses" class="form-control" rows="2"><?= htmlspecialchars($scholarship['eligible_courses']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum GWA</label>
                            <input type="number" name="min_gwa" class="form-control" 
                                   value="<?= $scholarship['min_gwa']; ?>" 
                                   min="1.0" max="5.0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Family Income (₱)</label>
                            <input type="number" name="max_family_income" class="form-control" 
                                   value="<?= $scholarship['max_family_income']; ?>" 
                                   min="0" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Eligible Year Levels</label>
                        <div class="row">
                            <?php 
                            $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
                            ?>
                            <?php foreach ($yearLevels as $year): ?>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_levels[]" 
                                               value="<?= $year; ?>" id="year_<?= str_replace(' ', '_', $year); ?>"
                                               <?= in_array($year, $selectedYearLevels) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="year_<?= str_replace(' ', '_', $year); ?>">
                                            <?= $year; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Other Requirements</label>
                        <textarea name="other_requirements" class="form-control" rows="3"><?= htmlspecialchars($scholarship['other_requirements']); ?></textarea>
                    </div>
                </div>

                <!-- Application Period -->
                <div class="mb-4">
                    <h4 class="section-title">Application Period</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Application Start Date *</label>
                            <input type="date" name="application_start" class="form-control" 
                                   value="<?= $scholarship['application_start']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Application End Date *</label>
                            <input type="date" name="application_end" class="form-control" 
                                   value="<?= $scholarship['application_end']; ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <h4 class="section-title">Publication Status</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="Draft" <?= $scholarship['status'] == 'Draft' ? 'selected' : ''; ?>>Draft (Not visible to students)</option>
                            <option value="Active" <?= $scholarship['status'] == 'Active' ? 'selected' : ''; ?>>Active (Visible to students)</option>
                            <option value="Closed" <?= $scholarship['status'] == 'Closed' ? 'selected' : ''; ?>>Closed (No new applications)</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="scholarships.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Scholarships
                    </a>
                    <div class="d-flex gap-2">
                        <a href="view_scholarship.php?id=<?= $scholarshipId; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-eye"></i> Preview
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Scholarship
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Set minimum date to today for new dates
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const startDateInput = document.querySelector('input[name="application_start"]');
    const endDateInput = document.querySelector('input[name="application_end"]');
    
    // Update end date minimum when start date changes
    startDateInput.addEventListener('change', function() {
        endDateInput.setAttribute('min', this.value);
    });
    
    // Set initial minimum for end date
    if (startDateInput.value) {
        endDateInput.setAttribute('min', startDateInput.value);
    }
});
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