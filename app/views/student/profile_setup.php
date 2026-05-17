<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/models/User.php';
require_once __DIR__ . '/../../../app/models/StudentProfile.php';

$userModel = new User();
$profileModel = new StudentProfile();

$user = $userModel->findById($_SESSION['user_id']);
$profile = $profileModel->getProfile($_SESSION['user_id']);

// Create profile if doesn't exist
if (!$profile) {
    $profileModel->createProfile($_SESSION['user_id']);
    $profile = $profileModel->getProfile($_SESSION['user_id']);
}

// Get current step
$currentStep = isset($_GET['step']) ? intval($_GET['step']) : ($user['profile_completion_step'] ?: 1);

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = intval($_POST['step']);
    
    try {
        switch($step) {
            case 1:
                $data = [
                    'last_name' => trim($_POST['last_name']),
                    'first_name' => trim($_POST['first_name']),
                    'middle_name' => trim($_POST['middle_name']),
                    'suffix' => trim($_POST['suffix']),
                    'birthdate' => $_POST['birthdate'],
                    'place_of_birth' => trim($_POST['place_of_birth']),
                    'sex' => $_POST['sex'],
                    'civil_status' => $_POST['civil_status'],
                    'citizenship' => trim($_POST['citizenship']),
                    'mobile_number' => trim($_POST['mobile_number']),
                    'landline' => trim($_POST['landline']),
                    'present_address' => trim($_POST['present_address']),
                    'permanent_address' => trim($_POST['permanent_address']),
                    'zip_code' => trim($_POST['zip_code'])
                ];
                $profileModel->updatePhase1($_SESSION['user_id'], $data);
                $userModel->updateProfileCompletion($_SESSION['user_id'], 2);
                header("Location: profile_setup.php?step=2");
                exit();
                break;
                
            case 2:
                $data = [
                    'school_id' => intval($_POST['school_id']),
                    'school_name' => trim($_POST['school_name']),
                    'school_address' => trim($_POST['school_address']),
                    'school_sector' => $_POST['school_sector'],
                    'course' => trim($_POST['course']),
                    'year_level' => $_POST['year_level'],
                    'type_of_disability' => trim($_POST['type_of_disability'])
                ];
                $profileModel->updatePhase2($_SESSION['user_id'], $data);
                $userModel->updateProfileCompletion($_SESSION['user_id'], 3);
                header("Location: profile_setup.php?step=3");
                exit();
                break;
                
            case 3:
                $data = [
                    'father_name' => trim($_POST['father_name']),
                    'father_address' => trim($_POST['father_address']),
                    'father_contact' => trim($_POST['father_contact']),
                    'father_occupation' => trim($_POST['father_occupation']),
                    'father_employer' => trim($_POST['father_employer']),
                    'father_employer_address' => trim($_POST['father_employer_address']),
                    'father_education' => trim($_POST['father_education']),
                    'father_income' => floatval($_POST['father_income']),
                    'mother_name' => trim($_POST['mother_name']),
                    'mother_address' => trim($_POST['mother_address']),
                    'mother_contact' => trim($_POST['mother_contact']),
                    'mother_occupation' => trim($_POST['mother_occupation']),
                    'mother_employer' => trim($_POST['mother_employer']),
                    'mother_employer_address' => trim($_POST['mother_employer_address']),
                    'mother_education' => trim($_POST['mother_education']),
                    'mother_income' => floatval($_POST['mother_income']),
                    'legal_guardian' => trim($_POST['legal_guardian']),
                    'num_siblings' => intval($_POST['num_siblings'])
                ];
                $profileModel->updatePhase3($_SESSION['user_id'], $data);
                $userModel->updateProfileCompletion($_SESSION['user_id'], 4);
                header("Location: profile_setup.php?step=4");
                exit();
                break;
                
            case 4:
                $data = [
                    'family_monthly_income' => floatval($_POST['family_monthly_income']),
                    'is_4ps_beneficiary' => $_POST['is_4ps_beneficiary']
                ];
                $profileModel->updatePhase4($_SESSION['user_id'], $data);
                $userModel->updateProfileCompletion($_SESSION['user_id'], 5);
                header("Location: profile_setup.php?step=5");
                exit();
                break;
                
            case 5:
                $data = [
                    'gwa' => floatval($_POST['gwa']),
                    'awards_received' => trim($_POST['awards_received']),
                    'profile_photo' => '' // Handle file upload separately
                ];
                $profileModel->updatePhase5($_SESSION['user_id'], $data);
                $userModel->updateProfileCompletion($_SESSION['user_id'], 5, true);
                header("Location: ../student_home.php");
                exit();
                break;
        }
    } catch (Exception $e) {
        $error = "Error saving data: " . $e->getMessage();
    }
}

