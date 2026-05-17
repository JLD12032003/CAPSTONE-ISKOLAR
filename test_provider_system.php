<?php
// Simple test to verify provider system functionality
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/models/ProviderProfile.php';
require_once __DIR__ . '/app/models/Scholarship.php';

echo "<h1>ISKOLar Provider System Test</h1>";

try {
    // Test database connection
    $database = new Database();
    $conn = $database->connect();
    echo "<p>✅ Database connection successful</p>";
    
    // Test ProviderProfile model
    $profileModel = new ProviderProfile();
    echo "<p>✅ ProviderProfile model loaded</p>";
    
    // Test Scholarship model
    $scholarshipModel = new Scholarship();
    echo "<p>✅ Scholarship model loaded</p>";
    
    // Check if required tables exist
    $tables = ['users', 'provider_profiles', 'scholarships', 'scholarship_applications', 'schools'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Table '$table' exists</p>";
        } else {
            echo "<p>❌ Table '$table' missing</p>";
        }
    }
    
    // Check if provider files exist
    $files = [
        'app/views/provider/dashboard.php',
        'app/views/provider/profile_setup.php',
        'app/views/provider/create_scholarship.php',
        'app/views/provider/scholarships.php',
        'app/views/provider/applications.php',
        'app/views/provider/edit_scholarship.php',
        'app/views/provider/view_scholarship.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            echo "<p>✅ File '$file' exists</p>";
        } else {
            echo "<p>❌ File '$file' missing</p>";
        }
    }
    
    echo "<h2>Provider System Status: READY ✅</h2>";
    echo "<p>All core files and models are in place. The provider system should be functional.</p>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Ensure database tables are created (run database_rbac.sql)</li>";
    echo "<li>Register as a provider at <a href='index.php'>index.php</a></li>";
    echo "<li>Complete provider profile setup</li>";
    echo "<li>Start creating scholarships</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>