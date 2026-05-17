<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$userModel = new User();
$database = new Database();
$conn = $database->connect();

$user = $userModel->findById($_SESSION['user_id']);

// Check if verification already exists
$stmt = $conn->prepare("SELECT * FROM admin_verifications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existingVerification = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingVerification) {
    // Redirect to verification status page
    header("Location: verification_status.php");
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $requiredFields = [
            'first_name', 'last_name', 'birthdate', 'gender', 'mobile_number',
            'address', 'city', 'province', 'position', 'valid_id_type', 'valid_id_number'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }
        
        // Validate birthdate (must be at least 18 years old)
        $birthdate = new DateTime($_POST['birthdate']);
        $today = new DateTime();
        $age = $today->diff($birthdate)->y;
        
        if ($age < 18) {
            throw new Exception("You must be at least 18 years old to register as an administrator");
        }
        
        // Handle file upload
        if (!isset($_FILES['valid_id_file']) || $_FILES['valid_id_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please upload a valid ID document");
        }
        
        $file = $_FILES['valid_id_file'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Invalid file type. Please upload JPG, PNG, or PDF files only");
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception("File size too large. Maximum size is 5MB");
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'id_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
        $uploadPath = __DIR__ . '/../../../uploads/identity_verification/' . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to upload file");
        }
        
        // Insert verification data
        $stmt = $conn->prepare("
            INSERT INTO admin_verifications (
                user_id, first_name, middle_name, last_name, suffix, birthdate, gender, nationality,
                mobile_number, landline, address, city, province, postal_code,
                employee_id, department, position, years_of_service, employment_status,
                admin_role, role_description, valid_id_type, valid_id_number, valid_id_file
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            trim($_POST['first_name']),
            trim($_POST['middle_name']) ?: null,
            trim($_POST['last_name']),
            trim($_POST['suffix']) ?: null,
            $_POST['birthdate'],
            $_POST['gender'],
            trim($_POST['nationality']) ?: 'Filipino',
            trim($_POST['mobile_number']),
            trim($_POST['landline']) ?: null,
            trim($_POST['address']),
            trim($_POST['city']),
            trim($_POST['province']),
            trim($_POST['postal_code']) ?: null,
            trim($_POST['employee_id']) ?: null,
            trim($_POST['department']) ?: null,
            trim($_POST['position']),
            intval($_POST['years_of_service']) ?: null,
            $_POST['employment_status'] ?: 'Regular',
            $user['admin_role'],
            trim($_POST['role_description']) ?: null,
            $_POST['valid_id_type'],
            trim($_POST['valid_id_number']),
            $fileName
        ]);
        
        $message = "Identity verification submitted successfully! Your information will be reviewed by system administrators.";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        // Delete uploaded file if there was an error
        if (isset($fileName) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification - ISKOLar</title>
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

    .verification-container {
        max-width: 900px;
        margin: 50px auto;
        padding: 20px;
    }

    .verification-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .verification-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        text-align: center;
    }

    .verification-body {
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

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .file-upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }

    .file-upload-area:hover {
        border-color: var(--primary);
        background: #f0f5ff;
    }

    .required-info {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }
    </style>
</head>
<body>

<div class="verification-container">
    <div class="verification-card">
        <div class="verification-header">
            <h1><i class="bi bi-shield-check"></i> Identity Verification</h1>
            <p>Complete your administrator identity verification to access full system features</p>
        </div>

        <div class="verification-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
                    <div class="mt-3">
                        <a href="verification_status.php" class="btn btn-success">View Verification Status</a>
                        <a href="dashboard.php" class="btn btn-outline-success">Go to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
                <div class="required-info">
                    <h5><i class="bi bi-info-circle text-warning"></i> Verification Required</h5>
                    <p class="mb-0">As a school administrator, you must complete identity verification before accessing administrative functions. This ensures the security and integrity of the system.</p>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Personal Information -->
                    <div class="mb-4">
                        <h4 class="section-title">Personal Information</h4>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Suffix</label>
                                <select name="suffix" class="form-select">
                                    <option value="">None</option>
                                    <option value="Jr." <?= ($_POST['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?= ($_POST['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="II" <?= ($_POST['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                    <option value="III" <?= ($_POST['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Birthdate *</label>
                                <input type="date" name="birthdate" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['birthdate'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= ($_POST['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nationality</label>
                                <input type="text" name="nationality" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['nationality'] ?? 'Filipino'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="mb-4">
                        <h4 class="section-title">Contact Information</h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobile Number *</label>
                                <input type="tel" name="mobile_number" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['mobile_number'] ?? ''); ?>" 
                                       placeholder="09XX-XXX-XXXX" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Landline</label>
                                <input type="tel" name="landline" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['landline'] ?? ''); ?>" 
                                       placeholder="(02) XXXX-XXXX">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Complete Address *</label>
                            <textarea name="address" class="form-control" rows="3" 
                                      placeholder="House/Unit Number, Street, Barangay" required><?= htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Province *</label>
                                <input type="text" name="province" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['province'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="mb-4">
                        <h4 class="section-title">Professional Information</h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['employee_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['department'] ?? ''); ?>" 
                                       placeholder="e.g., Academic Affairs, Finance, Registrar">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position/Title *</label>
                                <input type="text" name="position" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['position'] ?? ''); ?>" 
                                       placeholder="e.g., Vice President, Dean, Coordinator" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Years of Service</label>
                                <input type="number" name="years_of_service" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['years_of_service'] ?? ''); ?>" 
                                       min="0" max="50">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employment Status</label>
                                <select name="employment_status" class="form-select">
                                    <option value="Regular" <?= ($_POST['employment_status'] ?? 'Regular') == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                    <option value="Contractual" <?= ($_POST['employment_status'] ?? '') == 'Contractual' ? 'selected' : ''; ?>>Contractual</option>
                                    <option value="Part-time" <?= ($_POST['employment_status'] ?? '') == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Administrative Role</label>
                                <input type="text" class="form-control" 
                                       value="<?= ucwords(str_replace('_', ' ', $user['admin_role'])); ?>" readonly>
                                <small class="text-muted">Role selected during registration</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role Description</label>
                            <textarea name="role_description" class="form-control" rows="3" 
                                      placeholder="Describe your responsibilities and authority within the institution"><?= htmlspecialchars($_POST['role_description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Identity Document -->
                    <div class="mb-4">
                        <h4 class="section-title">Identity Verification Document</h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Valid ID Type *</label>
                                <select name="valid_id_type" class="form-select" required>
                                    <option value="">Select ID Type</option>
                                    <option value="Government ID" <?= ($_POST['valid_id_type'] ?? '') == 'Government ID' ? 'selected' : ''; ?>>Government ID</option>
                                    <option value="Passport" <?= ($_POST['valid_id_type'] ?? '') == 'Passport' ? 'selected' : ''; ?>>Passport</option>
                                    <option value="Drivers License" <?= ($_POST['valid_id_type'] ?? '') == 'Drivers License' ? 'selected' : ''; ?>>Driver's License</option>
                                    <option value="SSS ID" <?= ($_POST['valid_id_type'] ?? '') == 'SSS ID' ? 'selected' : ''; ?>>SSS ID</option>
                                    <option value="PhilHealth ID" <?= ($_POST['valid_id_type'] ?? '') == 'PhilHealth ID' ? 'selected' : ''; ?>>PhilHealth ID</option>
                                    <option value="TIN ID" <?= ($_POST['valid_id_type'] ?? '') == 'TIN ID' ? 'selected' : ''; ?>>TIN ID</option>
                                    <option value="Voters ID" <?= ($_POST['valid_id_type'] ?? '') == 'Voters ID' ? 'selected' : ''; ?>>Voter's ID</option>
                                    <option value="PRC ID" <?= ($_POST['valid_id_type'] ?? '') == 'PRC ID' ? 'selected' : ''; ?>>PRC ID</option>
                                    <option value="School ID" <?= ($_POST['valid_id_type'] ?? '') == 'School ID' ? 'selected' : ''; ?>>School ID</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Number *</label>
                                <input type="text" name="valid_id_number" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['valid_id_number'] ?? ''); ?>" 
                                       placeholder="Enter ID number" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Upload Valid ID *</label>
                            <div class="file-upload-area">
                                <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">Upload Your Valid ID</h5>
                                <p class="text-muted mb-3">Clear photo or scan of your government-issued ID</p>
                                <input type="file" name="valid_id_file" class="form-control" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max 5MB)</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Ensure your ID is clear and readable</li>
                                <li>All information must match your ID exactly</li>
                                <li>Your verification will be reviewed within 24-48 hours</li>
                                <li>You will receive email notification of verification status</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check"></i> Submit Verification
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// File upload preview
document.querySelector('input[type="file"]').addEventListener('change', function() {
    const file = this.files[0];
    const uploadArea = this.closest('.file-upload-area');
    const existingPreview = uploadArea.querySelector('.file-preview');
    
    if (existingPreview) {
        existingPreview.remove();
    }
    
    if (file) {
        const preview = document.createElement('div');
        preview.className = 'file-preview mt-3';
        preview.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-file-earmark-check"></i> 
                <strong>File selected:</strong> ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
            </div>
        `;
        uploadArea.appendChild(preview);
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = ['first_name', 'last_name', 'birthdate', 'gender', 'mobile_number', 'address', 'city', 'province', 'position', 'valid_id_type', 'valid_id_number'];
    
    for (let field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            e.preventDefault();
            alert(`Please fill in the ${field.replace('_', ' ')} field.`);
            input.focus();
            return;
        }
    }
    
    const fileInput = document.querySelector('input[type="file"]');
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please upload a valid ID document.');
        fileInput.focus();
        return;
    }
});
</script>
</body>
</html>