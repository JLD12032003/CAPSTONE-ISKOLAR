<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/core/CSRFToken.php';
require_once __DIR__ . '/app/core/Mailer.php';

session_start();

// Password policy validation function
function validatePasswordPolicy($password) {
    // At least 8 characters
    if (strlen($password) < 8) return false;
    
    // Contains uppercase letter
    if (!preg_match('/[A-Z]/', $password)) return false;
    
    // Contains lowercase letter
    if (!preg_match('/[a-z]/', $password)) return false;
    
    // Contains number
    if (!preg_match('/[0-9]/', $password)) return false;
    
    // Contains special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) return false;
    
    return true;
}

// Redirect if user is already logged in and has a valid type
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

// If session exists but user_type missing, clear it to prevent redirect loop
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_type'])) {
    session_unset();
    session_destroy();
}

// Check for logout messages
$logout_success = isset($_GET['logout']) && $_GET['logout'] === 'success';
$logout_timeout = isset($_GET['logout']) && $_GET['logout'] === 'timeout';

// Initialize messages
$login_error = '';
$register_error = '';
$register_success = false;
$verify_message = '';
$verify_type = ''; // 'success' or 'error'

// Check for verification success message from redirect
$verification_success = isset($_SESSION['verification_success']) ? $_SESSION['verification_success'] : '';

// Check for verification token (email verification link click)
if (isset($_GET['action']) && $_GET['action'] === 'verify' && isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $database = new Database();
    $conn = $database->connect();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM email_verifications WHERE token = ?");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($verification) {
            // Mark user as verified
            $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $updateStmt->execute([$verification['user_id']]);
            
            // Delete the token
            $deleteStmt = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
            $deleteStmt->execute([$verification['id']]);
            
            // Get user info for the success message
            $userStmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
            $userStmt->execute([$verification['user_id']]);
            $verifiedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            // Set success message for modal
            $_SESSION['email_verified'] = true;
            $_SESSION['verified_user_name'] = $verifiedUser['fullname'];
            $_SESSION['verified_user_email'] = $verifiedUser['email'];
            
            header("Location: index.php");
            exit();
        } else {
            $verify_type = 'error';
            $verify_message = "❌ Invalid or expired verification token. Please register again.";
        }
    } catch (Exception $e) {
        $verify_type = 'error';
        $verify_message = "❌ Error verifying email. Please try again.";
    }
}

// --- LOGIN HANDLER (with login success toast support) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Initialize ActivityLogger for login attempt logging
    require_once 'app/core/ActivityLogger.php';
    $logger = new ActivityLogger();
    
    // Verify CSRF token
    if (!CSRFToken::verify($_POST['_csrf_token'] ?? '')) {
        $login_error = "Invalid security token. Please try again.";
        // Log failed attempt - CSRF token invalid
        $logger->logLoginAttempt($_POST['email'] ?? 'unknown', 'FAILED', null, 'Invalid CSRF token');
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // Validate input
        if (empty($email) || empty($password)) {
            $login_error = "Email and password are required.";
            // Log failed attempt - missing credentials
            $logger->logLoginAttempt($email, 'FAILED', null, 'Missing email or password');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $login_error = "Please enter a valid email address.";
            // Log failed attempt - invalid email format
            $logger->logLoginAttempt($email, 'FAILED', null, 'Invalid email format');
        } else {
            // Get database connection
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Check if email is verified
                if (!$user['is_verified']) {
                    $login_error = "Please verify your email address before logging in.";
                    // Log failed attempt - unverified account
                    $logger->logLoginAttempt($email, 'FAILED', $user['id'], 'Account not verified');
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['name'] = $user['fullname'];
                    $_SESSION['email'] = $user['email'];

                    // Initialize session timeout (new feature - doesn't break existing code)
                    require_once 'app/core/SessionTimeout.php';
                    $sessionTimeout = new SessionTimeout();
                    $sessionTimeout->initTimeout($user['user_type']);

                    // Log successful login attempt
                    $logger->logLoginAttempt($email, 'SUCCESS', $user['id'], 'Login successful');
                    
                    // Log system activity for successful login
                    $logger->logSystemActivity(
                        $user['id'], 
                        $user['user_type'], 
                        'LOGIN', 
                        'user_session', 
                        $user['id'], 
                        'User successfully logged in via web interface'
                    );

                    // Add login success message (shown after redirect in next page)
                    $_SESSION['login_success'] = "Login successful! Welcome back, " . htmlspecialchars($user['fullname']) . ".";

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
                $login_error = $user ? "Incorrect password." : "No account found with that email.";
                // Log failed attempt - invalid credentials
                $failureReason = $user ? 'Invalid password' : 'Email not found';
                $logger->logLoginAttempt($email, 'FAILED', $user['id'] ?? null, $failureReason);
            }
        }
    }
}

