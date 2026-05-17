<?php
// Test Admin Registration System
echo "<h1>Admin Registration Test</h1>";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=localhost;dbname=ISKOLAR_101", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connected to database</p>";
    
    // Check if users table has school_id column
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasSchoolId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'school_id') {
            $hasSchoolId = true;
            break;
        }
    }
    
    if ($hasSchoolId) {
        echo "<p>✅ Users table has school_id column</p>";
    } else {
        echo "<p>❌ Users table missing school_id column - adding it now...</p>";
        $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT NULL AFTER user_type");
        $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL");
        echo "<p>✅ Added school_id column to users table</p>";
    }
    
    // Check available schools
    $stmt = $pdo->query("SELECT id, school_name FROM schools WHERE is_active = 1");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Schools for Admin Registration:</h3>";
    echo "<ul>";
    foreach ($schools as $school) {
        echo "<li>ID: {$school['id']} - {$school['school_name']}</li>";
    }
    echo "</ul>";
    
    // Check existing admin users
    $stmt = $pdo->query("SELECT id, fullname, email, user_type, school_id FROM users WHERE user_type = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Existing Admin Users:</h3>";
    if (empty($admins)) {
        echo "<p>No admin users found.</p>";
    } else {
        echo "<ul>";
        foreach ($admins as $admin) {
            $schoolName = 'No School';
            if ($admin['school_id']) {
                $schoolStmt = $pdo->prepare("SELECT school_name FROM schools WHERE id = ?");
                $schoolStmt->execute([$admin['school_id']]);
                $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
                $schoolName = $school ? $school['school_name'] : 'Unknown School';
            }
            echo "<li>{$admin['fullname']} ({$admin['email']}) - School: {$schoolName}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>🎉 Admin Registration System Ready!</h2>";
    echo "<p>Users can now register as school administrators by:</p>";
    echo "<ol>";
    echo "<li>Going to the registration form</li>";
    echo "<li>Filling in their details</li>";
    echo "<li>Checking the 'I am a School Administrator' checkbox</li>";
    echo "<li>Selecting their school from the dropdown</li>";
    echo "<li>Completing the registration process</li>";
    echo "</ol>";
    
    echo "<h3>Test the Registration:</h3>";
    echo "<p><a href='index.php' target='_blank'>Open Registration Form</a></p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>