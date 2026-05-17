<?php
/**
 * Fix audit log ENUM values to include missing action types
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "Updating scholarship_audit_log action_type ENUM...\n";
    
    // Update the ENUM to include all necessary action types
    $alterQuery = "
    ALTER TABLE scholarship_audit_log 
    MODIFY COLUMN action_type ENUM(
        'SCHOLARSHIP_CREATED',
        'SUBMITTED_FOR_REVIEW',
        'STAGE_APPROVED',
        'STAGE_REJECTED',
        'FORWARDED_TO_NEXT_STAGE',
        'PUBLISHED',
        'WORKFLOW_COMPLETED',
        'EMAIL_SENT',
        'EMAIL_FORWARDED',
        'TOKEN_GENERATED',
        'VOTE_RECORDED'
    ) NOT NULL
    ";
    
    $conn->exec($alterQuery);
    echo "✓ Updated action_type ENUM successfully\n";
    
    // Test the new ENUM values
    echo "\nTesting new ENUM values...\n";
    $testValues = ['STAGE_APPROVED', 'STAGE_REJECTED', 'EMAIL_FORWARDED'];
    
    foreach ($testValues as $value) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO scholarship_audit_log 
                (scholarship_id, action_type, stage_name, actor_role, actor_email, action_details) 
                VALUES (1, ?, 'TEST', 'TEST', 'test@test.com', 'Test entry')
            ");
            $stmt->execute([$value]);
            echo "✓ $value - OK\n";
            
            // Clean up test entry
            $conn->exec("DELETE FROM scholarship_audit_log WHERE action_type = '$value' AND stage_name = 'TEST'");
        } catch (Exception $e) {
            echo "❌ $value - Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Audit log ENUM fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>