// Get schools for dropdown
$schools = $profileModel->getSchools();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - ISKOLar</title>
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
        background: linear-gradient(135deg, var(--dark), var(--primary));
        min-height: 100vh;
        padding: 20px 0;
    }

    .profile-container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        overflow: hidden;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 30px;
        text-align: center;
    }

    .profile-header h1 {
        font-weight: 700;
        margin-bottom: 10px;
    }

    .progress-container {
        padding: 20px 30px;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .step-indicator {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        margin-bottom: 10px;
    }

    .step-indicator::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 4px;
        background: #dee2e6;
        z-index: 0;
    }

    .step-indicator .progress-line {
        position: absolute;
        top: 20px;
        left: 0;
        height: 4px;
        background: var(--primary);
        z-index: 1;
        transition: width 0.3s ease;
    }

    .step {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        border: 4px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        z-index: 2;
        position: relative;
    }

    .step.active {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }

    .step.completed {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }

    .form-section {
        padding: 40px;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 12px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
    }

    .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: #0044cc;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,85,255,0.3);
    }

    .btn-secondary {
        background: #6c757d;
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
    }

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary);
    }

    .required {
        color: #dc3545;
    }

    .introduction-section {
        padding: 20px 0;
    }

    .introduction-section .alert {
        border-left: 4px solid var(--primary);
    }

    .introduction-section .card {
        transition: all 0.3s ease;
    }

    .introduction-section .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }

    .btn-lg {
        padding: 15px 40px;
        font-size: 1.1rem;
        border-radius: 50px;
        transition: all 0.3s ease;
    }

    .btn-lg:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,85,255,0.3);
    }
    </style>
</head>
<body>