// --- REGISTRATION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Initialize ActivityLogger for registration logging
    require_once 'app/core/ActivityLogger.php';
    $logger = new ActivityLogger();
    
    // Verify CSRF token
    if (!CSRFToken::verify($_POST['_csrf_token'] ?? '')) {
        $register_error = "Invalid security token. Please try again.";
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        $user_type = $_POST['user_type'] ?? '';

        // Validate input
        if (empty($fullname) || empty($email) || empty($password) || empty($confirm) || empty($user_type)) {
            $register_error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = "Please enter a valid email address.";
        } elseif (!validatePasswordPolicy($password)) {
            $register_error = "Password must be at least 8 characters and contain uppercase, lowercase, number, and special character.";
        } elseif ($password !== $confirm) {
            $register_error = "Passwords do not match.";
        } elseif (!in_array($user_type, ['student', 'provider'])) {
            $register_error = "Invalid user type selected.";
        } else {
            // Get database connection
            $database = new Database();
            $conn = $database->connect();
            
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if ($check->rowCount() > 0) {
                $register_error = "Email already exists. Please use another email.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, user_type) VALUES (?, ?, ?, ?)");

                if ($stmt->execute([$fullname, $email, $hash, $user_type])) {
                    // Generate verification token
                    $user_id = $conn->lastInsertId();
                    $token = bin2hex(random_bytes(32));
                    
                    $tokenStmt = $conn->prepare("INSERT INTO email_verifications (user_id, token) VALUES (?, ?)");
                    $tokenStmt->execute([$user_id, $token]);
                    
                    // Log successful registration
                    $logger->logSystemActivity(
                        $user_id, 
                        $user_type, 
                        'REGISTER', 
                        'user_account', 
                        $user_id, 
                        "New user registered: $fullname ($user_type)"
                    );
                    
                    // Send verification email
                    try {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $path = dirname($_SERVER['PHP_SELF']);
                        $verifyLink = "{$protocol}://{$host}{$path}/index.php?action=verify&token={$token}";
                        
                        $emailBody = "
                        <html>
                        <body style='font-family: Poppins, Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
                            <div style='max-width: 600px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
                                <h2 style='color: #0055ff; margin-bottom: 20px;'>Welcome to ISKOLar! 🎓</h2>
                                <p>Hi <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
                                <p>Thank you for registering as a <strong>" . ucfirst($user_type) . "</strong>. Please verify your email address to activate your account:</p>
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='" . htmlspecialchars($verifyLink) . "' style='background: #0055ff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;'>Verify Email</a>
                                </div>
                                <p>Or copy this link:</p>
                                <p style='background: #f5f5f5; padding: 10px; border-radius: 5px; word-break: break-all; font-size: 12px;'>" . htmlspecialchars($verifyLink) . "</p>";
                        
                        if ($user_type === 'admin') {
                            $emailBody .= "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                                <h4 style='color: #0055ff; margin-bottom: 10px;'>School Administrator Access</h4>
                                <p style='margin: 0;'>As a school administrator, you will have access to:</p>
                                <ul style='margin: 10px 0;'>
                                    <li>Partnership request management</li>
                                    <li>Approval workflow oversight</li>
                                    <li>Student verification and reports</li>
                                    <li>Institutional dashboard</li>
                                </ul>
                            </div>";
                        }
                        
                        $emailBody .= "<p style='color: #999; font-size: 12px; margin-top: 20px;'>This link expires in 24 hours.</p>
                                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='color: #999; font-size: 12px;'>ISKOLar - Empowering Education<br/>Built for Students, Powered by Purpose</p>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        Mailer::send($email, "ISKOLar - Verify Your Email", $emailBody);
                    } catch (Exception $e) {
                        error_log("Failed to send verification email: " . $e->getMessage());
                    }

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ISKOLar – Empowering Education</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
  :root {
    --iskolar-primary: #0055FF;
    --iskolar-secondary: #FDC500;
    --iskolar-dark: #012A4A;
    --iskolar-light: #F8F9FA;
  }

  body {
    font-family: 'Poppins', sans-serif;
    background-color: var(--iskolar-light);
    scroll-behavior: smooth;
  }

  /* Navbar */
  .navbar {
    transition: all 0.4s ease;
    padding: 15px 0;
  }
  .navbar .nav-link {
    color: white !important;
    font-weight: 500;
    margin: 0 10px;
  }
  .navbar.scrolled {
    background-color: white !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }
  .navbar.scrolled .nav-link {
    color: var(--iskolar-dark) !important;
  }
  .btn-register {
    color: white !important;
    border: 2px solid white;
    border-radius: 50px;
    font-weight: 500;
    transition: all 0.3s ease;
    background: transparent;
  }
  .btn-register:hover {
    background: white;
    color: var(--iskolar-primary) !important;
  }
  .navbar.scrolled .btn-register {
    color: var(--iskolar-primary) !important;
    border: 2px solid var(--iskolar-primary);
  }
  .navbar.scrolled .btn-register:hover {
    background: var(--iskolar-primary);
    color: white !important;
  }
  .btn-login {
    background: var(--iskolar-secondary);
    color: var(--iskolar-dark);
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-login:hover {
    background: #e6bf00;
    transform: translateY(-2px);
  }
  .navbar-toggler-icon {
    filter: invert(1);
  }
  .navbar.scrolled .navbar-toggler-icon {
    filter: invert(0);
  }
  .hero {
    height: 100vh;
    background: linear-gradient(rgba(1,42,74,0.85), rgba(0,85,255,0.6)), url('images/BG_1.jpg') center/cover no-repeat;
    background-color: linear-gradient(135deg, var(--iskolar-dark), var(--iskolar-primary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0 20px;
  }
  .hero-content {
    max-width: 700px;
  }
  .hero h1 {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.2;
  }
  .hero .lead {
    font-size: 1.3rem;
    margin-bottom: 30px;
    opacity: 0.95;
  }
  .hero .btn-get-started {
    background: var(--iskolar-secondary);
    color: var(--iskolar-dark);
    border-radius: 50px;
    padding: 12px 40px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
  }
  .hero .btn-get-started:hover {
    background: #e6bf00;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(253, 197, 0, 0.3);
  }
  #foundation {
    background-color: var(--iskolar-light);
    padding: 80px 0;
  }
  .foundation-card {
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    height: 100%;
  }
  .foundation-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 8px 24px rgba(0,85,255,0.15);
  }
  .foundation-card h5 {
    color: var(--iskolar-primary);
    font-weight: 700;
    margin-bottom: 15px;
    font-size: 1.3rem;
  }
  footer {
    background-color: var(--iskolar-dark);
    color: white;
  }
  
  /* Modal Improvements */
  .modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
  }
  .modal-header {
    background: linear-gradient(135deg, var(--iskolar-dark), var(--iskolar-primary));
    color: white;
    border-radius: 12px 12px 0 0;
    border: none;
  }
  .modal-title {
    font-weight: 700;
  }
  .form-label {
    font-weight: 600;
    color: var(--iskolar-dark);
  }
  .form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px 12px;
    transition: all 0.3s ease;
  }
  .form-control:focus, .form-select:focus {
    border-color: var(--iskolar-primary);
    box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--iskolar-dark), var(--iskolar-primary));
    border: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,85,255,0.3);
  }
  .text-primary {
    color: var(--iskolar-primary) !important;
  }
  </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar" style="background: linear-gradient(135deg, var(--iskolar-dark), var(--iskolar-primary));">
  <div class="container">
    <a class="navbar-brand fw-bold text-white d-flex align-items-center" href="#">
  <img src="images/1.png" alt="ISKOLAR Logo" width="40" height="40" class="me-2">ISKOlar
