<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->connect();

    echo "Checking school configuration...\n\n";
    
    // Check schools table
    $stmt = $conn->query('DESCRIBE schools');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Schools table columns:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check if we have VP and President email fields
    $vpEmailExists = false;
    $presidentEmailExists = false;
    
    foreach ($columns as $col) {
        if (strpos($col['Field'], 'vp') !== false && strpos($col['Field'], 'email') !== false) {
            $vpEmailExists = true;
        }
        if (strpos($col['Field'], 'president') !== false && strpos($col['Field'], 'email') !== false) {
            $presidentEmailExists = true;
        }
    }
    
    echo "\nEmail configuration status:\n";
    echo "VP Email field exists: " . ($vpEmailExists ? "YES" : "NO") . "\n";
    echo "President Email field exists: " . ($presidentEmailExists ? "YES" : "NO") . "\n";
    
    // Check sample school data
    $stmt = $conn->query('SELECT * FROM schools LIMIT 1');
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        echo "\nSample school data:\n";
        foreach ($school as $key => $value) {
            if (strpos($key, 'email') !== false || strpos($key, 'vp') !== false || strpos($key, 'president') !== false) {
                echo "- $key: $value\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>