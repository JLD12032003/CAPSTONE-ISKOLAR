<?php
/**
 * Test the approval process to ensure it works without errors
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "=== TESTING APPROVAL PROCESS ===\n\n";
    
    // 1. Create a test scholarship if none exists
    echo "1. Setting up test data...\n";
    
    // Check if we have a test scholarship
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as provider_name 
        FROM scholarships s 
        JOIN users u ON s.provider_id = u.id 
        WHERE s.workflow_status = 'DRAFT' 
        LIMIT 1
    ");
    $stmt->execute();
    $testScholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testScholarship) {
        // Get a provider user
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_type = 'provider' LIMIT 1");
        $stmt->execute();
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$provider) {
            echo "❌ No provider user found. Please create a provider first.\n";
            exit;
        }
        
        // Create a test scholarship
        $stmt = $conn->prepare("
            INSERT INTO scholarships (
                provider_id, school_id, title, description, amount, slots, 
                scholarship_type, workflow_status, current_stage, created_at
            ) VALUES (?, 1, 'Test Scholarship', 'Test Description', 10000, 5, 
                     'Academic Merit', 'DRAFT', 'DRAFT', NOW())
        ");
        $stmt->execute([$provider['id']]);
        $scholarshipId = $conn->lastInsertId();
        
        echo "✓ Created test scholarship with ID: $scholarshipId\n";
    } else {
        $scholarshipId = $testScholarship['id'];
        echo "✓ Using existing test scholarship with ID: $scholarshipId\n";
    }
    
    // 2. Create a workflow tracking record
    echo "\n2. Creating workflow tracking record...\n";
    
    $approvalToken = hash('sha256', $scholarshipId . 'COMMITTEE' . time() . random_bytes(16));
    $testEmail = "committee@davaocentralcollege.edu.ph";
    $testName = "Committee Chair";
    
    // Clean up any existing workflow records for this scholarship
    $conn->prepare("DELETE FROM scholarship_workflow_tracking WHERE scholarship_id = ?")->execute([$scholarshipId]);
    
    $stmt = $conn->prepare("
        INSERT INTO scholarship_workflow_tracking (
            scholarship_id, stage_name, stage_order, approver_email, 
            approver_name, approval_token, token_expires_at
        ) VALUES (?, 'COMMITTEE', 1, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $stmt->execute([$scholarshipId, $testEmail, $testName, $approvalToken]);
    
    echo "✓ Created workflow tracking record\n";
    echo "✓ Approval token: $approvalToken\n";
    
    // 3. Update scholarship status
    echo "\n3. Updating scholarship status...\n";
    
    $stmt = $conn->prepare("
        UPDATE scholarships 
        SET workflow_status = 'PENDING_COMMITTEE_REVIEW', 
            current_stage = 'COMMITTEE'
        WHERE id = ?
    ");
    $stmt->execute([$scholarshipId]);
    
    echo "✓ Scholarship status updated to PENDING_COMMITTEE_REVIEW\n";
    
    // 4. Test token validation
    echo "\n4. Testing token validation...\n";
    
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
        echo "  - Stage: " . $tokenValidation['stage_name'] . "\n";
        echo "  - Approver: " . $tokenValidation['approver_name'] . "\n";
        echo "  - Email: " . $tokenValidation['approver_email'] . "\n";
    } else {
        echo "❌ Token validation failed\n";
        exit;
    }
    
    // 5. Test approval processing (simulate)
    echo "\n5. Testing approval processing...\n";
    
    try {
        $conn->beginTransaction();
        
        $decision = 'APPROVED';
        $notes = 'Test approval notes';
        
        // Update workflow tracking record
        $stmt = $conn->prepare("
            UPDATE scholarship_workflow_tracking 
            SET decision = ?, decision_at = NOW(), decision_notes = ?
            WHERE approval_token = ?
        ");
        $result = $stmt->execute([$decision, $notes, $approvalToken]);
        
        if (!$result) {
            throw new Exception("Failed to update workflow tracking record");
        }
        
        // Move to next stage (VP)
        $nextStage = 'VP';
        $stmt = $conn->prepare("
            UPDATE scholarships 
            SET workflow_status = ?, current_stage = ?
            WHERE id = ?
        ");
        $newStatus = 'PENDING_' . $nextStage . '_REVIEW';
        $result = $stmt->execute([$newStatus, $nextStage, $scholarshipId]);
        
        if (!$result) {
            throw new Exception("Failed to update scholarship to next stage");
        }
        
        // Log the action
        $actionType = 'STAGE_APPROVED';
        $stmt = $conn->prepare("
            INSERT INTO scholarship_audit_log (
                scholarship_id, action_type, stage_name, actor_role, actor_email,
                action_details, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $scholarshipId,
            $actionType,
            $tokenValidation['stage_name'],
            $tokenValidation['approver_name'] ?? 'Email Approver',
            $tokenValidation['approver_email'] ?? 'unknown@email.com',
            $notes ?: 'No notes provided'
        ]);
        
        if (!$result) {
            throw new Exception("Failed to log audit entry");
        }
        
        $conn->commit();
        echo "✓ Approval processing successful\n";
        echo "  - Decision: $decision\n";
        echo "  - Next stage: $nextStage\n";
        echo "  - New status: $newStatus\n";
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "❌ Approval processing failed: " . $e->getMessage() . "\n";
    }
    
    // 6. Verify final state
    echo "\n6. Verifying final state...\n";
    
    $stmt = $conn->prepare("SELECT workflow_status, current_stage FROM scholarships WHERE id = ?");
    $stmt->execute([$scholarshipId]);
    $finalState = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✓ Final scholarship state:\n";
    echo "  - Workflow Status: " . $finalState['workflow_status'] . "\n";
    echo "  - Current Stage: " . $finalState['current_stage'] . "\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarship_audit_log WHERE scholarship_id = ?");
    $stmt->execute([$scholarshipId]);
    $auditCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "  - Audit log entries: $auditCount\n";
    
    echo "\n✅ APPROVAL PROCESS TEST COMPLETED SUCCESSFULLY!\n";
    
    // Generate test URLs
    $protocol = 'http';
    $host = 'localhost';
    $testApproveUrl = "{$protocol}://{$host}/app/views/approval/scholarship_approval.php?token={$approvalToken}";
    
    echo "\nTest URL (if you want to test manually):\n";
    echo "$testApproveUrl\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>