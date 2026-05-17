# 📚 ISKOLar Database & Architecture Guide

## Database Schema Update

### Current Users Table Structure (After Updates)

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    google_id VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'donor', 'foundation') NOT NULL DEFAULT 'student',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

### Migration Script

If you're updating an existing database:

```sql
-- Update existing users table to add user_type column
ALTER TABLE email_auth.users 
ADD COLUMN user_type ENUM('student', 'donor', 'foundation') 
NOT NULL DEFAULT 'student' 
AFTER google_id;

-- Create index for user_type for better query performance
CREATE INDEX idx_user_type ON email_auth.users(user_type);

-- Create index for verified users for faster queries
CREATE INDEX idx_is_verified ON email_auth.users(is_verified);
```

### Supporting Tables (Unchanged)

```sql
-- Email verification tokens
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- OTP codes for 2FA
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

## Session Structure

### Session Variables Set During Login/Registration

```php
$_SESSION['user_id']         // User ID from database
$_SESSION['user_type']       // 'student', 'donor', or 'foundation'
$_SESSION['name']            // User's full name
$_SESSION['email']           // User's email address
$_SESSION['login_success']   // Success message (if applicable)
```

### Session Security

- Session IDs are regenerated after login: `session_regenerate_id(true)`
- Sessions are validated on each page load
- Logout clears all session data: `session_destroy()`

---

## User Flow Diagram

```
START
  └─→ index.php (Landing Page)
      ├─→ Already logged in? 
      │   ├─→ student → student_home.php 
      │   └─→ donor/foundation → donor_home.php
      │
      ├─→ Register Form
      │   ├─→ Validate inputs
      │   ├─→ Hash password
      │   ├─→ Create user with user_type
      │   ├─→ Generate verification token
      │   ├─→ Send verification email
      │   └─→ Show success toast
      │
      ├─→ Login Form
      │   ├─→ Validate email exists
      │   ├─→ Verify password
      │   ├─→ Check email verified
      │   ├─→ Generate OTP
      │   ├─→ Send OTP email
      │   └─→ Redirect to OTP page
      │
      ├─→ OTP Verification
      │   ├─→ Validate OTP
      │   ├─→ Create session
      │   └─→ Redirect to appropriate dashboard
      │
      └─→ Dashboard (based on user_type)
          ├─→ student_home.php (for students)
          └─→ donor_home.php (for donors/foundations)
```

---

## API/Route Structure (Previous Implementation)

The old index.php used a router pattern with actions:

```php
$action = $_GET['action'] ?? 'login';

switch($action){
    case 'register': $controller->register(); break;
    case 'verify': $controller->verify(); break;
    case 'login': $controller->login(); break;
    case 'otp': $controller->otp(); break;
    case 'home': $controller->home(); break;
    case 'logout': $controller->logout(); break;
    case 'googleLogin': $controller->googleLogin(); break;
    case 'googleCallback': $controller->googleCallback(); break;
    default: $controller->login();
}
```

**Old URLs:**
- `index.php?action=login` - Login page
- `index.php?action=register` - Register page
- `index.php?action=verify&token=xyz` - Email verification
- `index.php?action=otp` - OTP entry page
- `index.php?action=home` - Dashboard
- `index.php?action=logout` - Logout

**New Structure:**
The landing page is now the main entry point. Modal-based forms handle login/registration without page redirects.

---

## User Type Differentiation

### Student (`user_type = 'student'`)
**Dashboard**: `app/views/student_home.php`
**Features**:
- Browse scholarships
- Submit applications
- Track application status
- View awards received
- User profile

### Donor (`user_type = 'donor'`)
**Dashboard**: `app/views/donor_home.php`
**Features**:
- Create scholarships
- Manage applications
- Award scholarships
- Track donation history
- Analytics

### Foundation (`user_type = 'foundation'`)
**Dashboard**: `app/views/donor_home.php` (same as donor)
**Features**: Same as donor

---

## Query Examples

### Get all students
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE user_type = 'student' AND is_verified = 1");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Get all active donors
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE user_type IN ('donor', 'foundation') AND is_verified = 1");
$stmt->execute();
$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Count by user type
```php
$stmt = $conn->prepare("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
$stmt->execute();
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

---

## File Organization

```
ISKOLAR/
├── config/
│   ├── database.php          (PDO connection)
│   └── google.php            (Google OAuth credentials)
├── app/
│   ├── core/
│   │   ├── Mailer.php        (Email sending)
│   │   └── CSRFToken.php     (CSRF protection)
│   ├── models/
│   │   └── User.php          (Database operations)
│   ├── controllers/
│   │   └── AuthController.php (Auth logic)
│   └── views/
│       ├── login.php
│       ├── register.php
│       ├── otp.php
│       ├── verify_email.php
│       ├── home.php
│       ├── student_home.php  (NEW)
│       └── donor_home.php    (NEW)
├── public/
│   └── style.css             (Shared styles)
├── vendor/
│   └── (Composer dependencies)
├── index.php                 (NEW: Landing page)
├── database.sql              (UPDATED)
└── README files
```

---

## Configuration Files

### `config/database.php`
```php
private $host = "localhost";
private $db = "email_auth";
private $user = "root";
private $pass = "";
```

### `config/google.php` (Required for Google OAuth)
```php
return [
    'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_CLIENT_SECRET',
    'redirect_uri' => 'http://localhost/ISKOLAR_3RD_YEAR_EDITION/index.php?action=googleCallback',
    'scopes' => ['profile', 'email']
];
```

---

## Security Checklist

- ✅ CSRF tokens on all forms
- ✅ Prepared statements for all DB queries
- ✅ Password hashed with PASSWORD_DEFAULT (bcrypt)
- ✅ Session regeneration after login
- ✅ Input validation and sanitization
- ✅ Email verification required
- ✅ OTP verification for login
- ✅ No sensitive data in URL
- ✅ Secure password requirements
- ✅ Error messages don't reveal system info

---

## Performance Considerations

### Database Indexes
Consider adding these for better performance:
```sql
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_user_type ON users(user_type);
CREATE INDEX idx_verification_token ON email_verifications(token);
CREATE INDEX idx_otp_user_expires ON otp_codes(user_id, expires_at);
```

### Caching Opportunities
- Cache user data by ID
- Cache user role lookups
- Cache scholarship listings

### Query Optimization
- Use LIMIT for pagination
- Select only needed columns
- Use JOIN for related data

---

## Troubleshooting

### Database Connection Issues
- Check database credentials in `config/database.php`
- Ensure MySQL is running
- Verify database `email_auth` exists

### Session Issues
- Check PHP `session.save_path` is writable
- Verify `session_start()` is called early
- Check cookie settings

### Email Issues
- Verify Mailer.php configuration
- Check SMTP credentials
- Review spam folder

### CSRF Token Issues
- Ensure `CSRFToken::getInput()` in form
- Check `session_start()` called
- Verify `_csrf_token` POST parameter

---

## Deployment Checklist

- [ ] Database schema updated with user_type column
- [ ] Environment variables configured (database credentials)
- [ ] Email SMTP configured
- [ ] Google OAuth credentials set up
- [ ] Session directory is writable
- [ ] Database credentials secured
- [ ] SSL/HTTPS enabled
- [ ] Error logging configured
- [ ] Backup strategy in place
- [ ] Testing completed (login, register, email, OAuth)

---

**Last Updated**: April 13, 2026
**Version**: 2.0
