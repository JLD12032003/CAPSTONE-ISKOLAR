<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/ProviderProfile.php';
require_once __DIR__ . '/../../models/User.php';

$userModel = new User();
$profileModel = new ProviderProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->getProfile($_SESSION['user_id']);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'organization_name' => trim($_POST['organization_name']),
        'organization_type' => $_POST['organization_type'],
        'registration_number' => trim($_POST['registration_number']),
        'tin' => trim($_POST['tin']),
        'contact_person' => trim($_POST['contact_person']),
        'position' => trim($_POST['position']),
        'office_address' => trim($_POST['office_address']),
        'contact_number' => trim($_POST['contact_number']),
        'website' => trim($_POST['website']),
        'mission' => trim($_POST['mission']),
        'vision' => trim($_POST['vision'])
    ];

    // Validation
    if (empty($data['organization_name']) || empty($data['organization_type'])) {
        $error = "Organization name and type are required.";
    } else {
        try {
            if ($profile) {
                // Update existing profile
                if ($profileModel->updateProfile($_SESSION['user_id'], $data)) {
                    $message = "Profile updated successfully!";
                    $profile = $profileModel->getProfile($_SESSION['user_id']); // Refresh data
                } else {
                    $error = "Failed to update profile.";
                }
            } else {
                // Create new profile
                if ($profileModel->createProfile($_SESSION['user_id'], $data)) {
                    $message = "Profile created successfully!";
                    $profile = $profileModel->getProfile($_SESSION['user_id']); // Get created profile
                } else {
                    $error = "Failed to create profile.";
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Profile Setup - ISKOLar</title>
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

    .profile-container {
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
    }

    .profile-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        text-align: center;
    }

    .profile-body {
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

    .alert {
        border-radius: 10px;
        border: none;
    }

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    </style>
</head>
<body>

<div class="profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <h1><i class="bi bi-building"></i> Provider Profile Setup</h1>
            <p>Complete your organization profile to start creating scholarships</p>
        </div>

        <div class="profile-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Organization Information -->
                <div class="mb-4">
                    <h4 class="section-title">Organization Information</h4>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Organization Name *</label>
                            <input type="text" name="organization_name" class="form-control" 
                                   value="<?= htmlspecialchars($profile['organization_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Organization Type *</label>
                            <select name="organization_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Individual" <?= ($profile['organization_type'] ?? '') == 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="Foundation" <?= ($profile['organization_type'] ?? '') == 'Foundation' ? 'selected' : ''; ?>>Foundation</option>
                                <option value="Corporation" <?= ($profile['organization_type'] ?? '') == 'Corporation' ? 'selected' : ''; ?>>Corporation</option>
                                <option value="NGO" <?= ($profile['organization_type'] ?? '') == 'NGO' ? 'selected' : ''; ?>>NGO</option>
                                <option value="Government" <?= ($profile['organization_type'] ?? '') == 'Government' ? 'selected' : ''; ?>>Government</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Registration Number</label>
                            <input type="text" name="registration_number" class="form-control" 
                                   value="<?= htmlspecialchars($profile['registration_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">TIN</label>
                            <input type="text" name="tin" class="form-control" 
                                   value="<?= htmlspecialchars($profile['tin'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="mb-4">
                    <h4 class="section-title">Contact Information</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   value="<?= htmlspecialchars($profile['contact_person'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" 
                                   value="<?= htmlspecialchars($profile['position'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Office Address</label>
                        <textarea name="office_address" class="form-control" rows="3"><?= htmlspecialchars($profile['office_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" 
                                   value="<?= htmlspecialchars($profile['contact_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control" 
                                   value="<?= htmlspecialchars($profile['website'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Organization Details -->
                <div class="mb-4">
                    <h4 class="section-title">Organization Details</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Mission</label>
                        <textarea name="mission" class="form-control" rows="3" 
                                  placeholder="Describe your organization's mission"><?= htmlspecialchars($profile['mission'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vision</label>
                        <textarea name="vision" class="form-control" rows="3" 
                                  placeholder="Describe your organization's vision"><?= htmlspecialchars($profile['vision'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> <?= $profile ? 'Update Profile' : 'Create Profile'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>