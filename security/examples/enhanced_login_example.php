<?php
/**
 * Enhanced Login Example
 * Shows how to integrate security with existing email authentication
 * WITHOUT breaking the current system
 */

session_start();

// Include existing requirements
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/core/CSRFToken.php';
require_once __DIR__ . '/../../app/core/Mailer.php';

// NEW: Include security integration
require_once __DIR__ . '/../SecurityIntegration.php';

// Existing redirect logic (PRESERVED)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $user_type = $_SESSION['user_type'];

    if ($user_type === 'student') {
        header("Location: app/views/student_home.php");
        exit();
    } elseif ($user_type === 'donor' || $user_type === 'foundation' || $user_type === 'provider') {
        header("Location: app/views/provider/dashboard.php");
        exit();
    } elseif ($user_type === 'admin') {
        header("Location: app/views/admin/dashboard.php");
        exit();
    }
}

// Existing session cleanup (PRESERVED)
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_type'])) {
    session_unset();
    session_destroy();
}

// Initialize messages (PRESERVED)
$login_error = '';
$register_error = '';
$register_success = false;
$verify_message = '';
$verify_type = '';

// Existing verification logic (PRESERVED - NO CHANGES)
$verification_success = isset($_SESSION['verification_success']) ? $_SESSION['verification_success'] : '';

if (isset($_GET['action']) && $_GET['action'] === 'verify' && isset($_GET['token'])) {
    // ... existing verification code unchanged ...
}

// ENHANCED LOGIN HANDLER (PRESERVES EXISTING EMAIL AUTH)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Existing CSRF verification (PRESERVED)
    if (!CSRFToken::verify($_POST['_csrf_token'] ?? '')) {
        $login_error = "Invalid security token. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // NEW: Pre-login security check (ADDED - NON-BREAKING)
        $securityCheck = SecurityHelper::checkLogin($email, $password);
        if (!$securityCheck['allowed']) {
            $login_error = $securityCheck['message'];
        } else {
            // EXISTING VALIDATION (PRESERVED)
            if (empty($email) || empty($password)) {
                $login_error = "Email and password are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $login_error = "Please enter a valid email address.";
            } else {
                // EXISTING DATABASE LOGIC (PRESERVED)
                $database = new Database();
                $conn = $database->connect();
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // EXISTING EMAIL VERIFICATION CHECK (PRESERVED)
                    if (!$user['is_verified']) {
                        $login_error = "Please verify your email address before logging in.";
                        
                        // NEW: Log failed attempt due to unverified email
                        SecurityHelper::loginFailure($email, "Email not verified");
                    } else {
                        // EXISTING SESSION CREATION (PRESERVED)
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['name'] = $user['fullname'];
                        $_SESSION['email'] = $user['email'];

                        // NEW: Post-login security enhancements (ADDED - NON-BREAKING)
                        $securityResult = SecurityHelper::loginSuccess($user, $email);
                        
                        // NEW: Check for admin password rotation requirement
                        if (isset($securityResult['password_rotation_required'])) {
                            $_SESSION['password_rotation_required'] = true;
                            $_SESSION['password_rotation_message'] = "Your password has expired. Please change it immediately.";
                        } elseif (isset($securityResult['password_rotation_warning'])) {
                            $_SESSION['password_rotation_warning'] = "Your password expires in " . $securityResult['days_remaining'] . " days.";
                        }

                        // EXISTING SUCCESS MESSAGE (PRESERVED)
                        $_SESSION['login_success'] = "Login successful! Welcome back, " . htmlspecialchars($user['fullname']) . ".";

                        // EXISTING REDIRECT LOGIC (PRESERVED)
                        if ($user['user_type'] === 'student') {
                            header("Location: app/views/student_home.php");
                        } elseif ($user['user_type'] === 'donor' || $user['user_type'] === 'foundation' || $user['user_type'] === 'provider') {
                            header("Location: app/views/provider/dashboard.php");
                        } elseif ($user['user_type'] === 'admin') {
                            header("Location: app/views/admin/dashboard.php");
                        } else {
                            session_destroy();
                            header("Location: index.php");
                        }
                        exit();
                    }
                } else {
                    // NEW: Log failed login attempt
                    SecurityHelper::loginFailure($email, $user ? "Incorrect password" : "No account found");
                    
                    // EXISTING ERROR MESSAGE (PRESERVED)
                    $login_error = $user ? "Incorrect password." : "No account found with that email.";
                }
            }
        }
    }
}

