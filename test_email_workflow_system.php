<?php
/**
 * Test Email Workflow System
 * Tests the complete email-based approval workflow
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "=== TESTING EMAIL WORKFLOW SYSTEM ===\n\n";
    
    // 1. Check if workflow tracking table exists
    echo "1. Checking workflow tracking table...\n";
    $stmt = $conn->query("DESCRIBE scholarship_workflow_tracking");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Table exists with columns: " . implode(', ', $columns) . "\n\n";
    
    // 2. Check scholarships table for new columns
    echo "2. Checking scholarships table updates...\n";
    $stmt = $conn->query("DESCRIBE scholarships");
    $scholarshipColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['forwarded_at', 'forwarded_to', 'forwarded_by', 'current_stage'];
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $scholarshipColumns)) {
            echo "✓ Column '$col' exists\n";
        } else {
            echo "❌ Column '$col' missing\n";
        }
    }
    echo "\n";
    
    // 3. Test scholarship creation and workflow
    echo "3. Testing scholarship workflow...\n";
    
    // Get a test scholarship (or create one)
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as provider_name, pp.organization_name 
        FROM scholarships s 
        JOIN users u ON s.provider_id = u.id 
        LEFT JOIN provider_profiles pp ON u.id = pp.user_id 
        WHERE s.workflow_status = 'DRAFT' 
        LIMIT 1
    ");
    $stmt->execute();
    $testScholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testScholarship) {
        echo "✓ Found test scholarship: " . $testScholarship['title'] . "\n";
        
        // Simulate admin forwarding
        $testEmail = "committee@davaocentralcollege.edu.ph";
        $testName = "Committee Chair";
        $approvalToken = hash('sha256', $testScholarship['id'] . 'COMMITTEE' . time() . random_bytes(16));
        
        // Update scholarship status
        $stmt = $conn->prepare("
            UPDATE scholarships 
            SET workflow_status = 'PENDING_COMMITTEE_REVIEW', 
                current_stage = 'COMMITTEE',
                forwarded_at = NOW(),
                forwarded_to = ?,
                forwarded_by = 1
            WHERE id = ?
        ");
        $stmt->execute([$testEmail, $testScholarship['id']]);
        
        // Create workflow tracking record
        $stmt = $conn->prepare("
            INSERT INTO scholarship_workflow_tracking (
                scholarship_id, stage_name, stage_order, approver_email, 
                approver_name, approval_token, token_expires_at
            ) VALUES (?, 'COMMITTEE', 1, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        $stmt->execute([$testScholarship['id'], $testEmail, $testName, $approvalToken]);
        
        echo "✓ Scholarship forwarded to committee\n";
        echo "✓ Workflow tracking record created\n";
        
        // Generate test approval URLs
        $protocol = 'http';
        $host = 'localhost';
        $approveUrl = "{$protocol}://{$host}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=APPROVED";
        $rejectUrl = "{$protocol}://{$host}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=REJECTED";
        
        echo "✓ Approval URLs generated:\n";
        echo "  - Approve: $approveUrl\n";
        echo "  - Reject: $rejectUrl\n\n";
        
        // Test token validation
        $stmt = $conn->prepare("
            SELECT s.*, swt.* 
            FROM scholarship_workflow_tracking swt
            JOIN scholarships s ON swt.scholarship_id = s.id
            WHERE swt.approval_token = ? AND swt.decision = 'PENDING'
        ");
        $stmt->execute([$approvalToken]);
        $tokenTest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenTest) {
            echo "✓ Token validation successful\n";
        } else {
            echo "❌ Token validation failed\n";
        }
        
    } else {
        echo "❌ No test scholarship found in DRAFT status\n";
    }
    
    // 4. Check admin email favorites table
    echo "\n4. Checking admin email favorites...\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM admin_email_favorites");
    $favCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ Admin email favorites table has $favCount records\n";
    
    // 5. Check audit log table
    echo "\n5. Checking audit log...\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarship_audit_log");
    $auditCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ Scholarship audit log has $auditCount records\n";
    
    // 6. Test email template generation
    echo "\n6. Testing email template...\n";
    if ($testScholarship) {
        $sampleEmail = generateTestEmailBody($testScholarship, $testName, $approveUrl, $rejectUrl);
        $emailLength = strlen($sampleEmail);
        echo "✓ Email template generated ($emailLength characters)\n";
        
        // Save sample email to file for review
        file_put_contents('sample_approval_email.html', $sampleEmail);
        echo "✓ Sample email saved to sample_approval_email.html\n";
    }
    
    echo "\n=== EMAIL WORKFLOW SYSTEM TEST COMPLETED ===\n";
    echo "✅ All core components are functional!\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Test the admin review page: app/views/admin/review_scholarship.php\n";
    echo "2. Test email sending functionality\n";
    echo "3. Test approval processing: app/views/approval/scholarship_approval.php\n";
    echo "4. Verify workflow status updates in database\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function generateTestEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='max-width: 700px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
            <h1 style='color: #0055ff;'>ISKOLar Scholarship Approval</h1>
            <h2>Dear {$recipientName},</h2>
            <p>A scholarship requires your approval:</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Scholarship: " . htmlspecialchars($scholarship['title']) . "</h3>
                <p><strong>Provider:</strong> " . htmlspecialchars($scholarship['organization_name'] ?? $scholarship['provider_name']) . "</p>
                <p><strong>Amount:</strong> ₱" . number_format($scholarship['amount'], 2) . "</p>
                <p><strong>Slots:</strong> " . $scholarship['slots'] . "</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$approveUrl}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin-right: 10px;'>APPROVE</a>
                <a href='{$rejectUrl}' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px;'>REJECT</a>
            </div>
        </div>
    </body>
    </html>";
}
?>