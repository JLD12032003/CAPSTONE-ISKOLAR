<!DOCTYPE html>
<html>
<head>
    <title>Email Verification - ISKOLar</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            max-width: 500px;
            text-align: center;
        }
        .verify-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .success-icon {
            color: #28a745;
        }
        .error-icon {
            color: #dc3545;
        }
        .verify-title {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        .verify-message {
            color: #666;
            margin-bottom: 30px;
            font-size: 1rem;
            line-height: 1.6;
        }
        .btn-back {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            color: white !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 85, 255, 0.3);
        }
        .success-message {
            color: #28a745;
        }
        .error-message {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <?php 
            $isSuccess = strpos($message, 'Email verified') !== false || strpos($message, 'may now log in') !== false;
        ?>
        
        <div class="verify-icon <?php echo $isSuccess ? 'success-icon' : 'error-icon'; ?>">
            <i class="bi <?php echo $isSuccess ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
        </div>
        
        <h2 class="verify-title <?php echo $isSuccess ? 'success-message' : 'error-message'; ?>">
            <?php echo $isSuccess ? 'Email Verified!' : 'Verification Failed'; ?>
        </h2>
        
        <p class="verify-message">
            <?= isset($message) ? htmlspecialchars($message) : 'Processing...' ?>
        </p>
        
        <a href="../../index.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Return to Home
        </a>
    </div>
</body>
</html>
