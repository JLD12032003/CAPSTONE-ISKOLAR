<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "📋 Current Scholarships in Database:\n";
echo "===================================\n";

$stmt = $conn->query("SELECT id, title, status, workflow_status FROM scholarships ORDER BY id");
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($scholarships)) {
    echo "❌ No scholarships found!\n";
} else {
    foreach ($scholarships as $scholarship) {
        echo "ID: {$scholarship['id']} - {$scholarship['title']}\n";
        echo "   Status: {$scholarship['status']} | Workflow: {$scholarship['workflow_status']}\n\n";
    }
}

echo "📊 Total scholarships: " . count($scholarships) . "\n";
?>