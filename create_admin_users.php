<?php
/**
 * Manual Admin User Creation Script
 * Creates pre-defined admin users for the ISKOLar system
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>🔐 Manual Admin User Creation</h1>\n";
echo "<p>Creating pre-defined admin users for ISKOLar system...</p>\n";

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Get Davao Central College school ID
    $stmt = $conn->prepare("SELECT id FROM schools WHERE school_name LIKE '%Davao Central College%' LIMIT 1");
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$school) {
        // Create Davao Central College if it doesn't exist
        $stmt = $conn->prepare("
            INSERT INTO schools (
                school_name, school_code, address, city, province, region,
                contact_number, email, president_name, scholarship_coordinator_name,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'Davao Central College',
            'DCC-2024',
            'Tigatto Road, Buhangin',
            'Davao City',
            'Davao del Sur',
            'Region XI',
            '(082) 234-5678',
            'admin@davaocentralcollege.edu.ph',
            'Dr. Maria Santos',
            'Prof. Juan Dela Cruz',
            1
        ]);
        
        $school_id = $conn->lastInsertId();
        echo "<p>✅ Created Davao Central College (ID: {$school_id})</p>\n";
    } else {
        $school_id = $school['id'];
        echo "<p>✅ Found Davao Central College (ID: {$school_id})</p>\n";
    }
    
    // Define admin users to create
    $adminUsers = [
        [
            'fullname' => 'Dr. Maria Santos',
            'email' => 'president@davaocentralcollege.edu.ph',
            'password' => 'President2024!',
            'admin_role' => 'president',
            'description' => 'School President - Final approval authority'
        ],
        [
            'fullname' => 'Dr. Roberto Cruz',
            'email' => 'vp@davaocentralcollege.edu.ph',
            'password' => 'VicePresident2024!',
            'admin_role' => 'vp',
            'description' => 'Vice President for Academic Affairs'
        ],
        [
            'fullname' => 'Prof. Ana Reyes',
            'email' => 'committee@davaocentralcollege.edu.ph',
            'password' => 'Committee2024!',
            'admin_role' => 'committee',
            'description' => 'Scholarship Committee Chairperson'
        ],
        [
            'fullname' => 'Ms. Carmen Lopez',
            'email' => 'registrar@davaocentralcollege.edu.ph',
            'password' => 'Registrar2024!',
            'admin_role' => 'registrar',
            'description' => 'University Registrar'
        ],
        [
            'fullname' => 'Mr. Jose Garcia',
            'email' => 'finance@davaocentralcollege.edu.ph',
            'password' => 'Finance2024!',
            'admin_role' => 'finance',
            'description' => 'Finance Officer'
        ]
    ];
    
    echo "<h2>📋 Creating Admin Users</h2>\n";
    
    foreach ($adminUsers as $admin) {
        // Check if user already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$admin['email']]);
        
        if ($checkStmt->rowCount() > 0) {
            echo "<p>⚠️ Admin already exists: {$admin['email']}</p>\n";
            continue;
        }
        
        // Hash password
        $hashedPassword = password_hash($admin['password'], PASSWORD_DEFAULT);
        
        // Insert admin user
        $insertStmt = $conn->prepare("
            INSERT INTO users (
                fullname, email, password, user_type, school_id, admin_role, is_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($insertStmt->execute([
            $admin['fullname'],
            $admin['email'],
            $hashedPassword,
            'admin',
            $school_id,
            $admin['admin_role'],
            1
        ])) {
            $user_id = $conn->lastInsertId();
            
            // Create approved verification record
            $verificationStmt = $conn->prepare("
                INSERT INTO admin_verifications (
                    user_id, first_name, last_name, birthdate, gender, mobile_number,
                    address, city, province, position, admin_role, valid_id_type,
                    valid_id_number, valid_id_file, verification_status, verified_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $nameParts = explode(' ', $admin['fullname']);
            $firstName = $nameParts[0];
            $lastName = end($nameParts);
            
            $verificationStmt->execute([
                $user_id,
                $firstName,
                $lastName,
                '1980-01-01', // Default birthdate
                'Male', // Default gender
                '09123456789', // Default mobile
                'Davao City', // Default address
                'Davao City',
                'Davao del Sur',
                $admin['description'],
                $admin['admin_role'],
                'Government ID',
                'ADMIN-' . strtoupper($admin['admin_role']) . '-001',
                'pre_verified_admin.pdf',
                'Approved'
            ]);
            
            echo "<p>✅ Created admin: <strong>{$admin['fullname']}</strong> ({$admin['email']})</p>\n";
        } else {
            echo "<p>❌ Failed to create admin: {$admin['email']}</p>\n";
        }
    }
    
    echo "<h2>🔑 Admin Login Credentials</h2>\n";
    echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;'>\n";
    echo "<h3 style='color: #0d47a1; margin-top: 0;'>Use these credentials to login as admin:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background: #f5f5f5;'>\n";
    echo "<th style='padding: 10px; text-align: left;'>Role</th>\n";
    echo "<th style='padding: 10px; text-align: left;'>Email</th>\n";
    echo "<th style='padding: 10px; text-align: left;'>Password</th>\n";
    echo "<th style='padding: 10px; text-align: left;'>Description</th>\n";
    echo "</tr>\n";
    
    foreach ($adminUsers as $admin) {
        echo "<tr>\n";
        echo "<td style='padding: 10px;'><strong>" . ucfirst($admin['admin_role']) . "</strong></td>\n";
        echo "<td style='padding: 10px; font-family: monospace;'>{$admin['email']}</td>\n";
        echo "<td style='padding: 10px; font-family: monospace; background: #fff3cd;'><strong>{$admin['password']}</strong></td>\n";
        echo "<td style='padding: 10px;'>{$admin['description']}</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    echo "</div>\n";
    
    echo "<h2>🚀 Quick Login Instructions</h2>\n";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>\n";
    echo "<ol style='margin: 0; color: #155724;'>\n";
    echo "<li>Go to the ISKOLar homepage</li>\n";
    echo "<li>Click the <strong>Login</strong> button</li>\n";
    echo "<li>Use any of the email/password combinations above</li>\n";
    echo "<li>You will be redirected to the admin dashboard</li>\n";
    echo "<li>All admin accounts are pre-verified and ready to use</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<h2>⚠️ Security Notes</h2>\n";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>\n";
    echo "<ul style='margin: 0; color: #856404;'>\n";
    echo "<li><strong>Change passwords immediately</strong> after first login</li>\n";
    echo "<li>These are <strong>development/testing credentials</strong></li>\n";
    echo "<li>Use strong, unique passwords in production</li>\n";
    echo "<li>Enable two-factor authentication if available</li>\n";
    echo "<li>Regularly audit admin access and permissions</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // Test admin login
    echo "<h2>🧪 Testing Admin Access</h2>\n";
    $testEmail = 'president@davaocentralcollege.edu.ph';
    $testStmt = $conn->prepare("
        SELECT u.*, av.verification_status 
        FROM users u 
        LEFT JOIN admin_verifications av ON u.id = av.user_id 
        WHERE u.email = ? AND u.user_type = 'admin'
    ");
    $testStmt->execute([$testEmail]);
    $testAdmin = $testStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testAdmin) {
        echo "<p>✅ <strong>Test Login Available:</strong></p>\n";
        echo "<ul>\n";
        echo "<li><strong>Email:</strong> {$testAdmin['email']}</li>\n";
        echo "<li><strong>User Type:</strong> {$testAdmin['user_type']}</li>\n";
        echo "<li><strong>Admin Role:</strong> {$testAdmin['admin_role']}</li>\n";
        echo "<li><strong>Verification Status:</strong> {$testAdmin['verification_status']}</li>\n";
        echo "<li><strong>Account Status:</strong> " . ($testAdmin['is_verified'] ? 'Verified' : 'Unverified') . "</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>❌ Test admin not found</p>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;'>\n";
    echo "<h4 style='color: #721c24; margin: 0;'>❌ Error</h4>\n";
    echo "<p style='margin: 10px 0 0 0; color: #721c24;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "\n<hr>\n";
echo "<p><em>Admin creation completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>