// ENHANCED REGISTRATION HANDLER (PRESERVES EXISTING)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Existing CSRF verification (PRESERVED)
    if (!CSRFToken::verify($_POST['_csrf_token'] ?? '')) {
        $register_error = "Invalid security token. Please try again.";
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        $user_type = $_POST['user_type'] ?? '';

        // EXISTING BASIC VALIDATION (PRESERVED)
        if (empty($fullname) || empty($email) || empty($password) || empty($confirm) || empty($user_type)) {
            $register_error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $register_error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm) {
            $register_error = "Passwords do not match.";
        } elseif (!in_array($user_type, ['student', 'provider'])) {
            $register_error = "Invalid user type selected.";
        } else {
            // NEW: Enhanced password validation (ADDED - NON-BREAKING)
            $passwordValidation = SecurityHelper::validatePassword($password);
            if (!$passwordValidation['is_valid']) {
                $register_error = "Password strength requirements: " . implode('. ', $passwordValidation['errors']);
            } else {
                // EXISTING DATABASE LOGIC (PRESERVED)
                $database = new Database();
                $conn = $database->connect();
                
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                
                if ($check->rowCount() > 0) {
                    $register_error = "Email already exists. Please use another email.";
                } else {
                    // EXISTING USER CREATION (PRESERVED)
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, user_type) VALUES (?, ?, ?, ?)");

                    if ($stmt->execute([$fullname, $email, $hash, $user_type])) {
                        // EXISTING EMAIL VERIFICATION (PRESERVED)
                        $user_id = $conn->lastInsertId();
                        $token = bin2hex(random_bytes(32));
                        
                        $tokenStmt = $conn->prepare("INSERT INTO email_verifications (user_id, token) VALUES (?, ?)");
                        $tokenStmt->execute([$user_id, $token]);
                        
                        // EXISTING EMAIL SENDING (PRESERVED)
                        try {
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            $path = dirname($_SERVER['PHP_SELF']);
                            $verifyLink = "{$protocol}://{$host}{$path}/index.php?action=verify&token={$token}";
                            
                            // ... existing email body and sending code ...
                            
                            Mailer::send($email, "ISKOLar - Verify Your Email", $emailBody);
                        } catch (Exception $e) {
                            error_log("Failed to send verification email: " . $e->getMessage());
                        }

                        // EXISTING SUCCESS HANDLING (PRESERVED)
                        $register_success = true;
                        $fullname = $email = '';
                        $user_type = '';
                    } else {
                        $register_error = "An error occurred during registration. Please try again.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- EXISTING HEAD CONTENT (PRESERVED) -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISKOLar – Empowering Education</title>
    <!-- ... existing CSS links ... -->
    
    <!-- NEW: Security-related JavaScript (ADDED - NON-BREAKING) -->
    <script>
    // Password strength indicator
    function checkPasswordStrength(password) {
        const strengthMeter = document.getElementById('password-strength');
        if (!strengthMeter) return;
        
        let score = 0;
        let feedback = [];
        
        if (password.length >= 8) score += 25;
        else feedback.push('At least 8 characters');
        
        if (/[a-z]/.test(password)) score += 15;
        else feedback.push('Lowercase letter');
        
        if (/[A-Z]/.test(password)) score += 15;
        else feedback.push('Uppercase letter');
        
        if (/[0-9]/.test(password)) score += 15;
        else feedback.push('Number');
        
        if (/[^A-Za-z0-9]/.test(password)) score += 20;
        else feedback.push('Special character');
        
        // Update strength meter
        strengthMeter.style.width = score + '%';
        strengthMeter.className = 'progress-bar ';
        
        if (score < 50) {
            strengthMeter.className += 'bg-danger';
            strengthMeter.textContent = 'Weak';
        } else if (score < 80) {
            strengthMeter.className += 'bg-warning';
            strengthMeter.textContent = 'Medium';
        } else {
            strengthMeter.className += 'bg-success';
            strengthMeter.textContent = 'Strong';
        }
        
        // Update feedback
        const feedbackEl = document.getElementById('password-feedback');
        if (feedbackEl) {
            feedbackEl.innerHTML = feedback.length > 0 ? 
                'Missing: ' + feedback.join(', ') : 
                'Password meets all requirements';
        }
    }
    
    // Session timeout warning
    function initSessionTimeout() {
        <?php if (isset($_SESSION['session_expires_at'])): ?>
        const expiresAt = <?php echo $_SESSION['session_expires_at'] * 1000; ?>;
        const warningTime = 5 * 60 * 1000; // 5 minutes before expiry
        
        function checkSessionTimeout() {
            const now = new Date().getTime();
            const timeLeft = expiresAt - now;
            
            if (timeLeft <= warningTime && timeLeft > 0) {
                if (confirm('Your session will expire in 5 minutes. Do you want to extend it?')) {
                    // Extend session via AJAX
                    fetch('extend_session.php', {method: 'POST'})
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            }
                        });
                }
            } else if (timeLeft <= 0) {
                alert('Your session has expired. Please log in again.');
                window.location.href = 'index.php';
            }
        }
        
        setInterval(checkSessionTimeout, 60000); // Check every minute
        <?php endif; ?>
    }
    </script>
</head>

<body>
    <!-- EXISTING NAVBAR (PRESERVED) -->
    <!-- ... existing navbar code ... -->

    <!-- EXISTING HERO SECTION (PRESERVED) -->
    <!-- ... existing hero code ... -->

    <!-- EXISTING ABOUT SECTION (PRESERVED) -->
    <!-- ... existing about code ... -->

    <!-- ENHANCED REGISTER MODAL (PRESERVES EXISTING + ADDS SECURITY) -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Your Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <!-- EXISTING FORM FIELDS (PRESERVED) -->
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname ?? ''); ?>" required>

                        <label class="form-label mt-3">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>

                        <label class="form-label mt-3">Password</label>
                        <input type="password" name="password" class="form-control" required 
                               onkeyup="checkPasswordStrength(this.value)">
                        
                        <!-- NEW: Password strength indicator (ADDED - NON-BREAKING) -->
                        <div class="mt-2">
                            <div class="progress" style="height: 5px;">
                                <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="password-feedback" class="text-muted"></small>
                        </div>

                        <label class="form-label mt-3">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>

                        <!-- EXISTING USER TYPE SELECTION (PRESERVED) -->
                        <label class="form-label mt-3">Account Type</label>
                        <select name="user_type" class="form-select" required>
                            <option value="" disabled selected>Select your role</option>
                            <option value="student">Student</option>
                            <option value="provider">Scholarship Provider/Donor</option>
                        </select>
                        <small class="text-muted">Note: Admin accounts are created by the system</small>

                        <!-- EXISTING LOGIN LINK (PRESERVED) -->
                        <div class="text-center mt-3">
                            <small>Already have an account?
                                <a href="#" id="openLoginFromRegister" class="text-primary fw-semibold text-decoration-none">Log in</a>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <?php echo CSRFToken::getInput(); ?>
                        <button type="submit" name="register" class="btn btn-primary w-100 py-2">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EXISTING LOGIN MODAL (PRESERVED) -->
    <!-- ... existing login modal code ... -->

    <!-- EXISTING EMAIL VERIFICATION MODAL (PRESERVED) -->
    <!-- ... existing verification modal code ... -->

    <!-- NEW: Security Alert Modal (ADDED - NON-BREAKING) -->
    <?php if (isset($_SESSION['password_rotation_required'])): ?>
    <div class="modal fade" id="passwordRotationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Password Rotation Required</h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="mb-3"><?php echo htmlspecialchars($_SESSION['password_rotation_message']); ?></p>
                    <div class="d-grid gap-2">
                        <a href="change_password.php" class="btn btn-warning">Change Password Now</a>
                        <button type="button" class="btn btn-outline-secondary" onclick="remindLater()">Remind Me Later</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['password_rotation_required'], $_SESSION['password_rotation_message']); endif; ?>

    <!-- EXISTING FOOTER (PRESERVED) -->
    <!-- ... existing footer code ... -->

    <!-- EXISTING TOAST CONTAINER (PRESERVED) -->
    <!-- ... existing toast code ... -->

    <!-- EXISTING SCRIPTS (PRESERVED) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // EXISTING JAVASCRIPT (PRESERVED)
        // ... existing navbar, modal, toast code ...
        
        // NEW: Initialize security features (ADDED - NON-BREAKING)
        initSessionTimeout();
        
        // Show password rotation modal if required
        <?php if (isset($_SESSION['password_rotation_required'])): ?>
        const passwordRotationModal = new bootstrap.Modal(document.getElementById('passwordRotationModal'));
        passwordRotationModal.show();
        <?php endif; ?>
        
        // Show password rotation warning
        <?php if (isset($_SESSION['password_rotation_warning'])): ?>
        const warningToast = document.createElement('div');
        warningToast.className = 'toast align-items-center text-bg-warning border-0';
        warningToast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"><?php echo htmlspecialchars($_SESSION['password_rotation_warning']); ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.querySelector('.toast-container').appendChild(warningToast);
        const toast = new bootstrap.Toast(warningToast, { delay: 10000 });
        toast.show();
        <?php unset($_SESSION['password_rotation_warning']); ?>
        <?php endif; ?>
    });
    
    // NEW: Security helper functions (ADDED - NON-BREAKING)
    function remindLater() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('passwordRotationModal'));
        modal.hide();
    }
    </script>
</body>
</html>