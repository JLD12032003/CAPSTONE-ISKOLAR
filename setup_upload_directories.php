<?php
/**
 * Setup upload directories for scholarship applications
 */

echo "<h1>Setting up Upload Directories</h1>\n";

$baseUploadDir = __DIR__ . '/uploads/';
$applicationDir = $baseUploadDir . 'applications/';

// Create base upload directory
if (!file_exists($baseUploadDir)) {
    if (mkdir($baseUploadDir, 0755, true)) {
        echo "<p style='color: green;'>✓ Created base upload directory: {$baseUploadDir}</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to create base upload directory</p>\n";
    }
} else {
    echo "<p style='color: blue;'>ℹ Base upload directory already exists</p>\n";
}

// Create applications directory
if (!file_exists($applicationDir)) {
    if (mkdir($applicationDir, 0755, true)) {
        echo "<p style='color: green;'>✓ Created applications directory: {$applicationDir}</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to create applications directory</p>\n";
    }
} else {
    echo "<p style='color: blue;'>ℹ Applications directory already exists</p>\n";
}

// Create .htaccess file to protect uploads
$htaccessContent = "# Protect uploaded files\n";
$htaccessContent .= "Options -Indexes\n";
$htaccessContent .= "# Allow only specific file types\n";
$htaccessContent .= "<FilesMatch \"\\.(pdf|jpg|jpeg|png)$\">\n";
$htaccessContent .= "    Order Allow,Deny\n";
$htaccessContent .= "    Allow from all\n";
$htaccessContent .= "</FilesMatch>\n";
$htaccessContent .= "# Deny access to all other files\n";
$htaccessContent .= "<FilesMatch \"^(?!.*\\.(pdf|jpg|jpeg|png)$).*$\">\n";
$htaccessContent .= "    Order Allow,Deny\n";
$htaccessContent .= "    Deny from all\n";
$htaccessContent .= "</FilesMatch>\n";

$htaccessFile = $baseUploadDir . '.htaccess';
if (file_put_contents($htaccessFile, $htaccessContent)) {
    echo "<p style='color: green;'>✓ Created .htaccess protection file</p>\n";
} else {
    echo "<p style='color: red;'>✗ Failed to create .htaccess file</p>\n";
}

// Create index.php files to prevent directory listing
$indexContent = "<?php\n// Directory access denied\nheader('HTTP/1.0 403 Forbidden');\nexit('Access denied');\n?>";

$indexFiles = [
    $baseUploadDir . 'index.php',
    $applicationDir . 'index.php'
];

foreach ($indexFiles as $indexFile) {
    if (file_put_contents($indexFile, $indexContent)) {
        echo "<p style='color: green;'>✓ Created protection file: " . basename(dirname($indexFile)) . "/index.php</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to create protection file: {$indexFile}</p>\n";
    }
}

echo "<h2>Directory Structure Created:</h2>\n";
echo "<pre>\n";
echo "uploads/\n";
echo "├── .htaccess (security protection)\n";
echo "├── index.php (access protection)\n";
echo "└── applications/\n";
echo "    ├── index.php (access protection)\n";
echo "    └── [user_id]/\n";
echo "        └── [uploaded files]\n";
echo "</pre>\n";

echo "<h2>Security Features:</h2>\n";
echo "<ul>\n";
echo "<li>✓ Directory listing disabled</li>\n";
echo "<li>✓ Only PDF, JPG, JPEG, PNG files allowed</li>\n";
echo "<li>✓ Access protection with index.php files</li>\n";
echo "<li>✓ User-specific directories for file organization</li>\n";
echo "</ul>\n";

echo "<p style='color: green;'><strong>✓ Upload directory setup complete!</strong></p>\n";

?>