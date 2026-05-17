<?php
/**
 * Setup Scholarship Workflow Database Schema
 * Run this script to create all necessary tables for the scholarship approval workflow
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Setting up Scholarship Workflow Database Schema...\n\n";
    
    // Read and execute the SQL schema
    $sqlFile = 'database/scholarship_approval_workflow_schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $conn->exec($statement);
            $successCount++;
            echo "✓ Executed statement successfully\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "✗ Error executing statement: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Successful statements: $successCount\n";
    echo "Failed statements: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n🎉 Scholarship Workflow Database Schema setup completed successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Test the workflow by creating a scholarship as a provider\n";
        echo "2. Submit it for approval to trigger the email workflow\n";
        echo "3. Check admin dashboard for workflow monitoring\n";
    } else {
        echo "\n⚠️  Some errors occurred during setup. Please review the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>