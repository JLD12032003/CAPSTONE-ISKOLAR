<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification - ISKOLar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0055FF;
            --dark: #012A4A;
        }
        body {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        .otp-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            margin: 80px auto;
            max-width: 400px;
        }
        .otp-title {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.8rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
        }
        .form-control {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 5px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 85, 255, 0.25);
        }
        .btn-verify {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 12px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 85, 255, 0.3);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
        }
        .info-text {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <h2 class="otp-title"><i class="bi bi-shield-check" style="color: var(--primary);"></i></h2>
        <h3 class="otp-title" style="font-size: 1.3rem; margin-top: -10px; margin-bottom: 25px;">Verify Your Code</h3>
        
        <p class="info-text mb-3">
            A 6-digit verification code has been sent to your email. Please enter it below to complete your login.
        </p>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label class="form-label">Enter OTP Code</label>
            <input type="text" name="otp" class="form-control" maxlength="6" inputmode="numeric" placeholder="000000" required>
            
            <button type="submit" class="btn-verify">Verify & Login</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <small class="text-muted">
                Didn't receive the code? <a href="../../index.php" class="text-primary fw-semibold text-decoration-none">Try again</a>
            </small>
        </div>
    </div>
</body>
</html>
