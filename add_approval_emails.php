<?php
/**
 * Add VP and President email fields to schools table
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Adding VP and President email fields to schools table...\n";
    
    // Add email fields for approval workflow
    $alterQueries = [
        "ALTER TABLE schools ADD COLUMN IF NOT EXISTS vp_email VARCHAR(255) NULL COMMENT 'Vice President email for approval workflow'",
        "ALTER TABLE schools ADD COLUMN IF NOT EXISTS president_email VARCHAR(255) NULL COMMENT 'President email for approval workflow'",
        "ALTER TABLE schools ADD COLUMN IF NOT EXISTS committee_email VARCHAR(255) NULL COMMENT 'Committee email for approval workflow'"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $conn->exec($query);
            echo "✓ Executed: " . substr($query, 0, 60) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column already exists: " . substr($query, 0, 60) . "...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Update Davao Central College with sample email addresses
    echo "\nUpdating Davao Central College with sample approval emails...\n";
    
    $stmt = $conn->prepare("
        UPDATE schools 
        SET committee_email = 'committee@davaocentralcollege.edu.ph',
            vp_email = 'vp@davaocentralcollege.edu.ph',
            president_email = 'president@davaocentralcollege.edu.ph'
        WHERE school_name LIKE '%Davao Central%' OR id = 1
    ");
    $stmt->execute();
    
    echo "✓ Updated school with approval email addresses\n";
    
    // Verify the changes
    echo "\nVerifying changes...\n";
    $stmt = $conn->query("SELECT school_name, committee_email, vp_email, president_email FROM schools LIMIT 1");
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        echo "School: " . $school['school_name'] . "\n";
        echo "Committee Email: " . ($school['committee_email'] ?: 'Not set') . "\n";
        echo "VP Email: " . ($school['vp_email'] ?: 'Not set') . "\n";
        echo "President Email: " . ($school['president_email'] ?: 'Not set') . "\n";
    }
    
    echo "\n✅ Approval email configuration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>