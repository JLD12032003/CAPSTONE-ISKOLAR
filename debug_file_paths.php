<?php
/**
 * Debug File Paths Issue
 * Check where files are actually stored vs where the system is looking
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔍 Debugging File Paths Issue...\n\n";
    
    // Get application with documents
    $stmt = $conn->prepare("
        SELECT id, student_id, documents 
        FROM scholarship_applications 
        WHERE documents IS NOT NULL AND documents != '' 
        LIMIT 1
    ");
    $stmt->execute();
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($application) {
        echo "Application ID: {$application['id']}\n";
        echo "Student ID: {$application['student_id']}\n";
        echo "Documents JSON: {$application['documents']}\n\n";
        
        $documents = json_decode($application['documents'], true);
        
        if ($documents) {
            echo "Parsed Documents:\n";
            foreach ($documents as $docType => $fileName) {
                echo "  - {$docType}: {$fileName}\n";
            }
            echo "\n";
            
            // Check different possible file locations
            $possiblePaths = [
                "uploads/applications/{$application['student_id']}/",
                "app/views/uploads/applications/{$application['student_id']}/",
                "uploads/applications/{$application['id']}/",
                "app/views/uploads/applications/{$application['id']}/"
            ];
            
            echo "Checking file locations:\n";
            foreach ($possiblePaths as $basePath) {
                echo "\nChecking path: {$basePath}\n";
                
                if (is_dir($basePath)) {
                    echo "  ✅ Directory exists\n";
                    $files = scandir($basePath);
                    echo "  Files in directory:\n";
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..') {
                            echo "    - {$file}\n";
                        }
                    }
                } else {
                    echo "  ❌ Directory does not exist\n";
                }
                
                // Check specific files
                foreach ($documents as $docType => $fileName) {
                    $fullPath = $basePath . $fileName;
                    if (file_exists($fullPath)) {
                        echo "  ✅ Found: {$fullPath}\n";
                    } else {
                        echo "  ❌ Missing: {$fullPath}\n";
                    }
                }
            }
            
            // Check what the current code is looking for
            echo "\nCurrent code is looking for files at:\n";
            foreach ($documents as $docType => $fileName) {
                $expectedPath = "uploads/applications/{$application['student_id']}/{$fileName}";
                $fullExpectedPath = __DIR__ . '/' . $expectedPath;
                echo "  Expected: {$expectedPath}\n";
                echo "  Full path: {$fullExpectedPath}\n";
                echo "  Exists: " . (file_exists($fullExpectedPath) ? "✅ Yes" : "❌ No") . "\n\n";
            }
        }
    } else {
        echo "No applications with documents found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>