<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../../index.php");
    exit();
}

// Include session timeout integration (new feature)
require_once __DIR__ . '/../../includes/session_timeout_integration.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Scholarship.php';
require_once __DIR__ . '/../../app/core/ActivityLogger.php';

$database = new Database();
$conn = $database->connect();

$userModel = new User();
$user = $userModel->findById($_SESSION['user_id']);
$logger = new ActivityLogger();

if (!$user) {
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

// Check if profile is completed
if (!$user['profile_completed']) {
    header("Location: student/profile_setup.php");
    exit();
}

// Get student profile
$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scholarships
$scholarshipModel = new Scholarship();
$scholarships = $scholarshipModel->getActiveScholarships(6);
$myApplications = $scholarshipModel->getStudentApplications($_SESSION['user_id']);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ? AND provider_decision = 'Approved'");
$stmt->execute([$_SESSION['user_id']]);
$approvedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(sa.amount_awarded), 0) as total FROM scholarship_awards sa WHERE sa.student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalAwarded = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get available scholarships count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM scholarships 
    WHERE status = 'Active' 
    AND (workflow_status = 'APPROVED_FOR_PUBLICATION' OR workflow_status IS NULL)
    AND application_start <= CURDATE() 
    AND application_end >= CURDATE()
");
$stmt->execute();
$availableCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$message = '';
$error = '';

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_application') {
    $applicationId = intval($_POST['application_id']);
    
    try {
        if ($scholarshipModel->deleteApplication($applicationId, $_SESSION['user_id'])) {
            $message = "Application deleted successfully!";
            
            // Log the deletion activity
            $logger->logSystemActivity(
                $_SESSION['user_id'], 
                'student', 
                'APPLICATION_DELETE', 
                'scholarship_application', 
                $applicationId,
                "Student deleted their scholarship application (ID: $applicationId)"
            );
            
            // Refresh applications data
            $myApplications = $scholarshipModel->getStudentApplications($_SESSION['user_id']);
            
            // Update statistics
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ? AND provider_decision = 'Approved'");
            $stmt->execute([$_SESSION['user_id']]);
            $approvedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } else {
            $error = "Failed to delete application.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        
        // Log the error
        $logger->logSystemActivity(
            $_SESSION['user_id'], 
            'student', 
            'APPLICATION_DELETE', 
            'scholarship_application', 
            $applicationId,
            "Failed to delete application: " . $e->getMessage()
        );
    }
}

