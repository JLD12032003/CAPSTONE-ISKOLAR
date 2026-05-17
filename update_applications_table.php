<?php
/**
 * Update scholarship_applications table to support document uploads
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->connect();

echo "<h1>Updating Scholarship Applications Table</h1>\n";

try {
    // Check if documents column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM scholarship_applications LIKE 'documents'");
    $stmt->execute();
    $documentsExists = $stmt->rowCount() > 0;
    
    if (!$documentsExists) {
        // Add documents column
        $conn->exec("ALTER TABLE scholarship_applications ADD COLUMN documents JSON NULL AFTER why_deserve_scholarship");
        echo "<p style='color: green;'>✓ Added documents column to scholarship_applications table</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ Documents column already exists</p>\n";
    }
    
    // Show current table structure
    $stmt = $conn->prepare("DESCRIBE scholarship_applications");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Table Structure:</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h2>Documents Field Details:</h2>\n";
    echo "<p>The documents field will store JSON data with the following structure:</p>\n";
    echo "<pre>\n";
    echo "{\n";
    echo "  \"transcript_of_records\": \"filename.pdf\",\n";
    echo "  \"certificate_of_enrollment\": \"filename.pdf\",\n";
    echo "  \"family_income_certificate\": \"filename.pdf\",\n";
    echo "  \"additional_documents\": [\"file1.pdf\", \"file2.jpg\"]\n";
    echo "}\n";
    echo "</pre>\n";
    
    echo "<p style='color: green;'><strong>✓ Database update complete!</strong></p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

?>