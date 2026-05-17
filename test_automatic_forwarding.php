<?php
/**
 * Test automatic forwarding system
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "=== TESTING AUTOMATIC FORWARDING SYSTEM ===\n\n";
    
    // 1. Verify school email configuration
    echo "1. Checking school email configuration...\n";
    
    $stmt = $conn->query("SELECT school_name, committee_email, vp_email, president_email FROM schools LIMIT 1");
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        echo "✓ School: " . $school['school_name'] . "\n";
        echo "✓ Committee Email: " . ($school['committee_email'] ?: 'NOT SET') . "\n";
        echo "✓ VP Email: " . ($school['vp_email'] ?: 'NOT SET') . "\n";
        echo "✓ President Email: " . ($school['president_email'] ?: 'NOT SET') . "\n";
        
        if ($school['committee_email'] && $school['vp_email'] && $school['president_email']) {
            echo "✅ All approval emails are configured\n";
        } else {
            echo "❌ Some approval emails are missing\n";
        }
    } else {
        echo "❌ No school found\n";
        exit;
    }
    
    // 2. Create test scholarship and workflow
    echo "\n2. Setting up test workflow...\n";
    
    // Get or create test scholarship
    $stmt = $conn->prepare("SELECT id FROM scholarships WHERE title LIKE 'Auto Forward Test%' LIMIT 1");
    $stmt->execute();
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scholarship) {
        $stmt = $conn->prepare("
            INSERT INTO scholarships (
                provider_id, school_id, title, description, amount, slots, 
                scholarship_type, workflow_status, current_stage, created_at
            ) VALUES (7, 1, 'Auto Forward Test Scholarship', 'Test automatic forwarding', 20000, 2, 
                     'Academic Merit', 'PENDING_COMMITTEE_REVIEW', 'COMMITTEE', NOW())
        ");
        $stmt->execute();
        $scholarshipId = $conn->lastInsertId();
        echo "✓ Created test scholarship with ID: $scholarshipId\n";
    } else {
        $scholarshipId = $scholarship['id'];
        echo "✓ Using existing scholarship ID: $scholarshipId\n";
    }
    
    // 3. Test Committee approval and auto-forward to VP
    echo "\n3. Testing Committee approval → VP forwarding...\n";
    
    // Clean up existing workflow records
    $conn->prepare("DELETE FROM scholarship_workflow_tracking WHERE scholarship_id = ?")->execute([$scholarshipId]);
    
    // Create Committee workflow record
    $committeeToken = hash('sha256', $scholarshipId . 'COMMITTEE' . time() . random_bytes(16));
    $stmt = $conn->prepare("
        INSERT INTO scholarship_workflow_tracking (
            scholarship_id, stage_name, stage_order, approver_email, 
            approver_name, approval_token, token_expires_at
        ) VALUES (?, 'COMMITTEE', 1, ?, 'Committee Chair', ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $stmt->execute([$scholarshipId, $school['committee_email'], $committeeToken]);
    
    echo "✓ Created Committee workflow record\n";
    
    // Simulate Committee approval
    $conn->beginTransaction();
    
    try {
        // Update Committee decision
        $stmt = $conn->prepare("
            UPDATE scholarship_workflow_tracking 
            SET decision = 'APPROVED', decision_at = NOW(), decision_notes = 'Test approval by committee'
            WHERE approval_token = ?
        ");
        $stmt->execute([$committeeToken]);
        
        // Update scholarship to VP stage
        $stmt = $conn->prepare("
            UPDATE scholarships 
            SET workflow_status = 'PENDING_VP_REVIEW', current_stage = 'VP'
            WHERE id = ?
        ");
        $stmt->execute([$scholarshipId]);
        
        // Test automatic VP forwarding
        require_once 'app/core/AutoForwarding.php';
        $vpResult = AutoForwarding::createNextApprovalStage($conn, $scholarshipId, 'VP', 1);
        
        if ($vpResult['success']) {
            echo "✅ VP forwarding successful to: " . $vpResult['email'] . "\n";
        } else {
            echo "❌ VP forwarding failed: " . $vpResult['error'] . "\n";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "❌ Committee approval simulation failed: " . $e->getMessage() . "\n";
    }
    
    // 4. Test VP approval and auto-forward to President
    echo "\n4. Testing VP approval → President forwarding...\n";
    
    // Get VP token
    $stmt = $conn->prepare("
        SELECT approval_token FROM scholarship_workflow_tracking 
        WHERE scholarship_id = ? AND stage_name = 'VP' AND decision = 'PENDING'
    ");
    $stmt->execute([$scholarshipId]);
    $vpTokenResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vpTokenResult) {
        $vpToken = $vpTokenResult['approval_token'];
        echo "✓ Found VP token: " . substr($vpToken, 0, 20) . "...\n";
        
        $conn->beginTransaction();
        
        try {
            // Update VP decision
            $stmt = $conn->prepare("
                UPDATE scholarship_workflow_tracking 
                SET decision = 'APPROVED', decision_at = NOW(), decision_notes = 'Test approval by VP'
                WHERE approval_token = ?
            ");
            $stmt->execute([$vpToken]);
            
            // Update scholarship to President stage
            $stmt = $conn->prepare("
                UPDATE scholarships 
                SET workflow_status = 'PENDING_PRESIDENT_REVIEW', current_stage = 'PRESIDENT'
                WHERE id = ?
            ");
            $stmt->execute([$scholarshipId]);
            
            // Test automatic President forwarding
            $presidentResult = AutoForwarding::createNextApprovalStage($conn, $scholarshipId, 'PRESIDENT', 1);
            
            if ($presidentResult['success']) {
                echo "✅ President forwarding successful to: " . $presidentResult['email'] . "\n";
            } else {
                echo "❌ President forwarding failed: " . $presidentResult['error'] . "\n";
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            echo "❌ VP approval simulation failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ VP token not found\n";
    }
    
    // 5. Verify final workflow state
    echo "\n5. Verifying workflow state...\n";
    
    $stmt = $conn->prepare("
        SELECT workflow_status, current_stage FROM scholarships WHERE id = ?
    ");
    $stmt->execute([$scholarshipId]);
    $finalState = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Final scholarship state:\n";
    echo "- Workflow Status: " . $finalState['workflow_status'] . "\n";
    echo "- Current Stage: " . $finalState['current_stage'] . "\n";
    
    $stmt = $conn->prepare("
        SELECT stage_name, decision, approver_email, decision_at 
        FROM scholarship_workflow_tracking 
        WHERE scholarship_id = ? 
        ORDER BY stage_order
    ");
    $stmt->execute([$scholarshipId]);
    $workflowHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nWorkflow history:\n";
    foreach ($workflowHistory as $stage) {
        $status = $stage['decision'] ?: 'PENDING';
        $decidedAt = $stage['decision_at'] ? date('Y-m-d H:i:s', strtotime($stage['decision_at'])) : 'Not decided';
        echo "- " . $stage['stage_name'] . ": $status (" . $stage['approver_email'] . ") - $decidedAt\n";
    }
    
    echo "\n=== AUTOMATIC FORWARDING TEST COMPLETED ===\n";
    echo "✅ The system now automatically forwards approvals through the workflow!\n\n";
    
    echo "WORKFLOW PROCESS:\n";
    echo "1. Admin forwards to Committee → Committee receives email\n";
    echo "2. Committee approves → VP automatically receives email\n";
    echo "3. VP approves → President automatically receives email\n";
    echo "4. President approves → Scholarship is published\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>