// Handle rejected application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_rejected_application') {
    $applicationId = intval($_POST['application_id']);
    
    try {
        // Verify the application is rejected and belongs to the user
        $stmt = $conn->prepare("
            SELECT sa.id, sa.status, s.title 
            FROM scholarship_applications sa
            JOIN scholarships s ON sa.scholarship_id = s.id
            WHERE sa.id = ? AND sa.student_id = ? AND sa.status = 'Rejected'
        ");
        $stmt->execute([$applicationId, $_SESSION['user_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            // Delete the rejected application
            $stmt = $conn->prepare("DELETE FROM scholarship_applications WHERE id = ? AND student_id = ? AND status = 'Rejected'");
            if ($stmt->execute([$applicationId, $_SESSION['user_id']])) {
                $message = "Rejected application removed successfully!";
                
                // Log the removal activity
                $logger->logSystemActivity(
                    $_SESSION['user_id'], 
                    'student', 
                    'APPLICATION_DELETE', 
                    'scholarship_application', 
                    $applicationId,
                    "Student removed rejected application for scholarship: " . $application['title']
                );
                
                // Refresh applications data
                $myApplications = $scholarshipModel->getStudentApplications($_SESSION['user_id']);
                
                // Update statistics
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ? AND provider_decision = 'Approved'");
                $stmt->execute([$_SESSION['user_id']]);
                $approvedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } else {
                $error = "Failed to remove rejected application.";
            }
        } else {
            $error = "Application not found or not rejected.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        
        // Log the error
        $logger->logSystemActivity(
            $_SESSION['user_id'], 
            'student', 
            'APPLICATION_DELETE', 
            'scholarship_application', 
            $applicationId,
            "Failed to remove rejected application: " . $e->getMessage()
        );
    }
}

// Handle scholarship application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    $scholarshipId = intval($_POST['scholarship_id']);
    $personalStatement = trim($_POST['personal_statement']);
    $whyDeserve = trim($_POST['why_deserve_scholarship']);
    
    // Debug: Log the scholarship ID being submitted
    error_log("DEBUG: Scholarship ID submitted: " . $scholarshipId);
    error_log("DEBUG: Available scholarship IDs: " . implode(', ', array_column($scholarships, 'id')));
    
    // Validate scholarship ID exists
    $validScholarshipIds = array_column($scholarships, 'id');
    if (!in_array($scholarshipId, $validScholarshipIds)) {
        $error = "Invalid scholarship selected. Please try again.";
    } elseif (empty($personalStatement) || empty($whyDeserve)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if already applied
        $stmt = $conn->prepare("SELECT id FROM scholarship_applications WHERE scholarship_id = ? AND student_id = ?");
        $stmt->execute([$scholarshipId, $_SESSION['user_id']]);
        $existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingApplication) {
            $error = "You have already applied for this scholarship.";
        } else {
            // Get scholarship requirements to determine which files are required
            $stmt = $conn->prepare("SELECT other_requirements FROM scholarships WHERE id = ?");
            $stmt->execute([$scholarshipId]);
            $scholarshipData = $stmt->fetch(PDO::FETCH_ASSOC);
            $requirements = $scholarshipData ? explode(',', $scholarshipData['other_requirements']) : [];
            
            // Handle file uploads
            $uploadedFiles = [];
            $uploadDir = 'uploads/applications/' . $_SESSION['user_id'] . '/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Always required documents
            $alwaysRequired = ['academic_transcript'];
            
            // Map requirements to file fields
            $requirementFileMapping = [
                'Good Moral Character Certificate' => 'good_moral_certificate',
                'Certificate of Indigency' => 'certificate_of_indigency',
                'Academic Transcript' => 'academic_transcript_additional',
                'Letter of Recommendation' => 'recommendation_letter',
                'Essay/Personal Statement' => 'essay_document',
                'Community Service Record' => 'community_service_record',
                'Medical Certificate' => 'medical_certificate',
                'Parent/Guardian Income Certificate' => 'parent_income_certificate',
                'Birth Certificate' => 'birth_certificate',
                'Valid ID Copy' => 'valid_id_copy',
                'Other' => 'other_requirement_document'
            ];
            
            // Determine required files based on scholarship requirements
            $requiredFiles = $alwaysRequired;
            foreach ($requirements as $req) {
                $trimmedReq = trim($req);
                if (isset($requirementFileMapping[$trimmedReq])) {
                    $requiredFiles[] = $requirementFileMapping[$trimmedReq];
                }
            }
            
            // Optional files
            $optionalFiles = ['additional_documents'];
            
            $uploadSuccess = true;
            $uploadErrors = [];
            
            // Process required files
            foreach ($requiredFiles as $fileField) {
                if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                    $fileName = time() . '_' . $fileField . '_' . basename($_FILES[$fileField]['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    // Validate file type and size
                    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES[$fileField]['type'], $allowedTypes)) {
                        $uploadErrors[] = "Invalid file type for " . str_replace('_', ' ', $fileField);
                        $uploadSuccess = false;
                    } elseif ($_FILES[$fileField]['size'] > $maxSize) {
                        $uploadErrors[] = "File too large for " . str_replace('_', ' ', $fileField) . " (max 5MB)";
                        $uploadSuccess = false;
                    } elseif (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetPath)) {
                        $uploadedFiles[$fileField] = $fileName;
                    } else {
                        $uploadErrors[] = "Failed to upload " . str_replace('_', ' ', $fileField);
                        $uploadSuccess = false;
                    }
                } else {
                    $uploadErrors[] = ucfirst(str_replace('_', ' ', $fileField)) . " is required";
                    $uploadSuccess = false;
                }
            }
            
            // Process optional files
            foreach ($optionalFiles as $fileField) {
                if ($fileField === 'additional_documents' && isset($_FILES[$fileField])) {
                    // Handle multiple files
                    $additionalDocs = [];
                    $fileCount = count($_FILES[$fileField]['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES[$fileField]['error'][$i] === UPLOAD_ERR_OK) {
                            $fileName = time() . '_additional_' . $i . '_' . basename($_FILES[$fileField]['name'][$i]);
                            $targetPath = $uploadDir . $fileName;
                            
                            // Validate file
                            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                            $maxSize = 5 * 1024 * 1024; // 5MB
                            
                            if (in_array($_FILES[$fileField]['type'][$i], $allowedTypes) && 
                                $_FILES[$fileField]['size'][$i] <= $maxSize) {
                                if (move_uploaded_file($_FILES[$fileField]['tmp_name'][$i], $targetPath)) {
                                    $additionalDocs[] = $fileName;
                                }
                            }
                        }
                    }
                    
                    if (!empty($additionalDocs)) {
                        $uploadedFiles[$fileField] = $additionalDocs;
                    }
                } elseif (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                    // Handle single optional file
                    $fileName = time() . '_' . $fileField . '_' . basename($_FILES[$fileField]['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetPath)) {
                        $uploadedFiles[$fileField] = $fileName;
                    }
                }
            }
            
            if ($uploadSuccess) {
                // Insert application
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO scholarship_applications (
                            scholarship_id, student_id, personal_statement, 
                            why_deserve_scholarship, documents, status
                        ) VALUES (?, ?, ?, ?, ?, 'Submitted')
                    ");
                    
                    if ($stmt->execute([
                        $scholarshipId, 
                        $_SESSION['user_id'], 
                        $personalStatement, 
                        $whyDeserve,
                        json_encode($uploadedFiles)
                    ])) {
                    $message = "Application submitted successfully with all required documents!";
                    // Refresh applications data
                    $myApplications = $scholarshipModel->getStudentApplications($_SESSION['user_id']);
                    
                    // Update statistics
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_applications WHERE student_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $totalApplications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    } else {
                        $error = "Failed to submit application. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage() . " (Scholarship ID: {$scholarshipId}, Student ID: {$_SESSION['user_id']})";
                    error_log("Application submission error: " . $e->getMessage());
                }
            } else {
                $error = "Upload failed: " . implode(', ', $uploadErrors);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ISKOLar</title>
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

    .scholarship-card {
        border-left: 4px solid var(--secondary);
    }

    .scholarship-card .card-header {
        background: var(--secondary);
        color: var(--dark);
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
                        <a class="nav-link active" href="#dashboard-section"><i class="bi bi-house-fill"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#scholarships-section"><i class="bi bi-award"></i> Scholarships</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#applications-section"><i class="bi bi-file-earmark-text"></i> My Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student/profile_setup.php"><i class="bi bi-person"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#help-section"><i class="bi bi-question-circle"></i> Help & Support</a>
                    </li>
                </ul>
            </div>

            <!-- CONTENT -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Dashboard Header -->
                    <div id="dashboard-section" class="dashboard-header">
                        <h1><i class="bi bi-hand-thumbs-up"></i> Welcome Back, <?= htmlspecialchars($user['fullname']); ?>!</h1>
                        <p>Your email (<?= htmlspecialchars($user['email']); ?>) has been verified. Explore scholarships and opportunities.</p>
                        <?php if ($profile): ?>
                            <small><i class="bi bi-mortarboard"></i> <?= htmlspecialchars($profile['course'] ?? 'Course not set'); ?> - <?= htmlspecialchars($profile['year_level'] ?? 'Year not set'); ?></small>
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
                                <h3><?= $availableCount; ?></h3>
                                <p>Scholarships Available</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3><?= $totalApplications; ?></h3>
                                <p>Applications Submitted</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3><?= $approvedCount; ?></h3>
                                <p>Approved</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>₱<?= number_format($totalAwarded, 2); ?></h3>
                                <p>Total Award Amount</p>
                            </div>
                        </div>
                    </div>

                    <!-- Available Scholarships Section -->
                    <div id="scholarships-section" class="card scholarship-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-award"></i> Available Scholarships</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($scholarships)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted mt-2">No scholarships available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($scholarships as $scholarship): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?= htmlspecialchars($scholarship['title']); ?></h6>
                                                    <p class="card-text small text-muted"><?= htmlspecialchars(substr($scholarship['description'], 0, 100)) . '...'; ?></p>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="badge bg-primary">₱<?= number_format($scholarship['amount'], 0); ?></span>
                                                        <small class="text-muted"><?= $scholarship['scholarship_type']; ?></small>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar"></i> 
                                                            Until <?= date('M j, Y', strtotime($scholarship['application_end'])); ?>
                                                        </small>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewScholarship(<?= $scholarship['id']; ?>)">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <button class="btn btn-primary" onclick="showAllScholarships()">
                                        <i class="bi bi-search"></i> Browse All Scholarships
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Applications Section -->
                    <div id="applications-section" class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> My Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($myApplications)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-file-earmark-plus" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted mt-2">You haven't applied for any scholarships yet.</p>
                                    <button class="btn btn-primary" onclick="document.getElementById('scholarships-section').scrollIntoView()">
                                        <i class="bi bi-plus-circle"></i> Apply for Scholarships
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Scholarship</th>
                                                <th>Amount</th>
                                                <th>Applied Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($myApplications as $application): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($application['title']); ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($application['provider_name']); ?></small>
                                                    </td>
                                                    <td>₱<?= number_format($application['amount'], 0); ?></td>
                                                    <td><?= date('M j, Y', strtotime($application['created_at'])); ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = 'bg-secondary';
                                                        if ($application['status'] == 'Under Review') $statusClass = 'bg-warning';
                                                        if ($application['status'] == 'Approved') $statusClass = 'bg-success';
                                                        if ($application['status'] == 'Rejected') $statusClass = 'bg-danger';
                                                        ?>
                                                        <span class="badge <?= $statusClass; ?>"><?= $application['status']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewApplication(<?= $application['id']; ?>)">
                                                                <i class="bi bi-eye"></i> View
                                                            </button>
                                                            <?php if (($application['status'] == 'Submitted' || $application['status'] == 'Under Review') && isset($application['can_edit_documents']) && $application['can_edit_documents']): ?>
                                                                <a href="student/edit_application.php?id=<?= $application['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                                    <i class="bi bi-pencil-square"></i> Edit Documents
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($application['status'] == 'Submitted' || $application['status'] == 'Under Review'): ?>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteApplication(<?= $application['id']; ?>, '<?= htmlspecialchars($application['title']); ?>')">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </button>
                                                            <?php elseif ($application['status'] == 'Rejected'): ?>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRejectedApplication(<?= $application['id']; ?>, '<?= htmlspecialchars($application['title']); ?>')">
                                                                    <i class="bi bi-trash"></i> Remove
                                                                </button>
                                                            <?php endif; ?>
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

                    <!-- Help & Support Section -->
                    <div id="help-section" class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-question-circle"></i> Help & Support</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-book"></i> Getting Started</h6>
                                    <ul class="list-unstyled">
                                        <li><a href="#" class="text-decoration-none">How to apply for scholarships</a></li>
                                        <li><a href="#" class="text-decoration-none">Completing your profile</a></li>
                                        <li><a href="#" class="text-decoration-none">Understanding eligibility criteria</a></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-headset"></i> Contact Support</h6>
                                    <p class="text-muted">Need help? Contact our support team:</p>
                                    <p>
                                        <i class="bi bi-envelope"></i> support@iskolar.edu.ph<br>
                                        <i class="bi bi-telephone"></i> (02) 8123-4567
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($myApplications)): ?>
                                    <?php foreach (array_slice($myApplications, 0, 3) as $application): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">Applied for <?= htmlspecialchars($application['title']); ?></h6>
                                                <small class="text-muted"><?= date('M j, Y', strtotime($application['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted">Status: <?= $application['status']; ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Account Created</h6>
                                            <small class="text-muted">Recently</small>
                                        </div>
                                        <p class="mb-1 text-muted">Your account has been successfully created and verified.</p>
                                    </div>
                                <?php endif; ?>
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
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your dashboard.</p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeLogoutModal()" style="padding:10px 20px; background-color:#e9ecef; color:#333; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Cancel</button>
            <button onclick="confirmLogout()" style="padding:10px 20px; background-color:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:'Poppins', sans-serif;">Logout</button>
        </div>
    </div>
</div>

<!-- SCHOLARSHIP DETAILS MODAL -->
<div class="modal fade" id="scholarshipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scholarship Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="scholarshipModalBody">
                <!-- Scholarship details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyButton" onclick="showApplicationForm()">Apply Now</button>
            </div>
        </div>
    </div>
</div>

<!-- APPLICATION FORM MODAL -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for Scholarship</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="scholarship_id" id="applicationScholarshipId">
                    
                    <!-- Scholarship Requirements Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Scholarship Requirements</h6>
                        </div>
                        <div class="card-body" id="requirementsSection">
                            <!-- Requirements will be populated dynamically -->
                        </div>
                    </div>
                    
                    <!-- Application Form -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="personal_statement" class="form-label">Tell me about yourself <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="personal_statement" name="personal_statement" rows="4" required 
                                          placeholder="Tell us about yourself, your background, and your academic goals..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="why_deserve_scholarship" class="form-label">Why do you deserve this scholarship? <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="why_deserve_scholarship" name="why_deserve_scholarship" rows="4" required 
                                          placeholder="Explain why you should be selected for this scholarship..."></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Document Upload Section -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-cloud-upload"></i> Required Documents</h6>
                                </div>
                                <div class="card-body" id="documentsSection">
                                    <!-- Document upload fields will be populated dynamically based on requirements -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Important Notes:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Make sure your profile is complete before applying</li>
                            <li>All uploaded files should be clear and readable</li>
                            <li>Maximum file size: 5MB per file</li>
                            <li>Supported formats: PDF, JPG, JPEG, PNG</li>
                            <li>Incomplete applications may be rejected</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Submit Application
                    </button>
                </div>
            </form>
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
    window.location.href = '../../logout.php';
}

// Sidebar navigation
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();
                
                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Scroll to section
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
});

// Scholarship functions
function viewScholarship(scholarshipId) {
    // Get scholarship details via AJAX or use existing data
    const scholarships = <?= json_encode($scholarships); ?>;
    const scholarship = scholarships.find(s => s.id == scholarshipId);
    
    if (scholarship) {
        const modalBody = document.getElementById('scholarshipModalBody');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-8">
                    <h4>${scholarship.title}</h4>
                    <p class="text-muted">by ${scholarship.organization_name || scholarship.provider_name}</p>
                    <p>${scholarship.description}</p>
                    
                    <h6>Eligibility Criteria:</h6>
                    <ul>
                        ${scholarship.eligible_courses ? `<li><strong>Courses:</strong> ${scholarship.eligible_courses}</li>` : ''}
                        ${scholarship.year_levels ? `<li><strong>Year Levels:</strong> ${scholarship.year_levels}</li>` : ''}
                        ${scholarship.min_gwa ? `<li><strong>Minimum Latest Weighted Average:</strong> ${scholarship.min_gwa}</li>` : ''}
                        ${scholarship.max_family_income ? `<li><strong>Max Family Income:</strong> ₱${parseFloat(scholarship.max_family_income).toLocaleString()}</li>` : ''}
                    </ul>
                    
                    ${scholarship.other_requirements ? `
                        <h6>Other Requirements:</h6>
                        <p>${scholarship.other_requirements}</p>
                    ` : ''}
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="text-primary">₱${parseFloat(scholarship.amount).toLocaleString()}</h5>
                            <p class="mb-1">${scholarship.scholarship_type}</p>
                            <small class="text-muted">${scholarship.slots} slots available</small>
                            
                            <hr>
                            
                            <p class="mb-1"><strong>Application Period:</strong></p>
                            <small class="text-muted">
                                ${new Date(scholarship.application_start).toLocaleDateString()} - 
                                ${new Date(scholarship.application_end).toLocaleDateString()}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Set scholarship ID for application
        document.getElementById('applicationScholarshipId').value = scholarshipId;
        
        // Show modal
        new bootstrap.Modal(document.getElementById('scholarshipModal')).show();
    }
}

function showApplicationForm() {
    // Get the current scholarship data
    const scholarshipId = document.getElementById('applicationScholarshipId').value;
    const scholarships = <?= json_encode($scholarships); ?>;
    const scholarship = scholarships.find(s => s.id == scholarshipId);
    
    if (scholarship) {
        // Populate requirements section
        const requirementsSection = document.getElementById('requirementsSection');
        let requirementsHtml = '<div class="row">';
        
        // Eligibility Requirements
        requirementsHtml += '<div class="col-md-6">';
        requirementsHtml += '<h6 class="text-primary"><i class="bi bi-check-circle"></i> Eligibility Requirements</h6>';
        requirementsHtml += '<ul class="list-unstyled">';
        
        if (scholarship.eligible_courses) {
            requirementsHtml += `<li><i class="bi bi-mortarboard text-success"></i> <strong>Eligible Courses:</strong> ${scholarship.eligible_courses}</li>`;
        }
        
        if (scholarship.year_levels) {
            requirementsHtml += `<li><i class="bi bi-calendar text-success"></i> <strong>Year Levels:</strong> ${scholarship.year_levels}</li>`;
        }
        
        if (scholarship.min_gwa) {
            requirementsHtml += `<li><i class="bi bi-graph-up text-success"></i> <strong>Minimum Latest Weighted Average:</strong> ${scholarship.min_gwa}</li>`;
        }
        
        if (scholarship.max_family_income) {
            requirementsHtml += `<li><i class="bi bi-currency-peso text-success"></i> <strong>Maximum Family Income:</strong> ₱${parseFloat(scholarship.max_family_income).toLocaleString()}</li>`;
        }
        
        requirementsHtml += '</ul>';
        requirementsHtml += '</div>';
        
        // Additional Requirements
        requirementsHtml += '<div class="col-md-6">';
        requirementsHtml += '<h6 class="text-primary"><i class="bi bi-list-check"></i> Additional Requirements</h6>';
        
        if (scholarship.other_requirements) {
            const requirements = scholarship.other_requirements.split(',');
            requirementsHtml += '<ul class="list-unstyled">';
            requirements.forEach(req => {
                requirementsHtml += `<li><i class="bi bi-arrow-right text-warning"></i> ${req.trim()}</li>`;
            });
            requirementsHtml += '</ul>';
        } else {
            requirementsHtml += '<p class="text-muted">No additional requirements specified.</p>';
        }
        
        requirementsHtml += '</div>';
        requirementsHtml += '</div>';
        
        // Application Period
        requirementsHtml += '<div class="alert alert-warning mt-3">';
        requirementsHtml += '<i class="bi bi-calendar-event"></i> ';
        requirementsHtml += `<strong>Application Period:</strong> ${new Date(scholarship.application_start).toLocaleDateString()} - ${new Date(scholarship.application_end).toLocaleDateString()}`;
        requirementsHtml += '</div>';
        
        requirementsSection.innerHTML = requirementsHtml;
        
        // Populate documents section based on requirements
        const documentsSection = document.getElementById('documentsSection');
        let documentsHtml = '';
        
        // Always required documents
        documentsHtml += `
            <div class="mb-3">
                <label for="academic_transcript" class="form-label">Academic Transcript <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="academic_transcript" name="academic_transcript" 
                       accept=".pdf,.jpg,.jpeg,.png" required>
                <small class="text-muted">Upload your latest academic transcript (PDF, JPG, PNG)</small>
            </div>
        `;
        
        // Generate upload fields based on scholarship requirements
        if (scholarship.other_requirements) {
            const requirements = scholarship.other_requirements.split(',');
            
            // Map requirements to file upload fields
            const requirementMapping = {
                'Good Moral Character Certificate': {
                    field: 'good_moral_certificate',
                    label: 'Good Moral Character Certificate',
                    description: 'Certificate from your school or barangay'
                },
                'Certificate of Indigency': {
                    field: 'certificate_of_indigency',
                    label: 'Certificate of Indigency',
                    description: 'Barangay certificate of indigency'
                },
                'Academic Transcript': {
                    field: 'academic_transcript_additional',
                    label: 'Additional Academic Records',
                    description: 'Previous semester transcripts or grade reports'
                },
                'Letter of Recommendation': {
                    field: 'recommendation_letter',
                    label: 'Letter of Recommendation',
                    description: 'From teacher, employer, or community leader'
                },
                'Essay/Personal Statement': {
                    field: 'essay_document',
                    label: 'Essay Document',
                    description: 'If you have a separate essay document'
                },
                'Community Service Record': {
                    field: 'community_service_record',
                    label: 'Community Service Record',
                    description: 'Certificate or documentation of community service'
                },
                'Medical Certificate': {
                    field: 'medical_certificate',
                    label: 'Medical Certificate',
                    description: 'Recent medical certificate from licensed physician'
                },
                'Parent/Guardian Income Certificate': {
                    field: 'parent_income_certificate',
                    label: 'Parent/Guardian Income Certificate',
                    description: 'ITR, certificate of employment, or barangay income certificate'
                },
                'Birth Certificate': {
                    field: 'birth_certificate',
                    label: 'Birth Certificate',
                    description: 'PSA birth certificate (original or certified copy)'
                },
                'Valid ID Copy': {
                    field: 'valid_id_copy',
                    label: 'Valid ID Copy',
                    description: 'Government-issued ID (front and back)'
                }
            };
            
            requirements.forEach(req => {
                const trimmedReq = req.trim();
                if (requirementMapping[trimmedReq]) {
                    const mapping = requirementMapping[trimmedReq];
                    const isRequired = true; // All specified requirements are required
                    
                    documentsHtml += `
                        <div class="mb-3">
                            <label for="${mapping.field}" class="form-label">${mapping.label} ${isRequired ? '<span class="text-danger">*</span>' : ''}</label>
                            <input type="file" class="form-control" id="${mapping.field}" name="${mapping.field}" 
                                   accept=".pdf,.jpg,.jpeg,.png" ${isRequired ? 'required' : ''}>
                            <small class="text-muted">${mapping.description} (PDF, JPG, PNG)</small>
                        </div>
                    `;
                } else if (trimmedReq === 'Other') {
                    // Handle custom requirements
                    documentsHtml += `
                        <div class="mb-3">
                            <label for="other_requirement_document" class="form-label">Other Required Document <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="other_requirement_document" name="other_requirement_document" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="text-muted">Upload the additional document specified in requirements (PDF, JPG, PNG)</small>
                        </div>
                    `;
                }
            });
        }
        
        // Optional additional documents
        documentsHtml += `
            <div class="mb-3">
                <label for="additional_documents" class="form-label">Additional Supporting Documents</label>
                <input type="file" class="form-control" id="additional_documents" name="additional_documents[]" 
                       accept=".pdf,.jpg,.jpeg,.png" multiple>
                <small class="text-muted">Awards, certificates, or other supporting documents (Multiple files allowed)</small>
            </div>
        `;
        
        documentsSection.innerHTML = documentsHtml;
    }
    
    // Hide scholarship modal and show application modal
    bootstrap.Modal.getInstance(document.getElementById('scholarshipModal')).hide();
    new bootstrap.Modal(document.getElementById('applicationModal')).show();
}

function showAllScholarships() {
    // Scroll to scholarships section
    document.getElementById('scholarships-section').scrollIntoView({ behavior: 'smooth' });
}

function viewApplication(applicationId) {
    const applications = <?= json_encode($myApplications); ?>;
    const application = applications.find(a => a.id == applicationId);
    
    if (application) {
        // Create a detailed modal for application view using vanilla JavaScript
        const modalHtml = `
            <div class="modal fade show" id="viewApplicationModal" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Application Details</h5>
                            <button type="button" class="btn-close" onclick="closeApplicationModal()"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3"><i class="bi bi-award"></i> Scholarship Information</h6>
                                    <div class="mb-3">
                                        <strong>Title:</strong><br>
                                        ${application.title}
                                    </div>
                                    <div class="mb-3">
                                        <strong>Provider:</strong><br>
                                        ${application.provider_name}
                                    </div>
                                    <div class="mb-3">
                                        <strong>Amount:</strong><br>
                                        <span class="badge bg-success fs-6">₱${parseFloat(application.amount).toLocaleString()}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Application Date:</strong><br>
                                        ${new Date(application.created_at).toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </div>
                                    <div class="mb-3">
                                        <strong>Status:</strong><br>
                                        <span class="badge ${getStatusBadgeClass(application.status)} fs-6">${application.status}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3"><i class="bi bi-file-text"></i> Your Application</h6>
                                    ${application.personal_statement ? `
                                        <div class="mb-3">
                                            <strong>About Yourself:</strong><br>
                                            <div class="bg-light p-3 rounded small">
                                                ${application.personal_statement}
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${application.why_deserve_scholarship ? `
                                        <div class="mb-3">
                                            <strong>Why You Deserve This Scholarship:</strong><br>
                                            <div class="bg-light p-3 rounded small">
                                                ${application.why_deserve_scholarship}
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${application.provider_notes ? `
                                        <div class="mb-3">
                                            <strong>Provider Notes:</strong><br>
                                            <div class="bg-warning bg-opacity-10 p-3 rounded border border-warning small">
                                                ${application.provider_notes}
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeApplicationModal()">Close</button>
                            ${(application.status === 'Submitted' || application.status === 'Under Review') ? `
                                <button type="button" class="btn btn-danger" onclick="deleteApplicationFromModal(${application.id}, '${application.title}')">
                                    <i class="bi bi-trash"></i> Delete Application
                                </button>
                            ` : ''}
                            ${(application.status === 'Rejected') ? `
                                <button type="button" class="btn btn-danger" onclick="deleteRejectedApplicationFromModal(${application.id}, '${application.title}')">
                                    <i class="bi bi-trash"></i> Remove Application
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('viewApplicationModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Add click outside to close functionality
        document.getElementById('viewApplicationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApplicationModal();
            }
        });
    }
}

function closeApplicationModal() {
    const modal = document.getElementById('viewApplicationModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'Submitted': return 'bg-info';
        case 'Under Review': return 'bg-warning';
        case 'Approved': return 'bg-success';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function deleteApplication(applicationId, scholarshipTitle) {
    if (confirm(`Are you sure you want to DELETE your application for "${scholarshipTitle}"?\n\nThis action cannot be undone. You will need to reapply if you change your mind.\n\nNote: You can only delete applications that are not yet approved.`)) {
        // Create and submit delete form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_application';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'application_id';
        idInput.value = applicationId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteApplicationFromModal(applicationId, scholarshipTitle) {
    // Close the modal first
    closeApplicationModal();
    
    // Call the delete function
    deleteApplication(applicationId, scholarshipTitle);
}

function deleteRejectedApplication(applicationId, scholarshipTitle) {
    if (confirm(`Are you sure you want to REMOVE your rejected application for "${scholarshipTitle}"?\n\nThis will permanently delete the application record from your account.\n\nNote: This action cannot be undone.`)) {
        // Create and submit delete form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_rejected_application';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'application_id';
        idInput.value = applicationId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteRejectedApplicationFromModal(applicationId, scholarshipTitle) {
    // Close the modal first
    closeApplicationModal();
    
    // Call the delete function
    deleteRejectedApplication(applicationId, scholarshipTitle);
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});
</script>
</body>
</html>
