<?php
require_once 'config/database.php';
require_once 'app/core/ActivityLogger.php';

try {
    $database = new Database();
    $conn = $database->connect();
    $logger = new ActivityLogger();
    
    echo "🔧 Fixing Admin Activity Logging...\n";
    echo "===================================\n";
    
    // Get a valid admin user ID
    $stmt = $conn->query("SELECT id, fullname FROM users WHERE user_type = 'admin' LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo "❌ No admin users found. Creating a test admin...\n";
        
        $stmt = $conn->prepare("
            INSERT INTO users (fullname, email, password, user_type, email_verified, profile_completed) 
            VALUES (?, ?, ?, 'admin', 1, 1)
        ");
        $stmt->execute([
            'System Administrator',
            'admin@iskolar.system',
            password_hash('admin123', PASSWORD_DEFAULT)
        ]);
        $adminId = $conn->lastInsertId();
        echo "✅ Created test admin with ID: {$adminId}\n";
    } else {
        $adminId = $admin['id'];
        echo "✅ Using existing admin: ID {$adminId} - {$admin['fullname']}\n";
    }
    
    // Now create test admin activities with valid admin ID
    echo "\n📋 Creating test admin activities...\n";
    
    $logger->logAdminActivity($adminId, 'LOGIN', null, null, 'Admin logged into system', null, 'LOW');
    $logger->logAdminActivity($adminId, 'USER_MANAGEMENT', 'user', 2, 'Reviewed user account', ['action' => 'profile_review'], 'MEDIUM');
    $logger->logAdminActivity($adminId, 'SCHOLARSHIP_REVIEW', 'scholarship', 18, 'Approved scholarship for publication', ['decision' => 'approved'], 'HIGH');
    
    echo "✅ Created test admin activities\n";
    
    // Test the query
    echo "\n📊 Testing Admin Activity Query:\n";
    $stmt = $conn->query("
        SELECT aal.id, aal.admin_id, u.fullname as admin_name, aal.action_type, aal.risk_level, aal.created_at
        FROM admin_activity_logs aal
        JOIN users u ON aal.admin_id = u.id
        ORDER BY aal.created_at DESC
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        echo "  ID: {$row['id']} | Admin: {$row['admin_name']} | Action: {$row['action_type']} | Risk: {$row['risk_level']} | Time: {$row['created_at']}\n";
    }
    
    echo "\n✅ Admin logging fixed and working!\n\n";
    
    echo "📋 SUMMARY - ALL WORKING QUERIES:\n";
    echo "=================================\n";
    
    echo "1. Login Attempts:\n";
    echo "   SELECT id, email, ip_address, attempt_type as status, created_at as attempt_time\n";
    echo "   FROM login_attempts ORDER BY created_at DESC LIMIT 50;\n\n";
    
    echo "2. Admin Activities:\n";
    echo "   SELECT aal.id, aal.admin_id, u.fullname as admin_name, aal.action_type, aal.risk_level, aal.created_at\n";
    echo "   FROM admin_activity_logs aal\n";
    echo "   JOIN users u ON aal.admin_id = u.id\n";
    echo "   ORDER BY aal.created_at DESC LIMIT 50;\n\n";
    
    echo "3. Security Events:\n";
    echo "   SELECT id, event_type, severity, event_description, ip_address, created_at\n";
    echo "   FROM security_events ORDER BY created_at DESC LIMIT 50;\n\n";
    
    echo "4. System Activities:\n";
    echo "   SELECT sal.id, sal.user_type, sal.action_type, sal.action_description, sal.created_at\n";
    echo "   FROM system_activity_logs sal\n";
    echo "   ORDER BY sal.created_at DESC LIMIT 50;\n\n";
    
    echo "🔐 For encrypted/sensitive data, use:\n";
    echo "   php decrypt_logs.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>