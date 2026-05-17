<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Create only school administrator account
    $admin = [
        'fullname' => 'Prof. Juan Dela Cruz',
        'email' => 'admin@davaocentralcollege.edu.ph',
        'password' => 'SchoolAdmin2024!',
        'admin_role' => 'coordinator' // School administrator/coordinator
    ];
    
    echo "Creating school administrator account...\n";
    
    $hashedPassword = password_hash($admin['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, email, password, user_type, school_id, admin_role, is_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $admin['fullname'],
        $admin['email'],
        $hashedPassword,
        'admin',
        1, // Davao Central College ID
        $admin['admin_role'],
        1
    ]);
    
    if ($result) {
        $user_id = $conn->lastInsertId();
        
        // Create verification record
        $verificationStmt = $conn->prepare("
            INSERT INTO admin_verifications (
                user_id, first_name, last_name, birthdate, gender, mobile_number,
                address, city, province, position, admin_role, valid_id_type,
                valid_id_number, valid_id_file, verification_status, verified_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $verificationStmt->execute([
            $user_id,
            'Juan',
            'Dela Cruz',
            '1975-01-01',
            'Male',
            '09123456789',
            'Davao City',
            'Davao City',
            'Davao del Sur',
            'School Administrator / Scholarship Coordinator',
            $admin['admin_role'],
            'Government ID',
            'ADMIN-COORDINATOR-001',
            'pre_verified_admin.pdf',
            'Approved'
        ]);
        
        echo "✅ Created school administrator account\n\n";
        echo "=== SCHOOL ADMIN LOGIN CREDENTIALS ===\n";
        echo "Email: {$admin['email']}\n";
        echo "Password: {$admin['password']}\n";
        echo "Role: School Administrator/Coordinator\n\n";
        
        echo "=== EMAIL APPROVAL CONTACTS ===\n";
        echo "Committee Email: committee@davaocentralcollege.edu.ph\n";
        echo "VP Email: vp@davaocentralcollege.edu.ph\n";
        echo "President Email: president@davaocentralcollege.edu.ph\n\n";
        
        echo "Note: President, VP, and Committee members don't need accounts.\n";
        echo "They will receive approval emails and respond via email links.\n";
        
    } else {
        echo "❌ Failed to create school administrator account\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>