<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/Scholarship.php';
require_once __DIR__ . '/../../core/ActivityLogger.php';

$database = new Database();
$conn = $database->connect();
$logger = new ActivityLogger();

$applicationId = intval($_GET['id'] ?? 0);

if (!$applicationId) {
    header("Location: ../student_home.php?error=" . urlencode("Invalid application ID"));
    exit();
}

// Get application details
$stmt = $conn->prepare("
    SELECT sa.*, s.title as scholarship_title, s.other_requirements, s.provider_id,
           u.fullname as provider_name
    FROM scholarship_applications sa
    JOIN scholarships s ON sa.scholarship_id = s.id
    JOIN users u ON s.provider_id = u.id
    WHERE sa.id = ? AND sa.student_id = ?
");
$stmt->execute([$applicationId, $_SESSION['user_id']]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    header("Location: ../student_home.php?error=" . urlencode("Application not found"));
    exit();
}

// Check if editing is allowed
if (!$application['can_edit_documents'] || !in_array($application['status'], ['Submitted', 'Under Review'])) {
    header("Location: ../student_home.php?error=" . urlencode("This application cannot be edited"));
    exit();
}

$message = '';
$error = '';

// Handle document updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_documents') {
    try {
        $uploadDir = '../uploads/applications/' . $_SESSION['user_id'] . '/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get current documents
        $currentDocuments = json_decode($application['documents'], true) ?? [];
        $updatedDocuments = $currentDocuments;
        
        // Get scholarship requirements
        $requirements = $application['other_requirements'] ? explode(',', $application['other_requirements']) : [];
        
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
        
        // Always required documents
        $alwaysRequired = ['academic_transcript'];
        
        // Determine required files based on scholarship requirements
        $requiredFiles = $alwaysRequired;
        foreach ($requirements as $req) {
            $trimmedReq = trim($req);
            if (isset($requirementFileMapping[$trimmedReq])) {
                $requiredFiles[] = $requirementFileMapping[$trimmedReq];
            }
        }
        
        $uploadSuccess = true;
        $uploadErrors = [];
        
        // Process file uploads
        foreach ($requiredFiles as $fileField) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                // Delete old file if exists
                if (isset($currentDocuments[$fileField])) {
                    $oldFilePath = $uploadDir . $currentDocuments[$fileField];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                
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
                    $updatedDocuments[$fileField] = $fileName;
                } else {
                    $uploadErrors[] = "Failed to upload " . str_replace('_', ' ', $fileField);
                    $uploadSuccess = false;
                }
            }
        }
        
        // Handle optional additional documents
        if (isset($_FILES['additional_documents']) && $_FILES['additional_documents']['error'][0] === UPLOAD_ERR_OK) {
            $additionalDocs = [];
            $fileCount = count($_FILES['additional_documents']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['additional_documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = time() . '_additional_' . $i . '_' . basename($_FILES['additional_documents']['name'][$i]);
                    $targetPath = $uploadDir . $fileName;
                    
                    // Validate file
                    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (in_array($_FILES['additional_documents']['type'][$i], $allowedTypes) && 
                        $_FILES['additional_documents']['size'][$i] <= $maxSize) {
                        if (move_uploaded_file($_FILES['additional_documents']['tmp_name'][$i], $targetPath)) {
                            $additionalDocs[] = $fileName;
                        }
                    }
                }
            }
            
            if (!empty($additionalDocs)) {
                $updatedDocuments['additional_documents'] = $additionalDocs;
            }
        }
        
        if ($uploadSuccess) {
            // Update application with new documents
            $stmt = $conn->prepare("
                UPDATE scholarship_applications 
                SET documents = ?, last_document_update = NOW()
                WHERE id = ? AND student_id = ?
            ");
            
            if ($stmt->execute([json_encode($updatedDocuments), $applicationId, $_SESSION['user_id']])) {
                $message = "Documents updated successfully!";
                
                // Log the update activity
                $logger->logSystemActivity(
                    $_SESSION['user_id'], 
                    'student', 
                    'APPLICATION_UPDATE', 
                    'scholarship_application', 
                    $applicationId,
                    "Student updated documents for application: " . $application['scholarship_title']
                );
                
                // Refresh application data
                $stmt = $conn->prepare("
                    SELECT sa.*, s.title as scholarship_title, s.other_requirements, s.provider_id,
                           u.fullname as provider_name
                    FROM scholarship_applications sa
                    JOIN scholarships s ON sa.scholarship_id = s.id
                    JOIN users u ON s.provider_id = u.id
                    WHERE sa.id = ? AND sa.student_id = ?
                ");
                $stmt->execute([$applicationId, $_SESSION['user_id']]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update documents. Please try again.";
            }
        } else {
            $error = "Upload failed: " . implode(', ', $uploadErrors);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        
        // Log the error
        $logger->logSystemActivity(
            $_SESSION['user_id'], 
            'student', 
            'APPLICATION_UPDATE', 
            'scholarship_application', 
            $applicationId,
            "Failed to update documents: " . $e->getMessage()
        );
    }
}

// Get current documents
$currentDocuments = json_decode($application['documents'], true) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application - ISKOLar</title>
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

        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
        }

        .file-preview {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 10px;
        }

        .file-preview.has-file {
            border-color: var(--primary);
            background-color: #f0f5ff;
        }

        .current-file {
            background-color: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,85,255,0.3);
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="../student_home.php">
            <i class="bi bi-mortarboard-fill"></i> ISKOLar
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link text-white" href="../student_home.php">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Application Documents</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><?= htmlspecialchars($application['scholarship_title']); ?></h5>
                            <p class="text-muted">Provider: <?= htmlspecialchars($application['provider_name']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-warning"><?= $application['status']; ?></span>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="text-muted">
                                <small>
                                    <i class="bi bi-calendar"></i> Applied: <?= date('M j, Y', strtotime($application['created_at'])); ?><br>
                                    <?php if ($application['last_document_update']): ?>
                                        <i class="bi bi-clock"></i> Last Updated: <?= date('M j, Y g:i A', strtotime($application['last_document_update'])); ?>
                                    <?php endif; ?>
                                </small>
                            </p>
                        </div>
                    </div>
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

            <!-- Document Upload Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cloud-upload"></i> Update Documents</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_documents">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Important:</strong> You can update your documents while your application is under review. 
                            Only upload new files if you want to replace the existing ones.
                        </div>

                        <?php
                        // Get scholarship requirements
                        $requirements = $application['other_requirements'] ? explode(',', $application['other_requirements']) : [];
                        
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
                        
                        // Always required documents
                        $alwaysRequired = ['academic_transcript'];
                        
                        // Determine required files based on scholarship requirements
                        $requiredFiles = $alwaysRequired;
                        foreach ($requirements as $req) {
                            $trimmedReq = trim($req);
                            if (isset($requirementFileMapping[$trimmedReq])) {
                                $requiredFiles[] = $requirementFileMapping[$trimmedReq];
                            }
                        }
                        ?>

                        <div class="row">
                            <!-- Academic Transcript (Always Required) -->
                            <div class="col-md-6 mb-4">
                                <label for="academic_transcript" class="form-label">
                                    <i class="bi bi-file-earmark-text"></i> Academic Transcript <span class="text-danger">*</span>
                                </label>
                                
                                <?php if (isset($currentDocuments['academic_transcript'])): ?>
                                    <div class="current-file">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <strong>Current File:</strong> <?= htmlspecialchars($currentDocuments['academic_transcript']); ?>
                                        <br><small class="text-muted">Upload a new file to replace this one</small>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" class="form-control" id="academic_transcript" name="academic_transcript" 
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Upload your latest academic transcript (PDF, JPG, PNG, max 5MB)</small>
                            </div>

                            <!-- Dynamic Required Documents -->
                            <?php foreach ($requirements as $req): ?>
                                <?php 
                                $trimmedReq = trim($req);
                                if (isset($requirementFileMapping[$trimmedReq])):
                                    $fieldName = $requirementFileMapping[$trimmedReq];
                                    $displayName = $trimmedReq;
                                ?>
                                    <div class="col-md-6 mb-4">
                                        <label for="<?= $fieldName; ?>" class="form-label">
                                            <i class="bi bi-file-earmark"></i> <?= htmlspecialchars($displayName); ?> <span class="text-danger">*</span>
                                        </label>
                                        
                                        <?php if (isset($currentDocuments[$fieldName])): ?>
                                            <div class="current-file">
                                                <i class="bi bi-check-circle text-success"></i>
                                                <strong>Current File:</strong> <?= htmlspecialchars($currentDocuments[$fieldName]); ?>
                                                <br><small class="text-muted">Upload a new file to replace this one</small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <input type="file" class="form-control" id="<?= $fieldName; ?>" name="<?= $fieldName; ?>" 
                                               accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="text-muted"><?= htmlspecialchars($displayName); ?> (PDF, JPG, PNG, max 5MB)</small>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <!-- Additional Documents -->
                            <div class="col-12 mb-4">
                                <label for="additional_documents" class="form-label">
                                    <i class="bi bi-plus-circle"></i> Additional Supporting Documents
                                </label>
                                
                                <?php if (isset($currentDocuments['additional_documents']) && is_array($currentDocuments['additional_documents'])): ?>
                                    <div class="current-file">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <strong>Current Additional Files:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($currentDocuments['additional_documents'] as $doc): ?>
                                                <li><?= htmlspecialchars($doc); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <small class="text-muted">Upload new files to replace these</small>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" class="form-control" id="additional_documents" name="additional_documents[]" 
                                       accept=".pdf,.jpg,.jpeg,.png" multiple>
                                <small class="text-muted">Awards, certificates, or other supporting documents (Multiple files allowed, max 5MB each)</small>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Note:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Only upload files if you want to replace existing ones</li>
                                <li>All files should be clear and readable</li>
                                <li>Supported formats: PDF, JPG, JPEG, PNG</li>
                                <li>Maximum file size: 5MB per file</li>
                                <li>Document updates are logged for security</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="../student_home.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload"></i> Update Documents
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Application Details -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-text"></i> Application Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Personal Statement:</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($application['personal_statement'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Why I Deserve This Scholarship:</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($application['why_deserve_scholarship'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($application['provider_notes']): ?>
                        <div class="alert alert-info">
                            <h6><i class="bi bi-chat-text"></i> Provider Notes:</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($application['provider_notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// File upload preview
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                const fileNames = Array.from(files).map(file => file.name).join(', ');
                
                // Create or update preview
                let preview = this.parentNode.querySelector('.file-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'file-preview';
                    this.parentNode.appendChild(preview);
                }
                
                preview.className = 'file-preview has-file';
                preview.innerHTML = `
                    <i class="bi bi-file-earmark-check text-success" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2"><strong>Selected:</strong> ${fileNames}</p>
                `;
            }
        });
    });
});
</script>

</body>
</html>