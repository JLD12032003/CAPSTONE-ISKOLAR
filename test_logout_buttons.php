<?php
/**
 * Test Logout Button Paths
 * Verifies all logout buttons have correct paths
 */

echo "<h1>🔓 Logout Button Path Test</h1>\n";
echo "<p>Testing all logout button paths throughout the system...</p>\n";

// Define expected logout paths for each file
$logoutPaths = [
    // Admin files (3 levels deep: app/views/admin/)
    'app/views/admin/dashboard.php' => '../../../logout.php',
    'app/views/admin/verification_status.php' => '../../../logout.php',
    'app/views/admin/identity_verification.php' => '../../../logout.php',
    
    // Provider files (3 levels deep: app/views/provider/)
    'app/views/provider/dashboard.php' => '../../../logout.php',
    'app/views/provider/partnership_request.php' => '../../../logout.php',
    'app/views/provider/scholarships.php' => '../../../logout.php',
    'app/views/provider/partnership_status.php' => '../../../logout.php',
    'app/views/provider/edit_scholarship.php' => '../../../logout.php',
    'app/views/provider/applications.php' => '../../../logout.php',
    'app/views/provider/view_scholarship.php' => '../../../logout.php',
    'app/views/provider/create_scholarship.php' => '../../../logout.php',
    
    // Student files (2 levels deep: app/views/)
    'app/views/student_home.php' => '../logout.php',
    'app/views/donor_home.php' => '../logout.php',
    'app/views/home.php' => '../logout.php',
];

echo "<h2>📋 Logout Path Verification</h2>\n";

$allCorrect = true;

foreach ($logoutPaths as $file => $expectedPath) {
    if (!file_exists($file)) {
        echo "<p>⚠️ <strong>{$file}:</strong> File not found</p>\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for logout button/link
    if (strpos($content, 'logout.php') !== false) {
        // Check if it has the correct path
        if (strpos($content, $expectedPath) !== false) {
            echo "<p>✅ <strong>{$file}:</strong> Correct path ({$expectedPath})</p>\n";
        } else {
            echo "<p>❌ <strong>{$file}:</strong> Incorrect path (expected: {$expectedPath})</p>\n";
            $allCorrect = false;
            
            // Show what path it actually has
            if (preg_match('/href="([^"]*logout\.php)"/', $content, $matches)) {
                echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;Found: {$matches[1]}</p>\n";
            }
        }
    } else {
        echo "<p>⚠️ <strong>{$file}:</strong> No logout button found</p>\n";
    }
}

echo "<h2>🔗 Root Logout File</h2>\n";

if (file_exists('logout.php')) {
    echo "<p>✅ <strong>logout.php:</strong> Root logout file exists</p>\n";
    
    $logoutContent = file_get_contents('logout.php');
    if (strpos($logoutContent, 'session_destroy()') !== false) {
        echo "<p>✅ <strong>Session cleanup:</strong> Properly implemented</p>\n";
    } else {
        echo "<p>❌ <strong>Session cleanup:</strong> Missing session_destroy()</p>\n";
        $allCorrect = false;
    }
    
    if (strpos($logoutContent, 'index.php') !== false) {
        echo "<p>✅ <strong>Redirect:</strong> Redirects to index.php</p>\n";
    } else {
        echo "<p>❌ <strong>Redirect:</strong> No redirect to index.php</p>\n";
        $allCorrect = false;
    }
} else {
    echo "<p>❌ <strong>logout.php:</strong> Root logout file missing</p>\n";
    $allCorrect = false;
}

echo "<h2>📊 Summary</h2>\n";

if ($allCorrect) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>\n";
    echo "<h4 style='color: #155724; margin: 0;'>✅ All Logout Buttons Fixed</h4>\n";
    echo "<p style='margin: 10px 0 0 0; color: #155724;'>All logout buttons have correct paths and the root logout file is properly configured.</p>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;'>\n";
    echo "<h4 style='color: #721c24; margin: 0;'>❌ Issues Found</h4>\n";
    echo "<p style='margin: 10px 0 0 0; color: #721c24;'>Some logout buttons have incorrect paths or the logout system needs fixes.</p>\n";
    echo "</div>\n";
}

echo "<h2>🎯 Path Reference Guide</h2>\n";
echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;'>\n";
echo "<h4 style='color: #0d47a1; margin-top: 0;'>Correct Logout Paths by Location</h4>\n";
echo "<ul style='margin: 0; color: #0d47a1;'>\n";
echo "<li><strong>Admin files</strong> (app/views/admin/): <code>../../../logout.php</code></li>\n";
echo "<li><strong>Provider files</strong> (app/views/provider/): <code>../../../logout.php</code></li>\n";
echo "<li><strong>Student files</strong> (app/views/): <code>../logout.php</code></li>\n";
echo "<li><strong>Root files</strong> (index.php): <code>logout.php</code></li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🧪 Test Instructions</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>\n";
echo "<h4 style='color: #856404; margin-top: 0;'>Manual Testing</h4>\n";
echo "<ol style='margin: 0; color: #856404;'>\n";
echo "<li>Login as admin: admin@davaocentralcollege.edu.ph / SchoolAdmin2024!</li>\n";
echo "<li>Navigate to admin dashboard and click logout</li>\n";
echo "<li>Login as provider and test provider dashboard logout</li>\n";
echo "<li>Login as student and test student dashboard logout</li>\n";
echo "<li>Verify all logout buttons redirect to homepage</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "\n<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>