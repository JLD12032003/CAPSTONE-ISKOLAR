<?php
session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../models/ProviderProfile.php';
require_once __DIR__ . '/../../controllers/PartnershipController.php';

$profileModel = new ProviderProfile();
$partnershipController = new PartnershipController();

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
    $result = $partnershipController->submitRequest();
    
    if ($result['success']) {
        $message = $result['message'];
        $_POST = []; // Clear form
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Request - ISKOLar</title>
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

    .partnership-container {
        max-width: 1000px;
        margin: 100px auto 50px;
        padding: 20px;
    }

    .partnership-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .partnership-header {
        background: linear-gradient(135deg, var(--dark), var(--primary));
        color: white;
        padding: 40px;
        text-align: center;
    }

    .partnership-body {
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

    .workflow-steps {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .step {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .step-number {
        background: var(--primary);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 15px;
    }

    .file-upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .file-upload-area:hover {
        border-color: var(--primary);
        background: #f8f9ff;
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

<div class="partnership-container">
    <div class="partnership-card">
        <div class="partnership-header">
            <h1><i class="bi bi-handshake"></i> Partnership Request</h1>
            <p>Submit a formal partnership proposal to collaborate with educational institutions</p>
        </div>

        <div class="partnership-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message); ?>
                    <div class="mt-2">
                        <a href="partnership_status.php" class="btn btn-sm btn-success">View Request Status</a>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-success">Go to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Approval Workflow Information -->
            <div class="workflow-steps">
                <h4 class="section-title">📋 Approval Workflow Process</h4>
                <p class="text-muted mb-4">Your partnership request will go through a strict sequential approval process:</p>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Scholarship Committee Review</strong><br>
                        <small class="text-muted">Initial review by the school's scholarship committee</small>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Vice President Approval</strong><br>
                        <small class="text-muted">VP review and approval (only if committee approves)</small>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div>
                        <strong>President Final Approval</strong><br>
                        <small class="text-muted">Final approval by school president (only if VP approves)</small>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Important:</strong> Each stage must approve before proceeding to the next. 
                    Rejection at any stage terminates the process. Each approver receives a secure email with approval links.
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <!-- Organization Information -->
                <div class="mb-4">
                    <h4 class="section-title">Organization Information</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization Name *</label>
                            <input type="text" name="organization_name" class="form-control" 
                                   value="<?= htmlspecialchars($profile['organization_name']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization Type *</label>
                            <input type="text" name="organization_type" class="form-control" 
                                   value="<?= htmlspecialchars($profile['organization_type']); ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person *</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['contact_person'] ?? $profile['contact_person'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Email *</label>
                            <input type="email" name="contact_email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['contact_email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" name="contact_phone" class="form-control" 
                               value="<?= htmlspecialchars($_POST['contact_phone'] ?? $profile['contact_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Partnership Details -->
                <div class="mb-4">
                    <h4 class="section-title">Partnership Proposal</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Target School *</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id']; ?>" 
                                        <?= ($_POST['school_id'] ?? '') == $school['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($school['school_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partnership Title *</label>
                        <input type="text" name="partnership_title" class="form-control" 
                               value="<?= htmlspecialchars($_POST['partnership_title'] ?? ''); ?>" 
                               placeholder="e.g., Academic Excellence Scholarship Partnership 2024" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partnership Description *</label>
                        <textarea name="partnership_description" class="form-control" rows="4" 
                                  placeholder="Describe your partnership proposal, goals, and expected outcomes" required><?= htmlspecialchars($_POST['partnership_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Proposed Scholarship Amount (₱) *</label>
                            <input type="number" name="proposed_scholarship_amount" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['proposed_scholarship_amount'] ?? ''); ?>" 
                                   min="1" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Number of Scholarship Slots *</label>
                            <input type="number" name="proposed_scholarship_slots" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['proposed_scholarship_slots'] ?? ''); ?>" 
                                   min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Partnership Duration (Years)</label>
                            <select name="partnership_duration_years" class="form-select">
                                <option value="1" <?= ($_POST['partnership_duration_years'] ?? '1') == '1' ? 'selected' : ''; ?>>1 Year</option>
                                <option value="2" <?= ($_POST['partnership_duration_years'] ?? '') == '2' ? 'selected' : ''; ?>>2 Years</option>
                                <option value="3" <?= ($_POST['partnership_duration_years'] ?? '') == '3' ? 'selected' : ''; ?>>3 Years</option>
                                <option value="5" <?= ($_POST['partnership_duration_years'] ?? '') == '5' ? 'selected' : ''; ?>>5 Years</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Supporting Documents -->
                <div class="mb-4">
                    <h4 class="section-title">Supporting Documents</h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Registration Documents</label>
                        <div class="file-upload-area">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-2">Upload organization registration documents</p>
                            <input type="file" name="registration_documents[]" class="form-control" 
                                   multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB each)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Financial Statements</label>
                        <div class="file-upload-area">
                            <i class="bi bi-file-earmark-text" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-2">Upload recent financial statements or proof of funds</p>
                            <input type="file" name="financial_statements[]" class="form-control" 
                                   multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB each)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Partnership Proposal Document</label>
                        <div class="file-upload-area">
                            <i class="bi bi-file-earmark-pdf" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-2">Upload detailed partnership proposal (optional)</p>
                            <input type="file" name="partnership_proposal" class="form-control" 
                                   accept=".pdf,.doc,.docx">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 10MB)</small>
                        </div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="mb-4">
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Important Terms</h6>
                        <ul class="mb-0">
                            <li>Partnership requests undergo a strict sequential approval process</li>
                            <li>Each approval stage has a 7-day response window</li>
                            <li>Rejection at any stage terminates the entire process</li>
                            <li>Approved partnerships are binding for the specified duration</li>
                            <li>All scholarship programs must comply with school policies</li>
                        </ul>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the terms and conditions and understand the approval process *
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Submit Partnership Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// File upload preview
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const fileCount = this.files.length;
        const uploadArea = this.closest('.file-upload-area');
        const existingInfo = uploadArea.querySelector('.file-info');
        
        if (existingInfo) {
            existingInfo.remove();
        }
        
        if (fileCount > 0) {
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info mt-2';
            fileInfo.innerHTML = `<small class="text-success"><i class="bi bi-check-circle"></i> ${fileCount} file(s) selected</small>`;
            uploadArea.appendChild(fileInfo);
        }
    });
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = ['contact_person', 'contact_email', 'school_id', 'partnership_title', 'partnership_description', 'proposed_scholarship_amount', 'proposed_scholarship_slots'];
    
    for (let field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            e.preventDefault();
            alert(`Please fill in the ${field.replace('_', ' ')} field.`);
            input.focus();
            return;
        }
    }
    
    const terms = document.getElementById('terms');
    if (!terms.checked) {
        e.preventDefault();
        alert('Please agree to the terms and conditions.');
        terms.focus();
        return;
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