<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Admin users to create
    $admins = [
        [
            'fullname' => 'Dr. Maria Santos',
            'email' => 'president@davaocentralcollege.edu.ph',
            'password' => 'President2024!',
            'admin_role' => 'president'
        ],
        [
            'fullname' => 'Dr. Roberto Cruz',
            'email' => 'vp@davaocentralcollege.edu.ph',
            'password' => 'VicePresident2024!',
            'admin_role' => 'vp'
        ],
        [
            'fullname' => 'Prof. Ana Reyes',
            'email' => 'committee@davaocentralcollege.edu.ph',
            'password' => 'Committee2024!',
            'admin_role' => 'committee'
        ]
    ];
    
    echo "Creating admin users...\n";
    
    foreach ($admins as $admin) {
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
            echo "✅ Created: {$admin['email']} (Password: {$admin['password']})\n";
        } else {
            echo "❌ Failed to create: {$admin['email']}\n";
        }
    }
    
    echo "\n=== ADMIN LOGIN CREDENTIALS ===\n";
    echo "Email: president@davaocentralcollege.edu.ph\n";
    echo "Password: President2024!\n";
    echo "Role: School President\n\n";
    
    echo "Email: vp@davaocentralcollege.edu.ph\n";
    echo "Password: VicePresident2024!\n";
    echo "Role: Vice President\n\n";
    
    echo "Email: committee@davaocentralcollege.edu.ph\n";
    echo "Password: Committee2024!\n";
    echo "Role: Committee Member\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>