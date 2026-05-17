<?php
/**
 * Debug Scholarships Visibility Issue
 * Investigate why published scholarships aren't appearing in student dashboard
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔍 Debugging Scholarship Visibility Issue...\n\n";
    
    // 1. Check all scholarships in the database
    echo "1. All scholarships in database:\n";
    $stmt = $conn->prepare("
        SELECT id, title, status, workflow_status, application_start, application_end, 
               published_at, created_at
        FROM scholarships 
        ORDER BY id DESC
    ");
    $stmt->execute();
    $allScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allScholarships as $scholarship) {
        $appStart = $scholarship['application_start'] ?? 'NULL';
        $appEnd = $scholarship['application_end'] ?? 'NULL';
        $published = $scholarship['published_at'] ?? 'NULL';
        
        echo "   ID: {$scholarship['id']}\n";
        echo "   Title: {$scholarship['title']}\n";
        echo "   Status: {$scholarship['status']}\n";
        echo "   Workflow Status: {$scholarship['workflow_status']}\n";
        echo "   Application Start: {$appStart}\n";
        echo "   Application End: {$appEnd}\n";
        echo "   Published At: {$published}\n";
        echo "   Created At: {$scholarship['created_at']}\n";
        echo "   ---\n";
    }
    
    // 2. Check scholarships that should be visible to students (current query)
    echo "\n2. Scholarships visible to students (current query):\n";
    $stmt = $conn->prepare("
        SELECT s.id, s.title, s.status, s.workflow_status, s.application_start, s.application_end,
               u.fullname as provider_name, pp.organization_name
        FROM scholarships s
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN provider_profiles pp ON u.id = pp.user_id
        WHERE s.status = 'Active' 
        AND (s.workflow_status = 'APPROVED_FOR_PUBLICATION' OR s.workflow_status IS NULL)
        AND s.application_start <= CURDATE() 
        AND s.application_end >= CURDATE()
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $visibleScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($visibleScholarships) > 0) {
        foreach ($visibleScholarships as $scholarship) {
            echo "   ✅ ID: {$scholarship['id']} - {$scholarship['title']}\n";
            echo "      Status: {$scholarship['status']}\n";
            echo "      Workflow: {$scholarship['workflow_status']}\n";
            echo "      App Period: {$scholarship['application_start']} to {$scholarship['application_end']}\n";
            echo "      Provider: {$scholarship['organization_name']} ({$scholarship['provider_name']})\n";
            echo "   ---\n";
        }
    } else {
        echo "   ❌ No scholarships visible to students\n";
    }
    
    // 3. Check scholarships that are Active but not visible
    echo "\n3. Active scholarships that are NOT visible to students:\n";
    $stmt = $conn->prepare("
        SELECT s.id, s.title, s.status, s.workflow_status, s.application_start, s.application_end,
               CURDATE() as today,
               CASE 
                   WHEN s.application_start > CURDATE() THEN 'Future start date'
                   WHEN s.application_end < CURDATE() THEN 'Past end date'
                   WHEN s.workflow_status NOT IN ('APPROVED_FOR_PUBLICATION') AND s.workflow_status IS NOT NULL THEN 'Wrong workflow status'
                   ELSE 'Unknown reason'
               END as reason_not_visible
        FROM scholarships s
        WHERE s.status = 'Active'
        AND NOT (
            (s.workflow_status = 'APPROVED_FOR_PUBLICATION' OR s.workflow_status IS NULL)
            AND s.application_start <= CURDATE() 
            AND s.application_end >= CURDATE()
        )
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $notVisibleScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($notVisibleScholarships) > 0) {
        foreach ($notVisibleScholarships as $scholarship) {
            echo "   ❌ ID: {$scholarship['id']} - {$scholarship['title']}\n";
            echo "      Status: {$scholarship['status']}\n";
            echo "      Workflow: {$scholarship['workflow_status']}\n";
            echo "      App Period: {$scholarship['application_start']} to {$scholarship['application_end']}\n";
            echo "      Today: {$scholarship['today']}\n";
            echo "      Reason: {$scholarship['reason_not_visible']}\n";
            echo "   ---\n";
        }
    } else {
        echo "   ✅ All active scholarships are properly visible\n";
    }
    
    // 4. Check current date and application periods
    echo "\n4. Date analysis:\n";
    $stmt = $conn->prepare("SELECT CURDATE() as today, NOW() as now");
    $stmt->execute();
    $dateInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Current Date: {$dateInfo['today']}\n";
    echo "   Current DateTime: {$dateInfo['now']}\n";
    
    // Check each scholarship's date validity
    echo "\n   Date validity for each scholarship:\n";
    foreach ($allScholarships as $scholarship) {
        if ($scholarship['status'] === 'Active') {
            $startValid = ($scholarship['application_start'] <= $dateInfo['today']) ? '✅' : '❌';
            $endValid = ($scholarship['application_end'] >= $dateInfo['today']) ? '✅' : '❌';
            
            echo "   ID {$scholarship['id']}: Start {$startValid} ({$scholarship['application_start']}) | End {$endValid} ({$scholarship['application_end']})\n";
        }
    }
    
    // 5. Check what the admin dashboard is counting
    echo "\n5. Admin dashboard statistics:\n";
    
    // Published scholarships count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM scholarships 
        WHERE workflow_status = 'APPROVED_FOR_PUBLICATION'
    ");
    $stmt->execute();
    $publishedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Published Scholarships (workflow_status = 'APPROVED_FOR_PUBLICATION'): {$publishedCount}\n";
    
    // Currently active count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM scholarships 
        WHERE status = 'Active'
    ");
    $stmt->execute();
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Currently Active (status = 'Active'): {$activeCount}\n";
    
    // Student visible count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM scholarships 
        WHERE status = 'Active' 
        AND (workflow_status = 'APPROVED_FOR_PUBLICATION' OR workflow_status IS NULL)
        AND application_start <= CURDATE() 
        AND application_end >= CURDATE()
    ");
    $stmt->execute();
    $studentVisibleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Student Visible: {$studentVisibleCount}\n";
    
    // 6. Suggest fixes
    echo "\n6. Suggested fixes:\n";
    
    if (count($notVisibleScholarships) > 0) {
        echo "   Found scholarships that need fixing:\n";
        foreach ($notVisibleScholarships as $scholarship) {
            if (strpos($scholarship['reason_not_visible'], 'Future start date') !== false) {
                echo "   - ID {$scholarship['id']}: Update application_start to today or earlier\n";
            } elseif (strpos($scholarship['reason_not_visible'], 'Past end date') !== false) {
                echo "   - ID {$scholarship['id']}: Extend application_end to future date\n";
            } elseif (strpos($scholarship['reason_not_visible'], 'Wrong workflow status') !== false) {
                echo "   - ID {$scholarship['id']}: Update workflow_status to 'APPROVED_FOR_PUBLICATION'\n";
            }
        }
    } else {
        echo "   ✅ No obvious fixes needed based on current data\n";
    }
    
    echo "\n🎯 Summary:\n";
    echo "   - Total scholarships: " . count($allScholarships) . "\n";
    echo "   - Published scholarships: {$publishedCount}\n";
    echo "   - Active scholarships: {$activeCount}\n";
    echo "   - Student visible scholarships: {$studentVisibleCount}\n";
    echo "   - Scholarships needing fixes: " . count($notVisibleScholarships) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error debugging scholarships: " . $e->getMessage() . "\n";
}
?>