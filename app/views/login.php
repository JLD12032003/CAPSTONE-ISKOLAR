<!DOCTYPE html>
<html>
<head>
    <title>Login - ISKOLar</title>
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
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            margin: 80px auto;
            max-width: 400px;
        }
        .login-title {
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
        .btn-login {
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
        .btn-login:hover {
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .register-link a:hover {
            color: var(--dark);
            text-decoration: underline;
        }
        .divider {
            margin: 25px 0;
            text-align: center;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        .divider span {
            background: white;
            padding: 0 10px;
            position: relative;
            color: #999;
            font-size: 0.9rem;
        }
        .btn-google {
            display: block;
            color: white;
            text-decoration: none;
            background: #4285F4;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-google:hover {
            background: #357ae8;
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="login-title"><i class="bi bi-mortarboard-fill" style="color: var(--primary);"></i> ISKOLar</h2>
        <h3 class="login-title" style="font-size: 1.3rem; margin-top: -10px; margin-bottom: 25px;">Welcome Back</h3>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
            
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="../../index.php">Register here</a>
        </div>
        
        <div class="divider"><span>Or login with</span></div>
        <a href="../../index.php?action=googleLogin" class="btn-google">
            <i class="bi bi-google"></i> Google
        </a>
    </div>
</body>
</html>
