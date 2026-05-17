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
require_once __DIR__ . '/../../controllers/ScholarshipWorkflowController.php';

$scholarshipModel = new Scholarship();
$profileModel = new ProviderProfile();
$workflowController = new ScholarshipWorkflowController();

// Check if provider has completed profile
$profile = $profileModel->getProfile($_SESSION['user_id']);
if (!$profile) {
    header("Location: profile_setup.php");
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
    // Process eligible courses (checkbox array)
    $eligibleCourses = '';
    if (isset($_POST['eligible_courses']) && is_array($_POST['eligible_courses'])) {
        $eligibleCourses = implode(',', $_POST['eligible_courses']);
    }
    
    // Process additional requirements (checkbox array)
    $additionalRequirements = [];
    if (isset($_POST['additional_requirements']) && is_array($_POST['additional_requirements'])) {
        $additionalRequirements = $_POST['additional_requirements'];
        // Add custom requirement if "Other" is selected and custom text is provided
        if (in_array('Other', $additionalRequirements) && !empty($_POST['custom_requirement'])) {
            $additionalRequirements[] = trim($_POST['custom_requirement']);
        }
        // Remove "Other" from the array since we added the custom text
        $additionalRequirements = array_filter($additionalRequirements, function($req) {
            return $req !== 'Other';
        });
    }
    $otherRequirements = implode(',', $additionalRequirements);
    
    $data = [
        'provider_id' => $_SESSION['user_id'],
        'school_id' => !empty($_POST['school_id']) ? $_POST['school_id'] : null,
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'scholarship_type' => $_POST['scholarship_type'],
        'amount' => floatval($_POST['amount']),
        'slots' => intval($_POST['slots']),
        'eligible_courses' => $eligibleCourses,
        'min_gwa' => !empty($_POST['min_gwa']) ? floatval($_POST['min_gwa']) : null,
        'max_family_income' => !empty($_POST['max_family_income']) ? floatval($_POST['max_family_income']) : null,
        'year_levels' => implode(',', $_POST['year_levels'] ?? []),
        'other_requirements' => $otherRequirements,
        'partnership_letter' => trim($_POST['partnership_letter']),
        'application_start' => $_POST['application_start'],
        'application_end' => $_POST['application_end'],
        'status' => $_POST['status'],
        'workflow_status' => 'DRAFT' // Always start as draft
    ];

    // Validation
    if (empty($data['title']) || empty($data['description']) || empty($data['scholarship_type']) || 
        empty($data['partnership_letter']) || $data['amount'] <= 0 || $data['slots'] <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } elseif (strtotime($data['application_start']) >= strtotime($data['application_end'])) {
        $error = "Application end date must be after start date.";
    } elseif (isset($_POST['submit_action']) && $_POST['submit_action'] === 'submit_for_approval' && empty($data['school_id'])) {
        $error = "Please select a partner school before submitting for approval.";
    } else {
        try {
            $scholarshipId = $scholarshipModel->createScholarship($data);
            if ($scholarshipId) {
                // Check if user wants to submit for approval workflow
                if (isset($_POST['submit_action']) && $_POST['submit_action'] === 'submit_for_approval' && !empty($data['school_id'])) {
                    try {
                        // Set the scholarship ID for the workflow controller
                        $_POST['scholarship_id'] = $scholarshipId;
                        $workflowResult = $workflowController->submitForApproval();
                        if ($workflowResult['success']) {
                            $message = "🎉 Scholarship created and submitted for approval workflow! The school administrator will receive an email to begin the approval process.";
                        } else {
                            $message = "✅ Scholarship created successfully, but there was an issue with the approval workflow: " . $workflowResult['message'];
                        }
                    } catch (Exception $e) {
                        $message = "✅ Scholarship created successfully, but workflow submission failed: " . $e->getMessage();
                    }
                } else {
                    $message = "✅ Scholarship created successfully as a draft! You can edit it or submit for approval later.";
                }
                
                // Redirect to prevent form resubmission
                $redirectUrl = "scholarships.php?message=" . urlencode($message);
                header("Location: $redirectUrl");
                exit();
            } else {
                $error = "Failed to create scholarship. Please try again.";
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
    <title>Create Scholarship - ISKOLar</title>
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
            <h1><i class="bi bi-plus-circle"></i> Create New Scholarship</h1>
            <p>Launch a scholarship program to empower deserving students</p>
        </div>

        <div class="scholarship-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
                    <div class="mt-2">
                        <a href="dashboard.php" class="btn btn-sm btn-success">Go to Dashboard</a>
                        <a href="scholarships.php" class="btn btn-sm btn-outline-success">View My Scholarships</a>
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
                               value="<?= htmlspecialchars($_POST['title'] ?? ''); ?>" 
                               placeholder="e.g., Academic Excellence Scholarship 2024" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Describe the scholarship program, its purpose, and what makes it special" required><?= htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Scholarship Type *</label>
                            <select name="scholarship_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Full" <?= ($_POST['scholarship_type'] ?? '') == 'Full' ? 'selected' : ''; ?>>Full Scholarship</option>
                                <option value="Partial" <?= ($_POST['scholarship_type'] ?? '') == 'Partial' ? 'selected' : ''; ?>>Partial Scholarship</option>
                                <option value="Book Allowance" <?= ($_POST['scholarship_type'] ?? '') == 'Book Allowance' ? 'selected' : ''; ?>>Book Allowance</option>
                                <option value="Tuition Only" <?= ($_POST['scholarship_type'] ?? '') == 'Tuition Only' ? 'selected' : ''; ?>>Tuition Only</option>
                                <option value="Living Allowance" <?= ($_POST['scholarship_type'] ?? '') == 'Living Allowance' ? 'selected' : ''; ?>>Living Allowance</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount (₱) *</label>
                            <input type="number" name="amount" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                   min="1" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Number of Slots *</label>
                            <input type="number" name="slots" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['slots'] ?? ''); ?>" 
                                   min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partner School (Optional)</label>
                        <select name="school_id" class="form-select">
                            <option value="">Select School (Optional)</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id']; ?>" 
                                        <?= ($_POST['school_id'] ?? '') == $school['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($school['school_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave blank if scholarship is open to all schools</small>
                    </div>
                </div>

                <!-- Eligibility Criteria -->
                <div class="mb-4">
                    <h4 class="section-title">Eligibility Criteria</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Eligible Courses</label>
                        <div class="row">
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
                            $selectedCourses = isset($_POST['eligible_courses']) ? $_POST['eligible_courses'] : [];
                            ?>
                            <?php foreach ($courses as $course): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="eligible_courses[]" 
                                               value="<?= $course; ?>" id="course_<?= str_replace(' ', '_', $course); ?>"
                                               <?= in_array($course, $selectedCourses) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="course_<?= str_replace(' ', '_', $course); ?>">
                                            <?= $course; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Select all courses that are eligible for this scholarship. Leave unchecked if open to all courses.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum GWA</label>
                            <input type="number" name="min_gwa" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['min_gwa'] ?? ''); ?>" 
                                   min="1.0" max="5.0" step="0.01" 
                                   placeholder="e.g., 2.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Family Income (₱)</label>
                            <input type="number" name="max_family_income" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['max_family_income'] ?? ''); ?>" 
                                   min="0" step="0.01" 
                                   placeholder="e.g., 50000">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Eligible Year Levels</label>
                        <div class="row">
                            <?php 
                            $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
                            $selectedYears = $_POST['year_levels'] ?? [];
                            ?>
                            <?php foreach ($yearLevels as $year): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_levels[]" 
                                               value="<?= $year; ?>" id="year_<?= str_replace(' ', '_', $year); ?>"
                                               <?= in_array($year, $selectedYears) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="year_<?= str_replace(' ', '_', $year); ?>">
                                            <?= $year; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Select eligible year levels. Leave unchecked if open to all year levels.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Requirements</label>
                        <div class="row">
                            <?php 
                            $requirements = [
                                'Good Moral Character Certificate',
                                'Certificate of Indigency',
                                'Academic Transcript',
                                'Letter of Recommendation',
                                'Essay/Personal Statement',
                                'Community Service Record',
                                'Medical Certificate',
                                'Parent/Guardian Income Certificate',
                                'Birth Certificate',
                                'Valid ID Copy'
                            ];
                            $selectedRequirements = isset($_POST['additional_requirements']) ? $_POST['additional_requirements'] : [];
                            ?>
                            <?php foreach ($requirements as $requirement): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="additional_requirements[]" 
                                               value="<?= $requirement; ?>" id="req_<?= str_replace([' ', '/', '-'], '_', $requirement); ?>"
                                               <?= in_array($requirement, $selectedRequirements) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="req_<?= str_replace([' ', '/', '-'], '_', $requirement); ?>">
                                            <?= $requirement; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="additional_requirements[]" 
                                           value="Other" id="req_other" onchange="toggleCustomRequirement()"
                                           <?= in_array('Other', $selectedRequirements) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="req_other">
                                        Other (specify below)
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="customRequirementDiv" style="display: <?= in_array('Other', $selectedRequirements) ? 'block' : 'none'; ?>;" class="mt-3">
                            <label class="form-label">Specify Other Requirement</label>
                            <textarea name="custom_requirement" class="form-control" rows="2" 
                                      placeholder="Please specify the additional requirement..."><?= htmlspecialchars($_POST['custom_requirement'] ?? ''); ?></textarea>
                        </div>
                        
                        <small class="text-muted">Select all documents and requirements needed for application. Students will need to provide these during application.</small>
                    </div>
                </div>

                <!-- Partnership Letter -->
                <div class="mb-4">
                    <h4 class="section-title">Partnership Request Letter</h4>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Partnership Request</h6>
                        <p class="mb-0">This letter will be sent to the school administration requesting partnership for your scholarship program.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Letter Content *</label>
                        <textarea name="partnership_letter" class="form-control" rows="8" required 
                                  placeholder="Write your partnership request letter here..."><?= htmlspecialchars($_POST['partnership_letter'] ?? ''); ?></textarea>
                        <small class="text-muted">This letter should explain your organization, the scholarship program, and your request for partnership with the school.</small>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateSampleLetter()">
                            <i class="bi bi-file-text"></i> Generate Sample Letter
                        </button>
                        <small class="text-muted ms-2">Click to auto-generate a professional partnership letter template</small>
                    </div>
                </div>

                <!-- Application Period -->
                <div class="mb-4">
                    <h4 class="section-title">Application Period</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Application Start Date *</label>
                            <input type="date" name="application_start" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['application_start'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Application End Date *</label>
                            <input type="date" name="application_end" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['application_end'] ?? date('Y-m-d', strtotime('+30 days'))); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <h4 class="section-title">Publication & Approval</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="Draft" <?= ($_POST['status'] ?? 'Draft') == 'Draft' ? 'selected' : ''; ?>>Draft (Not visible to students)</option>
                        </select>
                        <small class="text-muted">Scholarships start as drafts. Submit for approval to make them visible to students.</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Approval Workflow</h6>
                        <p class="mb-2">When you submit a scholarship for approval, it goes through a strict multi-level approval process:</p>
                        <ol class="mb-2">
                            <li><strong>School Administrator</strong> - Initial review</li>
                            <li><strong>Scholarship Committee</strong> - Evaluation and vote</li>
                            <li><strong>Vice President</strong> - Review and approval</li>
                            <li><strong>President</strong> - Final approval</li>
                        </ol>
                        <p class="mb-0"><strong>Note:</strong> All levels must approve before the scholarship becomes visible to students. You'll receive email notifications about the progress.</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <div>
                        <button type="submit" name="submit_action" value="save_draft" class="btn btn-outline-primary me-2">
                            <i class="bi bi-save"></i> Save as Draft
                        </button>
                        <button type="submit" name="submit_action" value="submit_for_approval" class="btn btn-primary" 
                                onclick="return confirmSubmitForApproval()">
                            <i class="bi bi-send"></i> Submit for Approval
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Set minimum date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="application_start"]').setAttribute('min', today);
    document.querySelector('input[name="application_end"]').setAttribute('min', today);
    
    // Update end date minimum when start date changes
    document.querySelector('input[name="application_start"]').addEventListener('change', function() {
        document.querySelector('input[name="application_end"]').setAttribute('min', this.value);
    });
});

