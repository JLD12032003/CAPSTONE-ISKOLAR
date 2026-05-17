<!DOCTYPE html>
<html>
<head>
    <title>Register - ISKOLar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/style.css">
    <style>
        :root {
            --primary: #0055FF;
            --secondary: #FDC500;
            --dark: #012A4A;
        }
        body {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            margin: 50px auto;
            max-width: 450px;
        }
        .register-title {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.8rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-top: 15px;
            margin-bottom: 8px;
        }
        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
        }
        .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 85, 255, 0.3);
            color: white;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c3e6cb;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .login-link a:hover {
            color: var(--dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="register-title"><i class="bi bi-mortarboard-fill" style="color: var(--primary);"></i> ISKOLar</h2>
        <h3 class="register-title" style="font-size: 1.3rem; margin-top: -10px; margin-bottom: 25px;">Create Your Account</h3>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control" value="<?= isset($fullname) ? htmlspecialchars($fullname) : '' ?>" required>
            
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
            
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
            <small class="text-muted">Minimum 6 characters</small>
            
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
            
            <label class="form-label">Account Type</label>
            <select name="user_type" class="form-select" required>
                <option value="" disabled selected>Select your role</option>
                <option value="student">Student</option>
                <option value="donor">Donor</option>
                <option value="foundation">Foundation</option>
            </select>
            
            <button type="submit" class="btn-register">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="../../index.php">Log in here</a>
        </div>
    </div>
</body>
</html>
