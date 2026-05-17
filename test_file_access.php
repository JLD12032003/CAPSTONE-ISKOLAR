<?php
/**
 * Test File Access from Provider View
 * Simulate the file path resolution from the provider view
 */

// Simulate being in app/views/provider/ directory
$currentDir = __DIR__ . '/app/views/provider/';
$studentId = 20;
$fileName = '1778059603_academic_transcript_download (2).jpg';

echo "🧪 Testing File Access from Provider View...\n\n";

// Test the corrected path
$filePath = "../uploads/applications/{$studentId}/{$fileName}";
$fullFilePath = $currentDir . '../uploads/applications/' . $studentId . '/' . $fileName;

echo "Relative path: {$filePath}\n";
echo "Full path: {$fullFilePath}\n";
echo "Resolved path: " . realpath($fullFilePath) . "\n";
echo "File exists: " . (file_exists($fullFilePath) ? "✅ Yes" : "❌ No") . "\n\n";

// Check the actual file location we found
$actualPath = __DIR__ . '/app/views/uploads/applications/' . $studentId . '/' . $fileName;
echo "Actual file location: {$actualPath}\n";
echo "Actual file exists: " . (file_exists($actualPath) ? "✅ Yes" : "❌ No") . "\n\n";

// The correct path should be
$correctRelativePath = "uploads/applications/{$studentId}/{$fileName}";
$correctFullPath = $currentDir . $correctRelativePath;
echo "Correct relative path: {$correctRelativePath}\n";
echo "Correct full path: {$correctFullPath}\n";
echo "Correct file exists: " . (file_exists($correctFullPath) ? "✅ Yes" : "❌ No") . "\n";
?>