<?php
/**
 * Add partnership_letter column to scholarships table
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Adding partnership_letter column to scholarships table...\n";
    
    // Check if column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM scholarships LIKE 'partnership_letter'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ partnership_letter column already exists\n";
    } else {
        // Add the column
        $conn->exec("ALTER TABLE scholarships ADD COLUMN partnership_letter TEXT NULL AFTER other_requirements");
        echo "   ✓ partnership_letter column added successfully\n";
    }
    
    echo "\n🎉 Partnership letter column setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>