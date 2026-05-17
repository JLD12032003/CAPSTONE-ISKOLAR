<?php
/**
 * Fix Application Dates for Scholarships
 * Updates scholarships with future start dates to be visible immediately
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔧 Fixing Application Dates for Scholarships...\n\n";
    
    // Find scholarships with future start dates
    $stmt = $conn->prepare("
        SELECT id, title, application_start, application_end
        FROM scholarships 
        WHERE status = 'Active' 
        AND workflow_status = 'APPROVED_FOR_PUBLICATION'
        AND application_start > CURDATE()
    ");
    $stmt->execute();
    $futureScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($futureScholarships) > 0) {
        echo "Found " . count($futureScholarships) . " scholarships with future start dates:\n";
        
        foreach ($futureScholarships as $scholarship) {
            echo "   - ID: {$scholarship['id']}, Title: {$scholarship['title']}\n";
            echo "     Current start: {$scholarship['application_start']}\n";
            echo "     Current end: {$scholarship['application_end']}\n";
            
            // Update to start today
            $stmt = $conn->prepare("
                UPDATE scholarships 
                SET application_start = CURDATE()
                WHERE id = ?
            ");
            $stmt->execute([$scholarship['id']]);
            
            echo "     ✅ Updated start date to today (" . date('Y-m-d') . ")\n";
            echo "   ---\n";
        }
        
        echo "\n✅ All scholarships updated successfully!\n";
        
    } else {
        echo "✅ No scholarships found with future start dates.\n";
    }
    
    // Verify the fix
    echo "\n🧪 Verifying the fix...\n";
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM scholarships 
        WHERE status = 'Active' 
        AND (workflow_status = 'APPROVED_FOR_PUBLICATION' OR workflow_status IS NULL)
        AND application_start <= CURDATE() 
        AND application_end >= CURDATE()
    ");
    $stmt->execute();
    $visibleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Scholarships now visible to students: {$visibleCount}\n";
    
    // Show all visible scholarships
    $stmt = $conn->prepare("
        SELECT s.id, s.title, s.application_start, s.application_end,
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
        echo "\n📋 Currently visible scholarships:\n";
        foreach ($visibleScholarships as $scholarship) {
            $provider = $scholarship['organization_name'] ?? $scholarship['provider_name'];
            echo "   ✅ {$scholarship['title']} by {$provider}\n";
            echo "      Application Period: {$scholarship['application_start']} to {$scholarship['application_end']}\n";
        }
    }
    
    echo "\n🎉 Fix completed! Students should now see all approved scholarships.\n";
    
} catch (Exception $e) {
    echo "❌ Error fixing application dates: " . $e->getMessage() . "\n";
}
?>