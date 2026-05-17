<?php
/**
 * Add workflow tracking columns to scholarships table
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Adding workflow tracking columns to scholarships table...\n";
    
    // Add workflow tracking columns
    $alterQueries = [
        "ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS forwarded_at TIMESTAMP NULL",
        "ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS forwarded_to VARCHAR(255) NULL",
        "ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS forwarded_by INT NULL",
        "ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS current_stage VARCHAR(50) DEFAULT 'DRAFT'",
        "ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS approval_progress TEXT NULL COMMENT 'JSON tracking approval progress'"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $conn->exec($query);
            echo "✓ Executed: " . substr($query, 0, 50) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column already exists: " . substr($query, 0, 50) . "...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Create workflow tracking table if not exists
    $workflowTableQuery = "
    CREATE TABLE IF NOT EXISTS scholarship_workflow_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scholarship_id INT NOT NULL,
        stage_name VARCHAR(50) NOT NULL,
        stage_order INT NOT NULL,
        approver_email VARCHAR(255) NOT NULL,
        approver_name VARCHAR(255) NULL,
        approval_token VARCHAR(255) NOT NULL UNIQUE,
        token_expires_at TIMESTAMP NOT NULL,
        decision ENUM('APPROVED', 'REJECTED', 'PENDING') DEFAULT 'PENDING',
        decision_at TIMESTAMP NULL,
        decision_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
        INDEX idx_scholarship_stage (scholarship_id, stage_name),
        INDEX idx_token (approval_token)
    )";
    
    $conn->exec($workflowTableQuery);
    echo "✓ Created scholarship_workflow_tracking table\n";
    
    echo "\n✅ Workflow tracking system setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>