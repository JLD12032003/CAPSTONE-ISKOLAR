<?php
/**
 * Create admin_email_favorites table for saving frequently used emails
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Creating admin_email_favorites table...\n";
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS admin_email_favorites (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_admin_email (admin_id, email),
            INDEX idx_admin_id (admin_id)
        )
    ");
    
    echo "   ✓ admin_email_favorites table created successfully\n";
    
    // Insert some default favorite emails for testing
    $stmt = $conn->prepare("
        INSERT IGNORE INTO admin_email_favorites (admin_id, email, name) VALUES 
        (?, 'committee@davaocentralcollege.edu.ph', 'Scholarship Committee'),
        (?, 'vp@davaocentralcollege.edu.ph', 'Vice President'),
        (?, 'president@davaocentralcollege.edu.ph', 'School President'),
        (?, 'finance@davaocentralcollege.edu.ph', 'Finance Officer'),
        (?, 'registrar@davaocentralcollege.edu.ph', 'Registrar')
    ");
    
    // Get first admin user
    $adminStmt = $conn->query("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1");
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $adminId = $admin['id'];
        $stmt->execute([$adminId, $adminId, $adminId, $adminId, $adminId]);
        echo "   ✓ Default favorite emails added for admin ID: $adminId\n";
    }
    
    echo "\n🎉 Admin email favorites system setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>