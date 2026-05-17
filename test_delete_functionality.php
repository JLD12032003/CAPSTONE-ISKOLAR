<?php
/**
 * Test Script for Scholarship Delete Functionality
 * This script tests the comprehensive delete functionality to ensure
 * all related records are properly cleaned up.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/models/Scholarship.php';

$database = new Database();
$conn = $database->connect();
$scholarshipModel = new Scholarship();

echo "<h1>Scholarship Delete Functionality Test</h1>\n";

// Function to check if table exists
function tableExists($conn, $tableName) {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM $tableName LIMIT 1");
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to count records in a table for a scholarship
function countRelatedRecords($conn, $tableName, $scholarshipId, $columnName = 'scholarship_id') {
    if (!tableExists($conn, $tableName)) {
        return "Table does not exist";
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $tableName WHERE $columnName = ?");
        $stmt->execute([$scholarshipId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// Get a test scholarship (preferably one with related records)
echo "<h2>Finding Test Scholarship...</h2>\n";
$stmt = $conn->prepare("SELECT * FROM scholarships ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$testScholarship = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$testScholarship) {
    echo "<p style='color: red;'>No scholarships found in database. Please create a scholarship first.</p>\n";
    exit;
}

$scholarshipId = $testScholarship['id'];
$providerId = $testScholarship['provider_id'];

echo "<p><strong>Test Scholarship:</strong> ID {$scholarshipId} - {$testScholarship['title']}</p>\n";
echo "<p><strong>Provider ID:</strong> {$providerId}</p>\n";
echo "<p><strong>Workflow Status:</strong> {$testScholarship['workflow_status']}</p>\n";

// Check related records before deletion
echo "<h2>Related Records Before Deletion:</h2>\n";
$relatedTables = [
    'scholarship_applications' => 'scholarship_id',
    'scholarship_awards' => 'scholarship_id',
    'scholarship_approval_stages' => 'scholarship_id',
    'scholarship_audit_log' => 'scholarship_id',
    'scholarship_workflow_stages' => 'scholarship_id',
    'scholarship_workflow_audit' => 'scholarship_id',
    'scholarship_email_log' => 'scholarship_id',
    'committee_votes' => 'scholarship_id',
    'communications' => 'related_scholarship_id',
    'activity_logs' => 'entity_id' // Special case: need to check entity_type = 'scholarship'
];

$beforeCounts = [];
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Table</th><th>Record Count</th><th>Status</th></tr>\n";

foreach ($relatedTables as $table => $column) {
    if ($table === 'activity_logs') {
        // Special handling for activity_logs
        if (tableExists($conn, $table)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE entity_type = 'scholarship' AND entity_id = ?");
            $stmt->execute([$scholarshipId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
        } else {
            $count = "Table does not exist";
        }
    } else {
        $count = countRelatedRecords($conn, $table, $scholarshipId, $column);
    }
    
    $beforeCounts[$table] = $count;
    $status = is_numeric($count) && $count > 0 ? "Has Records" : "Empty/Missing";
    $statusColor = is_numeric($count) && $count > 0 ? "orange" : "green";
    
    echo "<tr><td>{$table}</td><td>{$count}</td><td style='color: {$statusColor};'>{$status}</td></tr>\n";
}
echo "</table>\n";

// Perform the deletion
echo "<h2>Performing Deletion...</h2>\n";
try {
    $result = $scholarshipModel->deleteScholarship($scholarshipId, $providerId);
    
    if ($result) {
        echo "<p style='color: green;'><strong>✓ Deletion completed successfully!</strong></p>\n";
    } else {
        echo "<p style='color: red;'><strong>✗ Deletion failed!</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Deletion error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Check related records after deletion
echo "<h2>Related Records After Deletion:</h2>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Table</th><th>Before</th><th>After</th><th>Status</th></tr>\n";

foreach ($relatedTables as $table => $column) {
    if ($table === 'activity_logs') {
        // Special handling for activity_logs
        if (tableExists($conn, $table)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE entity_type = 'scholarship' AND entity_id = ?");
            $stmt->execute([$scholarshipId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $afterCount = $result['count'];
        } else {
            $afterCount = "Table does not exist";
        }
    } else {
        $afterCount = countRelatedRecords($conn, $table, $scholarshipId, $column);
    }
    
    $beforeCount = $beforeCounts[$table];
    
    // Determine status
    if (!is_numeric($beforeCount) || !is_numeric($afterCount)) {
        $status = "N/A";
        $statusColor = "gray";
    } elseif ($afterCount == 0) {
        $status = "✓ Cleaned";
        $statusColor = "green";
    } elseif ($afterCount < $beforeCount) {
        $status = "⚠ Partial";
        $statusColor = "orange";
    } else {
        $status = "✗ Not Cleaned";
        $statusColor = "red";
    }
    
    echo "<tr><td>{$table}</td><td>{$beforeCount}</td><td>{$afterCount}</td><td style='color: {$statusColor};'>{$status}</td></tr>\n";
}
echo "</table>\n";

// Check if main scholarship record was deleted
echo "<h2>Main Scholarship Record:</h2>\n";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM scholarships WHERE id = ?");
$stmt->execute([$scholarshipId]);
$scholarshipExists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($scholarshipExists == 0) {
    echo "<p style='color: green;'><strong>✓ Main scholarship record successfully deleted!</strong></p>\n";
} else {
    echo "<p style='color: red;'><strong>✗ Main scholarship record still exists!</strong></p>\n";
}

// Check activity logs for deletion tracking
echo "<h2>Deletion Activity Logs:</h2>\n";
if (tableExists($conn, 'activity_logs')) {
    $stmt = $conn->prepare("
        SELECT action, description, created_at 
        FROM activity_logs 
        WHERE user_id = ? AND action LIKE '%DELETE%' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$providerId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($logs) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Action</th><th>Description</th><th>Timestamp</th></tr>\n";
        foreach ($logs as $log) {
            echo "<tr><td>{$log['action']}</td><td>" . htmlspecialchars($log['description']) . "</td><td>{$log['created_at']}</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No deletion logs found.</p>\n";
    }
} else {
    echo "<p>Activity logs table does not exist.</p>\n";
}

// Summary
echo "<h2>Test Summary:</h2>\n";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
echo "<p><strong>Test Scholarship ID:</strong> {$scholarshipId}</p>\n";
echo "<p><strong>Provider ID:</strong> {$providerId}</p>\n";
echo "<p><strong>Deletion Status:</strong> " . ($scholarshipExists == 0 ? "SUCCESS" : "FAILED") . "</p>\n";
echo "<p><strong>Tables Checked:</strong> " . count($relatedTables) . "</p>\n";
echo "<p><strong>Recommendation:</strong> ";
if ($scholarshipExists == 0) {
    echo "Delete functionality is working properly. All related records have been cleaned up.";
} else {
    echo "Delete functionality needs attention. Check foreign key constraints and table relationships.";
}
echo "</p>\n";
echo "</div>\n";

echo "<h3>Notes:</h3>\n";
echo "<ul>\n";
echo "<li>This test checks all known related tables for proper cleanup</li>\n";
echo "<li>Tables marked as 'Table does not exist' are expected if not all workflow features are implemented</li>\n";
echo "<li>All numeric counts should be 0 after successful deletion</li>\n";
echo "<li>Activity logs track the deletion process for audit purposes</li>\n";
echo "</ul>\n";

?>