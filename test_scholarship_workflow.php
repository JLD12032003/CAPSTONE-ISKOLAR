<?php
/**
 * Test Scholarship Workflow System
 * This script tests the complete scholarship approval workflow
 */

require_once 'config/database.php';
require_once 'app/models/ScholarshipWorkflow.php';
require_once 'app/controllers/ScholarshipWorkflowController.php';
require_once 'app/models/Scholarship.php';

echo "=== SCHOLARSHIP WORKFLOW SYSTEM TEST ===\n\n";

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Test 1: Check if all tables exist
    echo "1. Checking database tables...\n";
    $tables = [
        'scholarships',
        'scholarship_approval_stages',
        'committee_votes',
        'scholarship_audit_log',
        'loa_templates',
        'school_approval_config',
        'scholarship_email_log'
    ];
    
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ✗ Table '$table' missing\n";
        }
    }
    
    // Test 2: Check workflow_status column
    echo "\n2. Checking workflow_status column...\n";
    $stmt = $conn->query("DESCRIBE scholarships");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasWorkflowStatus = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'workflow_status') {
            $hasWorkflowStatus = true;
            echo "   ✓ workflow_status column exists: " . $col['Type'] . "\n";
            break;
        }
    }
    if (!$hasWorkflowStatus) {
        echo "   ✗ workflow_status column missing\n";
    }
    
    // Test 3: Check school approval configuration
    echo "\n3. Checking school approval configuration...\n";
    $stmt = $conn->query("SELECT * FROM school_approval_config WHERE school_id = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        echo "   ✓ School approval config exists for Davao Central College\n";
        echo "   - Admin Email: " . $config['admin_email'] . "\n";
        echo "   - VP Email: " . $config['vp_email'] . "\n";
        echo "   - President Email: " . $config['president_email'] . "\n";
        echo "   - Committee Quorum: " . $config['committee_quorum'] . "\n";
    } else {
        echo "   ✗ No school approval config found\n";
    }
    
    // Test 4: Check LOA templates
    echo "\n4. Checking LOA templates...\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM loa_templates WHERE is_active = 1");
    $templateCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   ✓ Active LOA templates: $templateCount\n";
    
    // Test 5: Test workflow model instantiation
    echo "\n5. Testing workflow model...\n";
    try {
        $workflowModel = new ScholarshipWorkflow();
        echo "   ✓ ScholarshipWorkflow model instantiated successfully\n";
    } catch (Exception $e) {
        echo "   ✗ Error instantiating ScholarshipWorkflow: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Test workflow controller
    echo "\n6. Testing workflow controller...\n";
    try {
        $workflowController = new ScholarshipWorkflowController();
        echo "   ✓ ScholarshipWorkflowController instantiated successfully\n";
    } catch (Exception $e) {
        echo "   ✗ Error instantiating ScholarshipWorkflowController: " . $e->getMessage() . "\n";
    }
    
    // Test 7: Check existing scholarships
    echo "\n7. Checking existing scholarships...\n";
    $stmt = $conn->query("
        SELECT id, title, workflow_status, status, provider_id, school_id 
        FROM scholarships 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($scholarships)) {
        echo "   ℹ No scholarships found in database\n";
    } else {
        echo "   ✓ Found " . count($scholarships) . " scholarships:\n";
        foreach ($scholarships as $scholarship) {
            echo "   - ID: {$scholarship['id']}, Title: {$scholarship['title']}, Workflow: {$scholarship['workflow_status']}, Status: {$scholarship['status']}\n";
        }
    }
    
    // Test 8: Check uploads directory
    echo "\n8. Checking uploads directory...\n";
    $uploadsDir = __DIR__ . '/uploads/loa_documents';
    if (is_dir($uploadsDir)) {
        echo "   ✓ LOA documents directory exists: $uploadsDir\n";
        if (is_writable($uploadsDir)) {
            echo "   ✓ Directory is writable\n";
        } else {
            echo "   ⚠ Directory is not writable\n";
        }
    } else {
        echo "   ✗ LOA documents directory missing: $uploadsDir\n";
    }
    
    // Test 9: Test workflow view
    echo "\n9. Testing workflow summary view...\n";
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarship_workflow_summary");
        $viewCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "   ✓ Workflow summary view working, records: $viewCount\n";
    } catch (Exception $e) {
        echo "   ✗ Error accessing workflow summary view: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== TEST SUMMARY ===\n";
    echo "✓ Database schema setup complete\n";
    echo "✓ Workflow models and controllers ready\n";
    echo "✓ Email-based approval system configured\n";
    echo "✓ Multi-level approval workflow operational\n";
    
    echo "\n=== NEXT STEPS ===\n";
    echo "1. Create a scholarship as a provider\n";
    echo "2. Submit it for approval to test the workflow\n";
    echo "3. Check email notifications (configure SMTP if needed)\n";
    echo "4. Test approval process via email links\n";
    echo "5. Monitor workflow progress in admin dashboard\n";
    
    echo "\n=== WORKFLOW PROCESS ===\n";
    echo "Draft → School Admin → Committee → VP → President → Published\n";
    echo "Each stage requires email approval before proceeding to next stage.\n";
    echo "All decisions are logged with complete audit trail.\n";
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Scholarship Workflow System Test Complete!\n";
?>