<div class="profile-container">
    <!-- Header -->
    <div class="profile-header">
        <h1><i class="bi bi-person-badge"></i> Complete Your Profile</h1>
        <p>Help us know you better to match you with the right scholarships</p>
    </div>

    <!-- Progress Indicator -->
    <div class="progress-container">
        <div class="step-indicator">
            <div class="progress-line" style="width: <?= (($currentStep - 1) / 4) * 100 ?>%;"></div>
            <div class="step <?= $currentStep >= 1 ? 'active' : '' ?> <?= $currentStep > 1 ? 'completed' : '' ?>">1</div>
            <div class="step <?= $currentStep >= 2 ? 'active' : '' ?> <?= $currentStep > 2 ? 'completed' : '' ?>">2</div>
            <div class="step <?= $currentStep >= 3 ? 'active' : '' ?> <?= $currentStep > 3 ? 'completed' : '' ?>">3</div>
            <div class="step <?= $currentStep >= 4 ? 'active' : '' ?> <?= $currentStep > 4 ? 'completed' : '' ?>">4</div>
            <div class="step <?= $currentStep >= 5 ? 'active' : '' ?>">5</div>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <small class="text-muted">Personal</small>
            <small class="text-muted">Education</small>
            <small class="text-muted">Family</small>
            <small class="text-muted">Financial</small>
            <small class="text-muted">Academic</small>
        </div>
    </div>

    <!-- Form Section -->
    <div class="form-section">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($currentStep == 1 && !isset($_POST['step'])): ?>
        <!-- INTRODUCTION SECTION -->
        <div class="introduction-section mb-5">
            <div class="text-center mb-4">
                <i class="bi bi-info-circle" style="font-size: 4rem; color: var(--primary);"></i>
                <h2 class="mt-3 mb-4" style="color: var(--primary);">Welcome to Your Profile Setup!</h2>
            </div>
            
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="alert alert-info border-0" style="background: linear-gradient(135deg, #e3f2fd, #f0f8ff);">
                        <h5 class="alert-heading"><i class="bi bi-lightbulb"></i> Why do we need your complete profile?</h5>
                        <p class="mb-3">Your detailed profile helps us:</p>
                        <ul class="mb-3">
                            <li><strong>Match you with the right scholarships</strong> - We'll recommend opportunities that fit your background and needs</li>
                            <li><strong>Speed up your applications</strong> - No need to fill out forms repeatedly for each scholarship</li>
                            <li><strong>Verify your eligibility</strong> - Providers can quickly see if you meet their requirements</li>
                            <li><strong>Provide better support</strong> - We can offer personalized guidance based on your situation</li>
                        </ul>
                        <p class="mb-0"><i class="bi bi-shield-check text-success"></i> <strong>Your information is secure</strong> and will only be shared with scholarship providers when you apply.</p>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title text-primary"><i class="bi bi-list-check"></i> What you'll need to complete:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check text-success"></i> Personal information</li>
                                        <li><i class="bi bi-check text-success"></i> Educational background</li>
                                        <li><i class="bi bi-check text-success"></i> Family details</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check text-success"></i> Financial information</li>
                                        <li><i class="bi bi-check text-success"></i> Academic records</li>
                                        <li><i class="bi bi-check text-success"></i> Awards & achievements</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="bi bi-clock" style="font-size: 2rem; color: var(--secondary);"></i>
                                <h6 class="mt-2">5-10 Minutes</h6>
                                <small class="text-muted">Average completion time</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="bi bi-save" style="font-size: 2rem; color: var(--secondary);"></i>
                                <h6 class="mt-2">Auto-Save</h6>
                                <small class="text-muted">Progress saved automatically</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="bi bi-pencil-square" style="font-size: 2rem; color: var(--secondary);"></i>
                                <h6 class="mt-2">Editable</h6>
                                <small class="text-muted">Update anytime later</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="startProfileSetup()">
                            <i class="bi bi-play-circle"></i> Start Profile Setup
                        </button>
                        <br>
                        <small class="text-muted mt-2 d-block">This information is based on CHED scholarship requirements</small>
                    </div>
                </div>
            </div>
        </div>

        <div id="profileForm" style="display: none;">
        <?php endif; ?>

        <?php if ($currentStep == 1): ?>
        <!-- PHASE 1: Personal Information -->
        <h3 class="section-title"><i class="bi bi-person-fill"></i> Personal Information</h3>
        <form method="POST">
            <input type="hidden" name="step" value="1">
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($profile['middle_name'] ?? '') ?>">
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Suffix</label>
                    <input type="text" name="suffix" class="form-control" value="<?= htmlspecialchars($profile['suffix'] ?? '') ?>" placeholder="Jr.">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Birthdate <span class="required">*</span></label>
                    <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($profile['birthdate'] ?? '') ?>" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Place of Birth <span class="required">*</span></label>
                    <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($profile['place_of_birth'] ?? '') ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sex <span class="required">*</span></label>
                    <select name="sex" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Male" <?= ($profile['sex'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($profile['sex'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Civil Status <span class="required">*</span></label>
                    <select name="civil_status" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Single" <?= ($profile['civil_status'] ?? '') == 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= ($profile['civil_status'] ?? '') == 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Widowed" <?= ($profile['civil_status'] ?? '') == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        <option value="Separated" <?= ($profile['civil_status'] ?? '') == 'Separated' ? 'selected' : '' ?>>Separated</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Citizenship <span class="required">*</span></label>
                    <input type="text" name="citizenship" class="form-control" value="<?= htmlspecialchars($profile['citizenship'] ?? 'Filipino') ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mobile Number <span class="required">*</span></label>
                    <input type="tel" name="mobile_number" class="form-control" value="<?= htmlspecialchars($profile['mobile_number'] ?? '') ?>" placeholder="09XX-XXX-XXXX" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Landline</label>
                    <input type="tel" name="landline" class="form-control" value="<?= htmlspecialchars($profile['landline'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Present Address <span class="required">*</span></label>
                <textarea name="present_address" class="form-control" rows="2" required><?= htmlspecialchars($profile['present_address'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Permanent Address <span class="required">*</span></label>
                <textarea name="permanent_address" class="form-control" rows="2" required><?= htmlspecialchars($profile['permanent_address'] ?? '') ?></textarea>
                <small class="text-muted">Same as present address? Just copy above.</small>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">ZIP Code <span class="required">*</span></label>
                    <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($profile['zip_code'] ?? '') ?>" required>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($currentStep == 2): ?>
        <!-- PHASE 2: Educational Background -->
        <h3 class="section-title"><i class="bi bi-mortarboard-fill"></i> Educational Background</h3>
        <form method="POST">
            <input type="hidden" name="step" value="2">
            
            <div class="mb-3">
                <label class="form-label">School <span class="required">*</span></label>
                <select name="school_id" class="form-select" id="schoolSelect" required>
                    <option value="">Select your school</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= $school['id'] ?>" <?= ($profile['school_id'] ?? '') == $school['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($school['school_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">School Name (if not in list) <span class="required">*</span></label>
                <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($profile['school_name'] ?? '') ?>" id="schoolNameInput">
            </div>

            <div class="mb-3">
                <label class="form-label">School Address <span class="required">*</span></label>
                <textarea name="school_address" class="form-control" rows="2" required><?= htmlspecialchars($profile['school_address'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">School Sector <span class="required">*</span></label>
                    <select name="school_sector" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Public" <?= ($profile['school_sector'] ?? '') == 'Public' ? 'selected' : '' ?>>Public</option>
                        <option value="Private" <?= ($profile['school_sector'] ?? '') == 'Private' ? 'selected' : '' ?>>Private</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Year Level <span class="required">*</span></label>
                    <select name="year_level" class="form-select" required>
                        <option value="">Select</option>
                        <option value="1st Year" <?= ($profile['year_level'] ?? '') == '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= ($profile['year_level'] ?? '') == '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= ($profile['year_level'] ?? '') == '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= ($profile['year_level'] ?? '') == '4th Year' ? 'selected' : '' ?>>4th Year</option>
                        <option value="5th Year" <?= ($profile['year_level'] ?? '') == '5th Year' ? 'selected' : '' ?>>5th Year</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Course/Program <span class="required">*</span></label>
                <select name="course" class="form-select" required>
                    <option value="">Select your course/program</option>
                    <?php 
                    $courses = [
                        'Computer Science',
                        'Information Technology',
                        'Engineering',
                        'Business Administration',
                        'Accounting',
                        'Education',
                        'Nursing',
                        'Medicine',
                        'Psychology',
                        'Mathematics',
                        'English',
                        'Communications',
                        'Arts and Design',
                        'Architecture',
                        'Tourism and Hospitality'
                    ];
                    foreach ($courses as $course): ?>
                        <option value="<?= $course; ?>" <?= ($profile['course'] ?? '') == $course ? 'selected' : ''; ?>>
                            <?= $course; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Type of Disability (if applicable)</label>
                <input type="text" name="type_of_disability" class="form-control" value="<?= htmlspecialchars($profile['type_of_disability'] ?? '') ?>" placeholder="Leave blank if not applicable">
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="profile_setup.php?step=1" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <button type="submit" class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($currentStep == 3): ?>
        <!-- PHASE 3: Family Background -->
        <h3 class="section-title"><i class="bi bi-people-fill"></i> Family Background</h3>
        <form method="POST">
            <input type="hidden" name="step" value="3">
            
            <h5 class="text-primary mt-4 mb-3">Father's Information</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Father's Name</label>
                    <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($profile['father_name'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="father_contact" class="form-control" value="<?= htmlspecialchars($profile['father_contact'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="father_address" class="form-control" rows="2"><?= htmlspecialchars($profile['father_address'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="father_occupation" class="form-control" value="<?= htmlspecialchars($profile['father_occupation'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employer</label>
                    <input type="text" name="father_employer" class="form-control" value="<?= htmlspecialchars($profile['father_employer'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employer Address</label>
                    <input type="text" name="father_employer_address" class="form-control" value="<?= htmlspecialchars($profile['father_employer_address'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Education Level</label>
                    <input type="text" name="father_education" class="form-control" value="<?= htmlspecialchars($profile['father_education'] ?? '') ?>" placeholder="e.g., College">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Monthly Income</label>
                    <input type="number" name="father_income" class="form-control" value="<?= htmlspecialchars($profile['father_income'] ?? '') ?>" step="0.01">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">Mother's Information</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mother's Name</label>
                    <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($profile['mother_name'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="mother_contact" class="form-control" value="<?= htmlspecialchars($profile['mother_contact'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="mother_address" class="form-control" rows="2"><?= htmlspecialchars($profile['mother_address'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="mother_occupation" class="form-control" value="<?= htmlspecialchars($profile['mother_occupation'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employer</label>
                    <input type="text" name="mother_employer" class="form-control" value="<?= htmlspecialchars($profile['mother_employer'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employer Address</label>
                    <input type="text" name="mother_employer_address" class="form-control" value="<?= htmlspecialchars($profile['mother_employer_address'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Education Level</label>
                    <input type="text" name="mother_education" class="form-control" value="<?= htmlspecialchars($profile['mother_education'] ?? '') ?>" placeholder="e.g., College">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Monthly Income</label>
                    <input type="number" name="mother_income" class="form-control" value="<?= htmlspecialchars($profile['mother_income'] ?? '') ?>" step="0.01">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">Other Information</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Legal Guardian (if applicable)</label>
                    <input type="text" name="legal_guardian" class="form-control" value="<?= htmlspecialchars($profile['legal_guardian'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Number of Siblings</label>
                    <input type="number" name="num_siblings" class="form-control" value="<?= htmlspecialchars($profile['num_siblings'] ?? '0') ?>" min="0">
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="profile_setup.php?step=2" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <button type="submit" class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($currentStep == 4): ?>
        <!-- PHASE 4: Financial Information -->
        <h3 class="section-title"><i class="bi bi-cash-stack"></i> Financial Information</h3>
        <form method="POST">
            <input type="hidden" name="step" value="4">
            
            <div class="mb-3">
                <label class="form-label">Total Family Monthly Income <span class="required">*</span></label>
                <input type="number" name="family_monthly_income" class="form-control" value="<?= htmlspecialchars($profile['family_monthly_income'] ?? '') ?>" step="0.01" required>
                <small class="text-muted">Combined income of all family members</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Are you a 4Ps Beneficiary? <span class="required">*</span></label>
                <select name="is_4ps_beneficiary" class="form-select" required>
                    <option value="">Select</option>
                    <option value="Yes" <?= ($profile['is_4ps_beneficiary'] ?? '') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= ($profile['is_4ps_beneficiary'] ?? '') == 'No' ? 'selected' : '' ?>>No</option>
                </select>
                <small class="text-muted">Pantawid Pamilyang Pilipino Program</small>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> This information helps match you with scholarships that fit your financial need.
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="profile_setup.php?step=3" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <button type="submit" class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($currentStep == 5): ?>
        <!-- PHASE 5: Academic Records -->
        <h3 class="section-title"><i class="bi bi-trophy-fill"></i> Academic Records</h3>
        <form method="POST">
            <input type="hidden" name="step" value="5">
            
            <div class="mb-3">
                <label class="form-label">Latest Weighted Average <span class="required">*</span></label>
                <input type="number" name="gwa" class="form-control" value="<?= htmlspecialchars($profile['gwa'] ?? '') ?>" step="0.01" min="1.0" max="5.0" required>
                <small class="text-muted">Enter your latest weighted average (e.g., 1.75, 2.50)</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Awards and Recognitions</label>
                <textarea name="awards_received" class="form-control" rows="4" placeholder="List any academic awards, honors, or recognitions you've received..."><?= htmlspecialchars($profile['awards_received'] ?? '') ?></textarea>
            </div>

            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>Almost done!</strong> Click "Complete Profile" to finish and start browsing scholarships.
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="profile_setup.php?step=4" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <button type="submit" class="btn btn-primary">Complete Profile <i class="bi bi-check-circle"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($currentStep == 1 && !isset($_POST['step'])): ?>
        </div> <!-- End profileForm div -->
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-fill school name when selecting from dropdown
document.getElementById('schoolSelect')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (this.value) {
        document.getElementById('schoolNameInput').value = selectedOption.text;
    }
});

// Function to start profile setup (show the form)
function startProfileSetup() {
    document.querySelector('.introduction-section').style.display = 'none';
    document.getElementById('profileForm').style.display = 'block';
}

// Show form immediately if returning to step 1 after submission
<?php if ($currentStep == 1 && isset($_POST['step'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.introduction-section')) {
        document.querySelector('.introduction-section').style.display = 'none';
        document.getElementById('profileForm').style.display = 'block';
    }
});
<?php endif; ?>
</script>
</body>
</html>
