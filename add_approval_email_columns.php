<?php
/**
 * Add approval email columns to schools table
 * This ensures the delete functionality can properly clean up workflow records
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->connect();

echo "<h1>Adding Approval Email Columns to Schools Table</h1>\n";

try {
    // Check if columns already exist
    $stmt = $conn->prepare("SHOW COLUMNS FROM schools LIKE 'committee_email'");
    $stmt->execute();
    $committeeExists = $stmt->rowCount() > 0;
    
    $stmt = $conn->prepare("SHOW COLUMNS FROM schools LIKE 'vp_email'");
    $stmt->execute();
    $vpExists = $stmt->rowCount() > 0;
    
    $stmt = $conn->prepare("SHOW COLUMNS FROM schools LIKE 'president_email'");
    $stmt->execute();
    $presidentExists = $stmt->rowCount() > 0;
    
    // Add missing columns
    if (!$committeeExists) {
        $conn->exec("ALTER TABLE schools ADD COLUMN committee_email VARCHAR(255) NULL AFTER scholarship_coordinator_email");
        echo "<p style='color: green;'>✓ Added committee_email column</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ committee_email column already exists</p>\n";
    }
    
    if (!$vpExists) {
        $conn->exec("ALTER TABLE schools ADD COLUMN vp_email VARCHAR(255) NULL AFTER committee_email");
        echo "<p style='color: green;'>✓ Added vp_email column</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ vp_email column already exists</p>\n";
    }
    
    if (!$presidentExists) {
        $conn->exec("ALTER TABLE schools ADD COLUMN president_email VARCHAR(255) NULL AFTER vp_email");
        echo "<p style='color: green;'>✓ Added president_email column</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ president_email column already exists</p>\n";
    }
    
    // Update Davao Central College with default emails if they don't exist
    $stmt = $conn->prepare("
        UPDATE schools 
        SET 
            committee_email = COALESCE(committee_email, 'committee@davaocentralcollege.edu.ph'),
            vp_email = COALESCE(vp_email, 'vp@davaocentralcollege.edu.ph'),
            president_email = COALESCE(president_email, 'president@davaocentralcollege.edu.ph')
        WHERE id = 1
    ");
    $stmt->execute();
    
    echo "<p style='color: green;'>✓ Updated Davao Central College with default approval emails</p>\n";
    
    // Show current school configuration
    $stmt = $conn->prepare("SELECT school_name, email, committee_email, vp_email, president_email FROM schools WHERE id = 1");
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        echo "<h2>Current School Configuration:</h2>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Field</th><th>Value</th></tr>\n";
        echo "<tr><td>School Name</td><td>" . htmlspecialchars($school['school_name']) . "</td></tr>\n";
        echo "<tr><td>Admin Email</td><td>" . htmlspecialchars($school['email']) . "</td></tr>\n";
        echo "<tr><td>Committee Email</td><td>" . htmlspecialchars($school['committee_email']) . "</td></tr>\n";
        echo "<tr><td>VP Email</td><td>" . htmlspecialchars($school['vp_email']) . "</td></tr>\n";
        echo "<tr><td>President Email</td><td>" . htmlspecialchars($school['president_email']) . "</td></tr>\n";
        echo "</table>\n";
    }
    
    echo "<p style='color: green;'><strong>✓ All approval email columns are now properly configured!</strong></p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h3>Next Steps:</h3>\n";
echo "<ul>\n";
echo "<li>Run the delete functionality test: <code>test_delete_functionality.php</code></li>\n";
echo "<li>Test delete buttons in the provider and admin interfaces</li>\n";
echo "<li>Verify all related records are properly cleaned up</li>\n";
echo "</ul>\n";

?>