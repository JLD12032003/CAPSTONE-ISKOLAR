<?php
require_once 'config/database.php';
require_once 'app/models/Scholarship.php';

$database = new Database();
$conn = $database->connect();

echo "🔍 Debugging Scholarship Application Issue...\n\n";

// 1. Check scholarships table
echo "📋 Scholarships in database:\n";
$stmt = $conn->query("SELECT id, title, school_id, provider_id FROM scholarships");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} - School ID: " . ($row['school_id'] ?? 'NULL') . " - Provider ID: {$row['provider_id']}\n";
}

// 2. Check what getActiveScholarships returns
echo "\n📊 Active scholarships from model:\n";
$scholarshipModel = new Scholarship();
$scholarships = $scholarshipModel->getActiveScholarships(6);

if (empty($scholarships)) {
    echo "❌ No active scholarships returned by getActiveScholarships()\n";
    
    // Debug the query
    echo "\n🔍 Debugging the query...\n";
    $stmt = $conn->query("
        SELECT s.*, u.fullname as provider_name, pp.organization_name, sc.school_name
        FROM scholarships s
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN provider_profiles pp ON u.id = pp.user_id
        LEFT JOIN schools sc ON s.school_id = sc.id
        WHERE s.status = 'Active' 
        AND (s.workflow_status = 'APPROVED_FOR_PUBLICATION' OR s.workflow_status IS NULL)
        AND s.application_start <= CURDATE() 
        AND s.application_end >= CURDATE()
    ");
    $debugResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($debugResults)) {
        echo "❌ Query returns no results. Checking individual conditions...\n";
        
        // Check each condition
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE status = 'Active'");
        $activeCount = $stmt->fetch()['count'];
        echo "   - Active status: {$activeCount} scholarships\n";
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE workflow_status = 'APPROVED_FOR_PUBLICATION'");
        $workflowCount = $stmt->fetch()['count'];
        echo "   - Approved workflow: {$workflowCount} scholarships\n";
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE application_start <= CURDATE()");
        $startCount = $stmt->fetch()['count'];
        echo "   - Start date valid: {$startCount} scholarships\n";
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE application_end >= CURDATE()");
        $endCount = $stmt->fetch()['count'];
        echo "   - End date valid: {$endCount} scholarships\n";
        
        // Check if the JOIN is the issue
        $stmt = $conn->query("
            SELECT s.id, s.title, s.provider_id, u.id as user_exists
            FROM scholarships s
            LEFT JOIN users u ON s.provider_id = u.id
            WHERE s.status = 'Active'
        ");
        $joinResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n   - JOIN results:\n";
        foreach ($joinResults as $result) {
            echo "     Scholarship {$result['id']}: Provider {$result['provider_id']} -> User " . ($result['user_exists'] ?? 'NOT FOUND') . "\n";
        }
        
    } else {
        echo "✅ Query returns results but getActiveScholarships() filters them out\n";
        foreach ($debugResults as $result) {
            echo "   - ID: {$result['id']} - {$result['title']}\n";
        }
    }
} else {
    echo "✅ Found " . count($scholarships) . " active scholarships:\n";
    foreach ($scholarships as $scholarship) {
        echo "   - ID: {$scholarship['id']} - {$scholarship['title']}\n";
    }
}

// 3. Check if there are any foreign key issues
echo "\n🔗 Checking foreign key constraints:\n";
$stmt = $conn->query("
    SELECT s.id, s.provider_id, u.id as user_exists
    FROM scholarships s
    LEFT JOIN users u ON s.provider_id = u.id
    WHERE u.id IS NULL
");
$orphanedScholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphanedScholarships)) {
    echo "✅ All scholarships have valid provider_id references\n";
} else {
    echo "❌ Found orphaned scholarships:\n";
    foreach ($orphanedScholarships as $orphan) {
        echo "   - Scholarship {$orphan['id']} references non-existent provider {$orphan['provider_id']}\n";
    }
}

echo "\n✅ Debug completed!\n";
?>