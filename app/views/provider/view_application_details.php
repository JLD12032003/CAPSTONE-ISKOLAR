<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    http_response_code(403);
    exit('Access denied: Invalid session or user type');
}

require_once __DIR__ . '/../../../config/database.php';

$applicationId = intval($_GET['id'] ?? 0);

if (!$applicationId) {
    http_response_code(400);
    exit('Invalid application ID');
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Get application details with student profile
    $stmt = $conn->prepare("
        SELECT sa.*, 
               u.fullname as student_name, 
               u.email as student_email,
               sp.*,
               s.title as scholarship_title,
               s.amount as scholarship_amount,
               s.scholarship_type
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        JOIN users u ON sa.student_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE sa.id = ? AND s.provider_id = ?
    ");
    $stmt->execute([$applicationId, $_SESSION['user_id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        http_response_code(404);
        exit('Application not found or access denied');
    }
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>

<div class="row">
    <!-- Student Information -->
    <div class="col-md-6">
        <h6 class="text-primary mb-3"><i class="bi bi-person-circle"></i> Student Information</h6>
        
        <div class="mb-3">
            <strong>Full Name:</strong><br>
            <?= htmlspecialchars($application['student_name']); ?>
        </div>
        
        <div class="mb-3">
            <strong>Email:</strong><br>
            <?= htmlspecialchars($application['student_email']); ?>
        </div>
        
        <?php if ($application['mobile_number']): ?>
        <div class="mb-3">
            <strong>Mobile Number:</strong><br>
            <?= htmlspecialchars($application['mobile_number']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['present_address']): ?>
        <div class="mb-3">
            <strong>Address:</strong><br>
            <?= htmlspecialchars($application['present_address']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['birthdate']): ?>
        <div class="mb-3">
            <strong>Age:</strong><br>
            <?= date_diff(date_create($application['birthdate']), date_create('today'))->y; ?> years old
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Academic Information -->
    <div class="col-md-6">
        <h6 class="text-primary mb-3"><i class="bi bi-mortarboard"></i> Academic Information</h6>
        
        <?php if ($application['school_name']): ?>
        <div class="mb-3">
            <strong>School:</strong><br>
            <?= htmlspecialchars($application['school_name']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['course']): ?>
        <div class="mb-3">
            <strong>Course:</strong><br>
            <?= htmlspecialchars($application['course']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['year_level']): ?>
        <div class="mb-3">
            <strong>Year Level:</strong><br>
            <?= htmlspecialchars($application['year_level']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['gwa']): ?>
        <div class="mb-3">
            <strong>Latest Weighted Average:</strong><br>
            <span class="badge bg-warning text-dark fs-6"><?= number_format($application['gwa'], 2); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($application['awards_received']): ?>
        <div class="mb-3">
            <strong>Awards & Recognition:</strong><br>
            <?= nl2br(htmlspecialchars($application['awards_received'])); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<hr class="my-4">

<!-- Family Background -->
<div class="row">
    <div class="col-12">
        <h6 class="text-primary mb-3"><i class="bi bi-house"></i> Family Background</h6>
    </div>
    
    <div class="col-md-6">
        <?php if ($application['father_name']): ?>
        <div class="mb-3">
            <strong>Father:</strong><br>
            <?= htmlspecialchars($application['father_name']); ?>
            <?php if ($application['father_occupation']): ?>
                <br><small class="text-muted">Occupation: <?= htmlspecialchars($application['father_occupation']); ?></small>
            <?php endif; ?>
            <?php if ($application['father_income']): ?>
                <br><small class="text-muted">Income: ₱<?= number_format($application['father_income'], 2); ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['mother_name']): ?>
        <div class="mb-3">
            <strong>Mother:</strong><br>
            <?= htmlspecialchars($application['mother_name']); ?>
            <?php if ($application['mother_occupation']): ?>
                <br><small class="text-muted">Occupation: <?= htmlspecialchars($application['mother_occupation']); ?></small>
            <?php endif; ?>
            <?php if ($application['mother_income']): ?>
                <br><small class="text-muted">Income: ₱<?= number_format($application['mother_income'], 2); ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-6">
        <?php if ($application['family_monthly_income']): ?>
        <div class="mb-3">
            <strong>Total Family Monthly Income:</strong><br>
            <span class="badge bg-info fs-6">₱<?= number_format($application['family_monthly_income'], 2); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($application['num_siblings']): ?>
        <div class="mb-3">
            <strong>Number of Siblings:</strong><br>
            <?= $application['num_siblings']; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($application['is_4ps_beneficiary']): ?>
        <div class="mb-3">
            <strong>4Ps Beneficiary:</strong><br>
            <span class="badge <?= $application['is_4ps_beneficiary'] == 'Yes' ? 'bg-success' : 'bg-secondary'; ?>">
                <?= $application['is_4ps_beneficiary']; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<hr class="my-4">

<!-- Application Details -->
<div class="row">
    <div class="col-12">
        <h6 class="text-primary mb-3"><i class="bi bi-file-text"></i> Application Details</h6>
        
        <div class="mb-3">
            <strong>Scholarship Applied For:</strong><br>
            <?= htmlspecialchars($application['scholarship_title']); ?>
            <span class="badge bg-success ms-2">₱<?= number_format($application['scholarship_amount'], 0); ?></span>
        </div>
        
        <div class="mb-3">
            <strong>Application Date:</strong><br>
            <?= date('F j, Y g:i A', strtotime($application['created_at'])); ?>
        </div>
        
        <div class="mb-3">
            <strong>Current Status:</strong><br>
            <?php
            $statusClass = 'bg-secondary';
            if ($application['status'] == 'Under Review') $statusClass = 'bg-warning';
            if ($application['status'] == 'Approved') $statusClass = 'bg-success';
            if ($application['status'] == 'Rejected') $statusClass = 'bg-danger';
            ?>
            <span class="badge <?= $statusClass; ?> fs-6"><?= $application['status']; ?></span>
        </div>
        
        <?php if ($application['personal_statement']): ?>
        <div class="mb-3">
            <strong>Tell me about yourself:</strong><br>
            <div class="bg-light p-3 rounded">
                <?= nl2br(htmlspecialchars($application['personal_statement'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($application['why_deserve_scholarship']): ?>
        <div class="mb-3">
            <strong>Why I Deserve This Scholarship:</strong><br>
            <div class="bg-light p-3 rounded">
                <?= nl2br(htmlspecialchars($application['why_deserve_scholarship'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($application['provider_notes']): ?>
        <div class="mb-3">
            <strong>Provider Notes:</strong><br>
            <div class="bg-warning bg-opacity-10 p-3 rounded border border-warning">
                <?= nl2br(htmlspecialchars($application['provider_notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<hr class="my-4">

<!-- Uploaded Documents -->
<div class="row">
    <div class="col-12">
        <h6 class="text-primary mb-3"><i class="bi bi-file-earmark-text"></i> Uploaded Documents</h6>
        
        <?php 
        $documents = [];
        if (!empty($application['documents'])) {
            $documents = json_decode($application['documents'], true);
        }
        
        if (!empty($documents) && is_array($documents)): ?>
            <div class="row">
                <?php foreach ($documents as $docType => $fileName): ?>
                    <?php if (!empty($fileName)): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <?php
                                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                    $iconClass = 'bi-file-earmark';
                                    $iconColor = '#6c757d';
                                    
                                    // Set icon based on file type
                                    if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $iconClass = 'bi-file-earmark-image';
                                        $iconColor = '#28a745';
                                    } elseif ($fileExtension === 'pdf') {
                                        $iconClass = 'bi-file-earmark-pdf';
                                        $iconColor = '#dc3545';
                                    } elseif (in_array($fileExtension, ['doc', 'docx'])) {
                                        $iconClass = 'bi-file-earmark-word';
                                        $iconColor = '#0d6efd';
                                    }
                                    
                                    // Format document type name
                                    $docTypeName = ucwords(str_replace('_', ' ', $docType));
                                    
                                    // File path - Use secure file viewer
                                    $filePath = "view_file.php?student_id={$application['student_id']}&file=" . urlencode($fileName);
                                    $fileExists = file_exists(__DIR__ . '/../uploads/applications/' . $application['student_id'] . '/' . $fileName);
                                    ?>
                                    
                                    <i class="<?= $iconClass; ?>" style="font-size: 3rem; color: <?= $iconColor; ?>;"></i>
                                    
                                    <h6 class="mt-2 mb-1"><?= htmlspecialchars($docTypeName); ?></h6>
                                    
                                    <small class="text-muted d-block mb-2">
                                        <?= htmlspecialchars($fileName); ?>
                                    </small>
                                    
                                    <?php if ($fileExists): ?>
                                        <div class="d-flex gap-1 justify-content-center">
                                            <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewImage('<?= htmlspecialchars($filePath); ?>', '<?= htmlspecialchars($docTypeName); ?>')">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="<?= htmlspecialchars($filePath); ?>" 
                                               class="btn btn-sm btn-outline-success" 
                                               download="<?= htmlspecialchars($docTypeName . '_' . $application['student_name'] . '.' . $fileExtension); ?>"
                                               target="_blank">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                        </div>
                                        
                                        <?php
                                        // Get file size if possible
                                        $fullFilePath = __DIR__ . '/../uploads/applications/' . $application['student_id'] . '/' . $fileName;
                                        if (file_exists($fullFilePath)) {
                                            $fileSize = filesize($fullFilePath);
                                            $fileSizeFormatted = $fileSize < 1024 ? $fileSize . ' B' : 
                                                               ($fileSize < 1048576 ? round($fileSize/1024, 1) . ' KB' : 
                                                                round($fileSize/1048576, 1) . ' MB');
                                            echo '<small class="text-muted d-block mt-1">' . $fileSizeFormatted . '</small>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <div class="text-danger">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <small>File not found</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No documents have been uploaded for this application.
            </div>
        <?php endif; ?>

<!-- Action Buttons -->
<div class="d-flex justify-content-end gap-2 mt-4">
    <?php if ($application['status'] == 'Submitted' || $application['status'] == 'Under Review'): ?>
        <button class="btn btn-warning" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Under Review')">
            <i class="bi bi-clock"></i> Mark Under Review
        </button>
        <button class="btn btn-success" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Approved')">
            <i class="bi bi-check-circle"></i> Approve
        </button>
        <button class="btn btn-danger" onclick="updateApplicationStatus(<?= $application['id']; ?>, 'Rejected')">
            <i class="bi bi-x-circle"></i> Reject
        </button>
    <?php endif; ?>
    
    <button class="btn btn-secondary" onclick="closeApplicationModal()">
        <i class="bi bi-x"></i> Close
    </button>
</div>