</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#foundation">About</a></li>
        <li class="nav-item ms-lg-3">
          <button class="btn btn-register btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">Register</button>
        </li>
        <li class="nav-item ms-2">
          <button class="btn btn-login btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section id="home" class="hero">
  <div class="hero-content">
    <h1 class="fw-bold mb-3">Empowering <span class="text-warning">Education</span></h1>
    <p class="lead mb-4">Connecting Filipino students with life-changing scholarship opportunities.</p>
    <button class="btn-get-started" id="getStartedBtn">Get Started</button>
  </div>
</section>

<!-- ABOUT -->
<section id="foundation" class="text-center">
  <div class="container">
    <h2 class="fw-bold text-primary mb-3">Our Foundation</h2>
    <p class="text-muted mb-5" style="font-size: 1.1rem;">What drives ISKOLar to support every student's journey</p>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="foundation-card">
          <h5><i class="bi bi-bullseye text-primary"></i> Mission</h5>
          <p>To connect students with scholarships that match their potential and need.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="foundation-card">
          <h5><i class="bi bi-star-fill text-warning"></i> Vision</h5>
          <p>A future where no student is left behind due to financial limitations.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="foundation-card">
          <h5><i class="bi bi-heart-fill text-danger"></i> Core Values</h5>
          <p>Equity, accessibility, empowerment, and community.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- REGISTER MODAL -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Your Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">

          <label class="form-label">Full Name</label>
          <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname ?? ''); ?>" required>

          <label class="form-label mt-3">Email</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>

          <label class="form-label mt-3">Password</label>
          <input type="password" name="password" class="form-control" id="passwordField" required>
          <div class="password-requirements mt-2">
            <small class="text-muted">Password must contain:</small>
            <div class="requirement-list">
              <small id="length-req" class="requirement text-danger">• At least 8 characters</small><br>
              <small id="uppercase-req" class="requirement text-danger">• One uppercase letter (A-Z)</small><br>
              <small id="lowercase-req" class="requirement text-danger">• One lowercase letter (a-z)</small><br>
              <small id="number-req" class="requirement text-danger">• One number (0-9)</small><br>
              <small id="special-req" class="requirement text-danger">• One special character (!@#$%^&*)</small>
            </div>
          </div>

          <label class="form-label mt-3">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>

          <label class="form-label mt-3">Account Type</label>
          <select name="user_type" class="form-select" required>
            <option value="" disabled selected>Select your role</option>
            <option value="student">Student</option>
            <option value="provider">Scholarship Provider/Donor</option>
          </select>

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

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Welcome Back</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
          
          <label class="form-label mt-3">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="modal-footer">
          <?php echo CSRFToken::getInput(); ?>
          <button type="submit" name="login" class="btn btn-primary w-100 py-2">Login</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EMAIL VERIFICATION SUCCESS MODAL -->
<div class="modal fade" id="emailVerifiedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> Email Verified Successfully!</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="mb-4">
          <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
        </div>
        <h4 class="text-success mb-3">Welcome to ISKOLar!</h4>
        <p class="mb-3">
          <strong>Congratulations <?php echo htmlspecialchars($_SESSION['verified_user_name'] ?? ''); ?>!</strong><br>
          Your email <strong><?php echo htmlspecialchars($_SESSION['verified_user_email'] ?? ''); ?></strong> has been successfully verified.
        </p>
        <p class="text-muted mb-4">You can now log in to your account and start exploring scholarship opportunities.</p>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-success btn-lg" onclick="openLoginFromVerification()">
            <i class="bi bi-box-arrow-in-right"></i> Login Now
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer class="footer text-center py-4 mt-auto">
  <small>&copy; 2025 ISKOLar | Built for Students. Powered by Purpose.</small>
</footer>

<!-- TOAST CONTAINER -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">

  <?php if ($login_error): ?>
  <div id="loginToast" class="toast align-items-center text-bg-danger border-0 mb-2">
    <div class="d-flex">
      <div class="toast-body"><?php echo htmlspecialchars($login_error); ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($register_error): ?>
  <div id="registerErrorToast" class="toast align-items-center text-bg-danger border-0 mb-2">
    <div class="d-flex">
      <div class="toast-body"><?php echo htmlspecialchars($register_error); ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($register_error): ?>
  <div id="registerErrorToast" class="toast align-items-center text-bg-danger border-0 mb-2">
    <div class="d-flex">
      <div class="toast-body"><?php echo htmlspecialchars($register_error); ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($logout_success): ?>
  <div id="logoutSuccessToast" class="toast align-items-center text-bg-success border-0">
    <div class="d-flex">
      <div class="toast-body">✅ You have been successfully logged out.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($logout_timeout): ?>
  <div id="logoutTimeoutToast" class="toast align-items-center text-bg-warning border-0">
    <div class="d-flex">
      <div class="toast-body">⏰ Your session has expired due to inactivity. Please log in again.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($register_success): ?>
  <div id="registerSuccessToast" class="toast align-items-center text-bg-success border-0">
    <div class="d-flex">
      <div class="toast-body">✅ Registration successful! Check your email to verify your account.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($verification_success)): ?>
  <div id="verificationSuccessToast" class="toast align-items-center text-bg-success border-0">
    <div class="d-flex">
      <div class="toast-body"><?php echo htmlspecialchars($verification_success); unset($_SESSION['verification_success']); ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const navbar = document.getElementById('mainNavbar');
  const getStartedBtn = document.getElementById('getStartedBtn');

  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });

  if (getStartedBtn) {
    getStartedBtn.addEventListener('click', () => {
      const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
      registerModal.show();
    });
  }

  // Show email verification success modal
  <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified']): ?>
  const emailVerifiedModal = new bootstrap.Modal(document.getElementById('emailVerifiedModal'));
  emailVerifiedModal.show();
  <?php 
    unset($_SESSION['email_verified']);
    unset($_SESSION['verified_user_name']);
    unset($_SESSION['verified_user_email']);
  ?>
  <?php endif; ?>

  // Show all toast notifications including login success and timeout
  ['loginToast', 'registerErrorToast', 'registerSuccessToast', 'loginSuccessToast', 'logoutSuccessToast', 'logoutTimeoutToast'].forEach(id => {
    const toastEl = document.getElementById(id);
    if (toastEl) {
      const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
      toast.show();
    }
  });

  const openLoginLink = document.getElementById('openLoginFromRegister');
  if (openLoginLink) {
    openLoginLink.addEventListener('click', (e) => {
      e.preventDefault();
      const registerModalEl = document.getElementById('registerModal');
      const regInstance = bootstrap.Modal.getInstance(registerModalEl);
      if (regInstance) regInstance.hide();

      const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
      loginModal.show();
    });
  }

  // Password policy validation
  const passwordField = document.getElementById('passwordField');
  if (passwordField) {
    passwordField.addEventListener('input', function() {
      const password = this.value;
      
      // Check length
      const lengthReq = document.getElementById('length-req');
      if (password.length >= 8) {
        lengthReq.className = 'requirement text-success';
        lengthReq.innerHTML = '✓ At least 8 characters';
      } else {
        lengthReq.className = 'requirement text-danger';
        lengthReq.innerHTML = '• At least 8 characters';
      }
      
      // Check uppercase
      const uppercaseReq = document.getElementById('uppercase-req');
      if (/[A-Z]/.test(password)) {
        uppercaseReq.className = 'requirement text-success';
        uppercaseReq.innerHTML = '✓ One uppercase letter (A-Z)';
      } else {
        uppercaseReq.className = 'requirement text-danger';
        uppercaseReq.innerHTML = '• One uppercase letter (A-Z)';
      }
      
      // Check lowercase
      const lowercaseReq = document.getElementById('lowercase-req');
      if (/[a-z]/.test(password)) {
        lowercaseReq.className = 'requirement text-success';
        lowercaseReq.innerHTML = '✓ One lowercase letter (a-z)';
      } else {
        lowercaseReq.className = 'requirement text-danger';
        lowercaseReq.innerHTML = '• One lowercase letter (a-z)';
      }
      
      // Check number
      const numberReq = document.getElementById('number-req');
      if (/[0-9]/.test(password)) {
        numberReq.className = 'requirement text-success';
        numberReq.innerHTML = '✓ One number (0-9)';
      } else {
        numberReq.className = 'requirement text-danger';
        numberReq.innerHTML = '• One number (0-9)';
      }
      
      // Check special character
      const specialReq = document.getElementById('special-req');
      if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        specialReq.className = 'requirement text-success';
        specialReq.innerHTML = '✓ One special character (!@#$%^&*)';
      } else {
        specialReq.className = 'requirement text-danger';
        specialReq.innerHTML = '• One special character (!@#$%^&*)';
      }
    });
  }

function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'block';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    window.location.href = 'logout.php';
}

});

// Function to open login modal from email verification success
function openLoginFromVerification() {
  const emailVerifiedModalEl = document.getElementById('emailVerifiedModal');
  const verifiedInstance = bootstrap.Modal.getInstance(emailVerifiedModalEl);
  if (verifiedInstance) verifiedInstance.hide();

  const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
  loginModal.show();
}
</script>

</body>
</html>

