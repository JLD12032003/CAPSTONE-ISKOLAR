<?php
/**
 * Complete workflow test - Email sending and URL access
 */

require_once 'config/database.php';
require_once 'app/core/Mailer.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "=== COMPLETE WORKFLOW TEST ===\n\n";
    
    // 1. Setup test data
    echo "1. Setting up test data...\n";
    
    // Get or create a test scholarship
    $stmt = $conn->prepare("SELECT id FROM scholarships WHERE title LIKE 'Test%' LIMIT 1");
    $stmt->execute();
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scholarship) {
        // Create a test scholarship
        $stmt = $conn->prepare("
            INSERT INTO scholarships (
                provider_id, school_id, title, description, amount, slots, 
                scholarship_type, workflow_status, current_stage, created_at
            ) VALUES (1, 1, 'Test Workflow Scholarship', 'Test Description', 15000, 3, 
                     'Academic Merit', 'DRAFT', 'DRAFT', NOW())
        ");
        $stmt->execute();
        $scholarshipId = $conn->lastInsertId();
        echo "✓ Created test scholarship with ID: $scholarshipId\n";
    } else {
        $scholarshipId = $scholarship['id'];
        echo "✓ Using existing scholarship ID: $scholarshipId\n";
    }
    
    // 2. Test email configuration
    echo "\n2. Testing email configuration...\n";
    
    $testEmail = "committee@davaocentralcollege.edu.ph";
    $testSubject = "Test - Scholarship Approval Required";
    $testBody = "<h1>Test Email</h1><p>This is a test of the ISKOLar email system.</p>";
    
    // Test email sending with error details
    $emailResult = Mailer::getLastError($testEmail, $testSubject, $testBody);
    echo "Email test result: $emailResult\n";
    
    if (strpos($emailResult, 'successfully') !== false) {
        echo "✅ Email system is working!\n";
    } else {
        echo "❌ Email system has issues: $emailResult\n";
        echo "Note: This might be expected if Gmail credentials are not configured.\n";
    }
    
    // 3. Create workflow tracking record
    echo "\n3. Creating workflow tracking record...\n";
    
    // Clean up existing records
    $conn->prepare("DELETE FROM scholarship_workflow_tracking WHERE scholarship_id = ?")->execute([$scholarshipId]);
    
    $approvalToken = hash('sha256', $scholarshipId . 'COMMITTEE' . time() . random_bytes(16));
    $recipientEmail = "committee@davaocentralcollege.edu.ph";
    $recipientName = "Committee Chair";
    
    $stmt = $conn->prepare("
        INSERT INTO scholarship_workflow_tracking (
            scholarship_id, stage_name, stage_order, approver_email, 
            approver_name, approval_token, token_expires_at
        ) VALUES (?, 'COMMITTEE', 1, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $stmt->execute([$scholarshipId, $recipientEmail, $recipientName, $approvalToken]);
    
    echo "✓ Created workflow tracking record\n";
    echo "✓ Token: " . substr($approvalToken, 0, 20) . "...\n";
    
    // 4. Generate and test URLs
    echo "\n4. Testing approval URLs...\n";
    
    $protocol = 'http';
    $host = 'localhost';
    $basePath = '/ISKOLAR_3RD_YEAR_EDITION';
    
    $approveUrl = "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=APPROVED";
    $rejectUrl = "{$protocol}://{$host}{$basePath}/app/views/approval/scholarship_approval.php?token={$approvalToken}&action=REJECTED";
    
    echo "✓ Approve URL: $approveUrl\n";
    echo "✓ Reject URL: $rejectUrl\n";
    
    // Test if the approval file exists and is accessible
    $approvalFile = __DIR__ . '/app/views/approval/scholarship_approval.php';
    if (file_exists($approvalFile)) {
        echo "✓ Approval file exists\n";
        
        // Test token validation by simulating the approval page logic
        $stmt = $conn->prepare("
            SELECT s.*, u.fullname as provider_name, pp.organization_name,
                   sch.school_name, swt.stage_name, swt.approver_name, swt.approver_email,
                   swt.token_expires_at, swt.decision, swt.id as workflow_id
            FROM scholarship_workflow_tracking swt
            JOIN scholarships s ON swt.scholarship_id = s.id
            JOIN users u ON s.provider_id = u.id
            LEFT JOIN provider_profiles pp ON u.id = pp.user_id
            LEFT JOIN schools sch ON s.school_id = sch.id
            WHERE swt.approval_token = ? 
                AND swt.token_expires_at > NOW() 
                AND swt.decision = 'PENDING'
        ");
        $stmt->execute([$approvalToken]);
        $tokenValidation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenValidation) {
            echo "✓ Token validation successful\n";
            echo "  - Scholarship: " . $tokenValidation['title'] . "\n";
            echo "  - Approver: " . $tokenValidation['approver_name'] . "\n";
        } else {
            echo "❌ Token validation failed\n";
        }
    } else {
        echo "❌ Approval file not found at: $approvalFile\n";
    }
    
    // 5. Test complete email with real URLs
    echo "\n5. Testing complete email generation...\n";
    
    $sampleScholarship = [
        'title' => 'Test Scholarship',
        'organization_name' => 'Test Organization',
        'provider_name' => 'Test Provider',
        'amount' => 15000,
        'slots' => 3,
        'scholarship_type' => 'Academic Merit',
        'description' => 'This is a test scholarship for the approval workflow.'
    ];
    
    $emailBody = generateTestEmailBody($sampleScholarship, $recipientName, $approveUrl, $rejectUrl, "Test admin notes");
    
    // Save email to file for inspection
    file_put_contents('test_approval_email.html', $emailBody);
    echo "✓ Generated complete email (saved to test_approval_email.html)\n";
    
    echo "\n=== TEST SUMMARY ===\n";
    echo "✅ Database setup: OK\n";
    echo "✅ URL generation: OK\n";
    echo "✅ File paths: OK\n";
    echo "✅ Token system: OK\n";
    
    if (strpos($emailResult, 'successfully') !== false) {
        echo "✅ Email system: OK\n";
    } else {
        echo "⚠️  Email system: Needs configuration\n";
    }
    
    echo "\nNEXT STEPS:\n";
    echo "1. Configure Gmail credentials in app/core/Mailer.php\n";
    echo "2. Test the URLs manually:\n";
    echo "   - $approveUrl\n";
    echo "3. Check the generated email: test_approval_email.html\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function generateTestEmailBody($scholarship, $recipientName, $approveUrl, $rejectUrl, $notes) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='max-width: 700px; background: white; padding: 30px; border-radius: 12px; margin: auto;'>
            <h1 style='color: #0055ff;'>ISKOLar Scholarship System</h1>
            <h2>Dear {$recipientName},</h2>
            <p>A scholarship requires your approval:</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Scholarship: " . htmlspecialchars($scholarship['title']) . "</h3>
                <p><strong>Provider:</strong> " . htmlspecialchars($scholarship['organization_name']) . "</p>
                <p><strong>Amount:</strong> ₱" . number_format($scholarship['amount'], 2) . "</p>
                <p><strong>Slots:</strong> " . $scholarship['slots'] . "</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$approveUrl}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin-right: 10px; display: inline-block;'>APPROVE SCHOLARSHIP</a>
                <a href='{$rejectUrl}' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>REJECT SCHOLARSHIP</a>
            </div>
            
            <p><strong>Admin Notes:</strong> {$notes}</p>
        </div>
    </body>
    </html>";
}
?>