function confirmSubmitForApproval() {
    const schoolSelect = document.querySelector('select[name="school_id"]');
    if (!schoolSelect.value) {
        alert('Please select a partner school before submitting for approval.');
        schoolSelect.focus();
        return false;
    }
    
    return confirm('Are you sure you want to submit this scholarship for approval? Once submitted, it will go through a multi-level approval process and cannot be edited until the process is complete.');
}

function toggleCustomRequirement() {
    const otherCheckbox = document.getElementById('req_other');
    const customDiv = document.getElementById('customRequirementDiv');
    const customTextarea = document.querySelector('textarea[name="custom_requirement"]');
    
    if (otherCheckbox.checked) {
        customDiv.style.display = 'block';
        customTextarea.focus();
    } else {
        customDiv.style.display = 'none';
        customTextarea.value = '';
    }
}

function generateSampleLetter() {
    const organizationName = "<?= htmlspecialchars($profile['organization_name'] ?? 'Your Organization'); ?>";
    const contactPerson = "<?= htmlspecialchars($profile['contact_person'] ?? 'Contact Person'); ?>";
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    const sampleLetter = `${today}

Dear School Administrator,

Greetings!

I am writing on behalf of ${organizationName} to formally request a partnership with your esteemed institution for our scholarship program.

ABOUT OUR ORGANIZATION:
${organizationName} is committed to supporting education and empowering deserving students to achieve their academic goals. We believe that education is the key to building a better future for our community.

SCHOLARSHIP PROGRAM DETAILS:
We are pleased to offer a scholarship program that will provide financial assistance to qualified students in your institution. This partnership will help reduce the financial burden on students and their families while promoting academic excellence.

PARTNERSHIP BENEFITS:
• Financial support for deserving students
• Recognition and awards for academic achievers
• Long-term educational partnership
• Community development through education

We are confident that this partnership will be mutually beneficial and will contribute to the academic success of your students. We look forward to working together to make quality education more accessible.

We would be honored to discuss this partnership opportunity further at your convenience. Please feel free to contact us for any additional information or clarification.

Thank you for your time and consideration.

Respectfully yours,

${contactPerson}
${organizationName}

---
This letter serves as our formal request for partnership and our commitment to supporting your students' educational journey.`;

    document.querySelector('textarea[name="partnership_letter"]').value = sampleLetter;
}

// Initialize custom requirement visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomRequirement();
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