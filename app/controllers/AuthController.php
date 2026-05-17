<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../core/ActivityLogger.php';


class AuthController {

    private $user;
    private $logger;

    public function __construct() {
        $this->user = new User();
        $this->logger = new ActivityLogger();
    }

    public function register() {
        // initialize variables used by the view
        $error = '';
        $success = '';
        $fullname = '';
        $email = '';
        $user_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');
            $user_type = $_POST['user_type'] ?? '';

            if (empty($fullname) || empty($email) || empty($password) || empty($user_type)) {
                $error = "All fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (!in_array($user_type, ['student', 'donor', 'foundation'])) {
                $error = "Invalid user type selected.";
            } elseif ($this->user->findByEmail($email)) {
                $error = "Email is already registered.";
            } else {
                $this->user->register($fullname, $email, $password, $user_type);
                $user = $this->user->findByEmail($email);

                $token = bin2hex(random_bytes(32));
                $this->user->saveToken($user['id'], $token);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['PHP_SELF']);
                $link = "{$protocol}://{$host}{$path}/index.php?action=verify&token={$token}";


                $body = "<h3>Verify Account</h3><a href='{$link}'>{$link}</a>";
                Mailer::send($email, "Verify Account", $body);

                $success = "Registration successful, check your email to verify your account.";
                // clear fields after successful submission
                $fullname = $email = $user_type = '';
            }
        }

        require __DIR__ . '/../views/register.php';
    }

    public function verify() {
        $message = '';

        if (isset($_GET['token'])) {
            if ($this->user->verifyEmail($_GET['token'])) {
                $message = "Email verified! You may now log in.";
            } else {
                $message = "Invalid or expired verification token.";
            }
        } else {
            $message = "Missing verification token.";
        }

        require __DIR__ . '/../views/verify_email.php';
    }

    public function login() {
        // clear any pending otp session from previous attempts
        unset($_SESSION['otp_user']);

        $error = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($email) || empty($password)) {
                $error = "Email and password are required.";
                // Log failed attempt - missing credentials
                $this->logger->logLoginAttempt($email, 'FAILED', null, 'Missing email or password');
            } else {
                $user = $this->user->findByEmail($email);

                if (!$user || !password_verify($password, $user['password'])) {
                    $error = "Invalid credentials.";
                    // Log failed attempt - invalid credentials
                    $this->logger->logLoginAttempt($email, 'FAILED', $user['id'] ?? null, 'Invalid email or password');
                } elseif (!$user['is_verified']) {
                    $error = "Please verify your email address before logging in.";
                    // Log failed attempt - unverified account
                    $this->logger->logLoginAttempt($email, 'FAILED', $user['id'], 'Account not verified');
                } else {
                    $otp = rand(100000, 999999);

                    $saved = $this->user->saveOTP($user['id'], $otp);
                    if (!$saved) {
                        // log database error and inform user
                        error_log("Failed to save OTP for user {$user['id']}");
                        $error = "Unable to generate OTP at this time. Please try again.";
                        // Log failed attempt - system error
                        $this->logger->logLoginAttempt($email, 'FAILED', $user['id'], 'OTP generation failed');
                    } else {
                        $body = "<h3>Your OTP</h3><h2>{$otp}</h2>";
                        Mailer::send($email, "OTP Code", $body);

                        // store OTP for debugging - remove in production
                        $_SESSION['debug_otp'] = $otp;

                        $_SESSION['otp_user'] = $user['id'];
                        
                        // Log successful login attempt (credentials verified, OTP sent)
                        $this->logger->logLoginAttempt($email, 'SUCCESS', $user['id'], null);
                        
                        header("Location: index.php?action=otp");
                        exit;
                    }
                }
            }
        }

        require __DIR__ . '/../views/login.php';
    }

    public function otp() {
        // ensure a login attempt has happened
        if (empty($_SESSION['otp_user'])) {
            header("Location: index.php?action=login");
            exit;
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $otp = trim($_POST['otp'] ?? '');
            $user_id = $_SESSION['otp_user'];

            // Get user email for logging
            $user = $this->user->findById($user_id);
            $email = $user['email'] ?? 'unknown';

            if ($row = $this->user->validateOTP($user_id, $otp)) {
                // delete used OTP
                $this->user->deleteOTPById($row['id']);

                // fetch user to get all necessary data
                $user = $this->user->findById($user_id);
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = $user['user_type'];
                unset($_SESSION['otp_user']);
                unset($_SESSION['debug_otp']);

                // Log successful OTP verification (complete login)
                $this->logger->logLoginAttempt($email, 'SUCCESS', $user_id, 'OTP verified - Login complete');
                
                // Log system activity for successful login
                $this->logger->logSystemActivity(
                    $user_id, 
                    $user['user_type'], 
                    'LOGIN', 
                    'user_session', 
                    $user_id, 
                    'User successfully logged in'
                );

                header("Location: index.php?action=home");
                exit;
            } else {
                // attempt to diagnose reason
                $check = $this->user->getOTP($user_id, $otp);
                if ($check) {
                    if (strtotime($check['expires_at']) <= time()) {
                        $error = "OTP has expired. Please login again to receive a new code.";
                        $failureReason = 'OTP expired';
                    } else {
                        $error = "The code you entered is incorrect.";
                        $failureReason = 'Invalid OTP code';
                    }
                } else {
                    $error = "Invalid OTP code.";
                    $failureReason = 'OTP not found';
                }
                
                // Log failed OTP attempt
                $this->logger->logLoginAttempt($email, 'FAILED', $user_id, $failureReason);
            }
        }

        require __DIR__ . '/../views/otp.php';
    }

    public function home() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit;
        }

        // fetch user so we can display their name
        $user = $this->user->findById($_SESSION['user_id']);

        require __DIR__ . '/../views/home.php';
    }

    public function logout() {
        // Log logout activity before destroying session
        if (isset($_SESSION['user_id'])) {
            $user = $this->user->findById($_SESSION['user_id']);
            if ($user) {
                $this->logger->logSystemActivity(
                    $_SESSION['user_id'], 
                    $_SESSION['user_type'] ?? 'unknown', 
                    'LOGOUT', 
                    'user_session', 
                    $_SESSION['user_id'], 
                    'User logged out'
                );
            }
        }

        session_destroy();
        header("Location: index.php?action=login");
        exit;
    }

    /**
     * Initiates Google OAuth flow by redirecting user to Google's consent screen.
     */
    public function googleLogin() {
        // make sure the library is installed
        if (!class_exists('Google_Client')) {
            $error = 'Google API client library not found. Run `composer require google/apiclient`.';
            require __DIR__ . '/../views/login.php';
            return;
        }

        // load credentials
        $config = require __DIR__ . '/../../config/google.php';

        $client = new Google_Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setRedirectUri($config['redirect_uri']);
        $client->setScopes($config['scopes']);
        $client->setAccessType('offline');
        $client->setPrompt('select_account');

        $authUrl = $client->createAuthUrl();
        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        exit;
    }

    /**
     * Handles Google's callback, obtains user information, and logs the user in.
     */
    public function googleCallback() {
        // ensure library is available
        if (!class_exists('Google_Client')) {
            header('Location: index.php?action=login');
            exit;
        }

        // load credentials
        $config = require __DIR__ . '/../../config/google.php';

        $client = new Google_Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setRedirectUri($config['redirect_uri']);

        if (isset($_GET['code'])) {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

            if (isset($token['error'])) {
                // handle error gracefully
                $error = 'Google login failed: ' . htmlspecialchars($token['error_description'] ?? $token['error']);
                
                // Log failed Google login attempt
                $this->logger->logLoginAttempt('unknown', 'FAILED', null, 'Google OAuth error: ' . $token['error']);
                
                require __DIR__ . '/../views/login.php';
                return;
            }

            $client->setAccessToken($token);

            $oauth = new Google_Service_Oauth2($client);
            $googleUser = $oauth->userinfo->get();

            $email = $googleUser->email;
            $fullname = $googleUser->name ?? '';

            // either find existing user or create a new one
            $googleId = $googleUser->id ?? null;
            $user = $this->user->findByEmail($email);
            if (!$user) {
                // generate a random password since user will login through Google
                $randomPassword = bin2hex(random_bytes(16));
                $this->user->register($fullname, $email, $randomPassword, 'student');
                // fetch the newly created user so we can work with its id
                $newUser = $this->user->findByEmail($email);
                if ($newUser) {
                    $this->user->markVerified($newUser['id']);
                    // save google_id if available
                    if ($googleId) {
                        $this->user->saveGoogleId($newUser['id'], $googleId);
                    }
                    $user = $newUser;
                    
                    // Log successful Google registration + login
                    $this->logger->logLoginAttempt($email, 'SUCCESS', $user['id'], 'Google OAuth - New account created');
                    $this->logger->logSystemActivity(
                        $user['id'], 
                        'student', 
                        'REGISTER', 
                        'user_account', 
                        $user['id'], 
                        'New user registered via Google OAuth'
                    );
                }
            } else {
                // existing account: ensure google_id stored for future lookups
                if ($googleId && empty($user['google_id'])) {
                    $this->user->saveGoogleId($user['id'], $googleId);
                }
                
                // Log successful Google login for existing user
                $this->logger->logLoginAttempt($email, 'SUCCESS', $user['id'], 'Google OAuth - Existing account');
            }

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                // also keep email handy
                $_SESSION['email'] = $email;
                
                // save verification token para sa audit trail (optional pero recommended)
                $verificationToken = bin2hex(random_bytes(32));
                $this->user->saveToken($user['id'], $verificationToken);
                
                // Log system activity for successful Google login
                $this->logger->logSystemActivity(
                    $user['id'], 
                    $user['user_type'], 
                    'LOGIN', 
                    'user_session', 
                    $user['id'], 
                    'User successfully logged in via Google OAuth'
                );
                
                header('Location: index.php?action=home');
                exit;
            } else {
                // Log failed Google login - user creation failed
                $this->logger->logLoginAttempt($email, 'FAILED', null, 'Google OAuth - User creation failed');
            }
        } else {
            // Log failed Google login - no authorization code
            $this->logger->logLoginAttempt('unknown', 'FAILED', null, 'Google OAuth - No authorization code received');
        }

        // nothing to do – redirect back to login
        header('Location: index.php?action=login');
        exit;
    }
}
