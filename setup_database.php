<?php
// Database setup script for ISKOLar
echo "<h1>ISKOLar Database Setup</h1>";

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ISKOLAR_101");
    echo "<p>✅ Database 'ISKOLAR_101' created/verified</p>";
    
    // Use the database
    $pdo->exec("USE ISKOLAR_101");
    
    // Create basic tables needed for provider system
    
    // 1. Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('student', 'provider', 'admin') NOT NULL DEFAULT 'student',
            is_verified TINYINT(1) DEFAULT 0,
            profile_completed TINYINT(1) DEFAULT 0,
            profile_completion_step INT DEFAULT 0,
            created_by INT DEFAULT NULL,
            school_id INT DEFAULT NULL,
            google_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Users table created</p>";
    
    // 2. Email verifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Email verifications table created</p>";
    
    // 3. Schools table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_name VARCHAR(255) NOT NULL,
            school_code VARCHAR(50) UNIQUE NOT NULL,
            address TEXT,
            city VARCHAR(100),
            province VARCHAR(100),
            region VARCHAR(100),
            contact_number VARCHAR(50),
            email VARCHAR(255),
            president_name VARCHAR(255),
            vp_name VARCHAR(255),
            finance_officer_name VARCHAR(255),
            scholarship_coordinator_name VARCHAR(255),
            scholarship_coordinator_email VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Schools table created</p>";
    
    // 4. Provider profiles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            organization_name VARCHAR(255) NOT NULL,
            organization_type ENUM('Individual', 'Foundation', 'Corporation', 'NGO', 'Government') NOT NULL,
            registration_number VARCHAR(100),
            tin VARCHAR(50),
            contact_person VARCHAR(255),
            position VARCHAR(100),
            office_address TEXT,
            contact_number VARCHAR(50),
            website VARCHAR(255),
            mission TEXT,
            vision TEXT,
            is_verified TINYINT(1) DEFAULT 0,
            verification_documents TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Provider profiles table created</p>";
    
    // 5. Scholarships table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scholarships (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            school_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            scholarship_type ENUM('Full', 'Partial', 'Book Allowance', 'Tuition Only', 'Living Allowance') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            slots INT NOT NULL,
            available_slots INT NOT NULL,
            eligible_courses TEXT,
            min_gwa DECIMAL(4,2),
            max_family_income DECIMAL(12,2),
            year_levels TEXT,
            other_requirements TEXT,
            application_start DATE,
            application_end DATE,
            status ENUM('Draft', 'Pending Approval', 'Active', 'Closed', 'Cancelled') DEFAULT 'Draft',
            approved_by INT,
            approved_at TIMESTAMP NULL,
            partnership_agreement TEXT,
            meeting_date DATE,
            meeting_attendees TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Scholarships table created</p>";
    
    // 6. Student profiles table (basic)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            last_name VARCHAR(100),
            first_name VARCHAR(100),
            middle_name VARCHAR(100),
            suffix VARCHAR(20),
            birthdate DATE,
            place_of_birth VARCHAR(255),
            sex ENUM('Male', 'Female'),
            civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Others'),
            citizenship VARCHAR(100),
            mobile_number VARCHAR(50),
            landline VARCHAR(50),
            present_address TEXT,
            permanent_address TEXT,
            zip_code VARCHAR(20),
            school_id INT,
            school_name VARCHAR(255),
            school_address TEXT,
            school_sector ENUM('Public', 'Private'),
            course VARCHAR(255),
            year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'),
            type_of_disability VARCHAR(255),
            father_name VARCHAR(255),
            father_address TEXT,
            father_contact VARCHAR(50),
            father_occupation VARCHAR(255),
            father_employer VARCHAR(255),
            father_employer_address TEXT,
            father_education VARCHAR(100),
            father_income DECIMAL(12,2),
            mother_name VARCHAR(255),
            mother_address TEXT,
            mother_contact VARCHAR(50),
            mother_occupation VARCHAR(255),
            mother_employer VARCHAR(255),
            mother_employer_address TEXT,
            mother_education VARCHAR(100),
            mother_income DECIMAL(12,2),
            legal_guardian VARCHAR(255),
            num_siblings INT DEFAULT 0,
            family_monthly_income DECIMAL(12,2),
            is_4ps_beneficiary ENUM('Yes', 'No'),
            gwa DECIMAL(4,2),
            awards_received TEXT,
            profile_photo VARCHAR(255),
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Student profiles table created</p>";
    
    // 7. Scholarship applications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scholarship_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scholarship_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('Submitted', 'Under Review', 'Shortlisted', 'Interview', 'Approved', 'Rejected', 'Withdrawn') DEFAULT 'Submitted',
            personal_statement TEXT,
            why_deserve_scholarship TEXT,
            documents JSON,
            reviewed_by INT,
            reviewed_at TIMESTAMP NULL,
            review_notes TEXT,
            admin_notes TEXT,
            is_student_enrolled TINYINT(1) DEFAULT 1,
            enrollment_verified_at TIMESTAMP NULL,
            provider_decision ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            provider_notes TEXT,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_application (scholarship_id, student_id)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Scholarship applications table created</p>";
    
    // Insert default school
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schools WHERE school_code = 'DCC-2024'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO schools (school_name, school_code, address, city, province, region, contact_number, email, president_name, scholarship_coordinator_name) 
            VALUES (
                'Davao Central College',
                'DCC-2024',
                'Tigatto Road, Buhangin',
                'Davao City',
                'Davao del Sur',
                'Region XI - Davao Region',
                '(082) 234-5678',
                'admin@davaocentralcollege.edu.ph',
                'Dr. Maria Santos',
                'Prof. Juan Dela Cruz'
            )
        ");
        echo "<p>✅ Default school (Davao Central College) added</p>";
    }
    
    // Create default admin account if it doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@davaocentralcollege.edu.ph'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('Admin@DCC2024', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (fullname, email, password, user_type, is_verified, school_id) 
            VALUES (
                'DCC Admin',
                'admin@davaocentralcollege.edu.ph',
                '$adminPassword',
                'admin',
                1,
                1
            )
        ");
        echo "<p>✅ Default admin account created (admin@davaocentralcollege.edu.ph / Admin@DCC2024)</p>";
    }
    
    echo "<h2>🎉 Database Setup Complete!</h2>";
    echo "<p>Your ISKOLar database is ready. You can now:</p>";
    echo "<ul>";
    echo "<li>Register as a provider at <a href='index.php'>index.php</a></li>";
    echo "<li>Test the system at <a href='test_provider_system.php'>test_provider_system.php</a></li>";
    echo "<li>Access the provider dashboard after registration</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>