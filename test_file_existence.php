<?php
// Test file existence from provider directory perspective

$studentId = 20;
$fileName = '1778059603_academic_transcript_download (2).jpg';

// Test different paths
$paths = [
    __DIR__ . '/app/views/uploads/applications/' . $studentId . '/' . $fileName,
    __DIR__ . '/app/views/provider/../uploads/applications/' . $studentId . '/' . $fileName,
    'app/views/uploads/applications/' . $studentId . '/' . $fileName
];

echo "Testing file existence for: {$fileName}\n\n";

foreach ($paths as $i => $path) {
    echo "Path " . ($i + 1) . ": {$path}\n";
    echo "Exists: " . (file_exists($path) ? "✅ Yes" : "❌ No") . "\n";
    echo "Real path: " . realpath($path) . "\n\n";
}

// Test from provider directory perspective
$providerDir = __DIR__ . '/app/views/provider';
$relativePath = '../uploads/applications/' . $studentId . '/' . $fileName;
$fullPath = $providerDir . '/' . $relativePath;

echo "From provider directory:\n";
echo "Provider dir: {$providerDir}\n";
echo "Relative path: {$relativePath}\n";
echo "Full path: {$fullPath}\n";
echo "Exists: " . (file_exists($fullPath) ? "✅ Yes" : "❌ No") . "\n";
?>