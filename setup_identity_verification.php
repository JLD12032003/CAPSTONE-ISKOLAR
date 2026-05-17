<?php
// Setup Identity Verification System
echo "<h1>Identity Verification System Setup</h1>";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=localhost;dbname=ISKOLAR_101", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connected to ISKOLAR_101 database</p>";
    
    // Create admin_verifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            
            -- Personal Information
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            suffix VARCHAR(20),
            birthdate DATE NOT NULL,
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            nationality VARCHAR(100) DEFAULT 'Filipino',
            
            -- Contact Information
            mobile_number VARCHAR(20) NOT NULL,
            landline VARCHAR(20),
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            province VARCHAR(100) NOT NULL,
            postal_code VARCHAR(10),
            
            -- Professional Information
            employee_id VARCHAR(50),
            department VARCHAR(100),
            position VARCHAR(100) NOT NULL,
            years_of_service INT,
            employment_status ENUM('Regular', 'Contractual', 'Part-time') DEFAULT 'Regular',
            
            -- Administrative Role
            admin_role ENUM('committee', 'vp', 'president', 'finance', 'registrar', 'dean', 'coordinator') NOT NULL,
            role_description TEXT,
            
            -- Identity Documents
            valid_id_type ENUM('Government ID', 'Passport', 'Drivers License', 'SSS ID', 'PhilHealth ID', 'TIN ID', 'Voters ID', 'PRC ID', 'School ID') NOT NULL,
            valid_id_number VARCHAR(100) NOT NULL,
            valid_id_file VARCHAR(255) NOT NULL,
            
            -- Verification Status
            verification_status ENUM('Pending', 'Under Review', 'Approved', 'Rejected') DEFAULT 'Pending',
            verified_by INT NULL,
            verified_at TIMESTAMP NULL,
            verification_notes TEXT,
            
            -- Metadata
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_user_id (user_id),
            INDEX idx_verification_status (verification_status),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Admin verifications table created</p>";
    
    // Add admin_role column to users table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'admin_role'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN admin_role ENUM('committee', 'vp', 'president', 'finance', 'registrar', 'dean', 'coordinator') NULL AFTER school_id
        ");
        echo "<p>✅ Added admin_role column to users table</p>";
    }
    
    // Add verification_completed column to users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'verification_completed'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN verification_completed TINYINT(1) DEFAULT 0 AFTER admin_role
        ");
        echo "<p>✅ Added verification_completed column to users table</p>";
    }
    
    // Create uploads directory for ID documents
    $uploadDir = __DIR__ . '/uploads/identity_verification/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "<p>✅ Created identity verification uploads directory</p>";
    }
    
    // Create .htaccess for security
    $htaccessContent = "
# Deny direct access to uploaded files
<Files *>
    Order Deny,Allow
    Deny from all
</Files>

# Allow access only to image files for authorized users
<FilesMatch '\.(jpg|jpeg|png|pdf)$'>
    Order Allow,Deny
    Allow from all
</FilesMatch>
";
    
    file_put_contents($uploadDir . '.htaccess', $htaccessContent);
    echo "<p>✅ Created security .htaccess file</p>";
    
    echo "<h2>🎉 Identity Verification System Setup Complete!</h2>";
    echo "<p>The identity verification system is now ready with:</p>";
    echo "<ul>";
    echo "<li>✅ Admin verification data storage</li>";
    echo "<li>✅ Personal and professional information tracking</li>";
    echo "<li>✅ Identity document upload capability</li>";
    echo "<li>✅ Verification workflow management</li>";
    echo "<li>✅ Secure file storage with access controls</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Admin users will be required to complete identity verification after registration</li>";
    echo "<li>Upload valid government ID for verification</li>";
    echo "<li>System administrators can review and approve verifications</li>";
    echo "<li>Only verified admins can access full administrative functions</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>