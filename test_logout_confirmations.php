<?php
/**
 * Test Logout Confirmation Modals
 * Verifies all logout buttons have confirmation popups
 */

echo "<h1>🔔 Logout Confirmation Modal Test</h1>\n";
echo "<p>Testing all logout confirmation popups throughout the system...</p>\n";

// Files to check for logout confirmation
$filesToCheck = [
    // Admin files
    'app/views/admin/dashboard.php' => 'Admin Dashboard',
    'app/views/admin/verification_status.php' => 'Admin Verification Status',
    
    // Provider files
    'app/views/provider/dashboard.php' => 'Provider Dashboard',
    'app/views/provider/partnership_request.php' => 'Partnership Request',
    'app/views/provider/scholarships.php' => 'Provider Scholarships',
    'app/views/provider/partnership_status.php' => 'Partnership Status',
    'app/views/provider/edit_scholarship.php' => 'Edit Scholarship',
    'app/views/provider/applications.php' => 'Provider Applications',
    'app/views/provider/view_scholarship.php' => 'View Scholarship',
    'app/views/provider/create_scholarship.php' => 'Create Scholarship',
    
    // Student files
    'app/views/student_home.php' => 'Student Home',
    'app/views/donor_home.php' => 'Donor Home',
    'app/views/home.php' => 'General Home'
];

echo "<h2>📋 Logout Confirmation Check</h2>\n";

$allHaveConfirmation = true;
$totalFiles = count($filesToCheck);
$filesWithConfirmation = 0;

foreach ($filesToCheck as $file => $description) {
    if (!file_exists($file)) {
        echo "<p>⚠️ <strong>{$description}:</strong> File not found ({$file})</p>\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for confirmation modal elements
    $hasConfirmLogout = strpos($content, 'confirmLogout()') !== false;
    $hasLogoutModal = strpos($content, 'logoutModal') !== false;
    $hasModalHTML = strpos($content, 'Confirm Logout') !== false;
    $hasOnclickConfirm = strpos($content, 'onclick="confirmLogout()') !== false || 
                        strpos($content, 'onclick="openLogoutModal()') !== false;
    
    if ($hasConfirmLogout && $hasLogoutModal && $hasModalHTML && $hasOnclickConfirm) {
        echo "<p>✅ <strong>{$description}:</strong> Logout confirmation implemented</p>\n";
        $filesWithConfirmation++;
    } else {
        echo "<p>❌ <strong>{$description}:</strong> Missing logout confirmation</p>\n";
        $allHaveConfirmation = false;
        
        // Show what's missing
        if (!$hasOnclickConfirm) echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;Missing: onclick confirmation</p>\n";
        if (!$hasConfirmLogout) echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;Missing: confirmLogout() function</p>\n";
        if (!$hasLogoutModal) echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;Missing: logout modal</p>\n";
        if (!$hasModalHTML) echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;Missing: modal HTML</p>\n";
    }
}

echo "<h2>📊 Summary</h2>\n";

echo "<p><strong>Files with confirmation:</strong> {$filesWithConfirmation}/{$totalFiles}</p>\n";

if ($allHaveConfirmation) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>\n";
    echo "<h4 style='color: #155724; margin: 0;'>✅ All Logout Confirmations Implemented</h4>\n";
    echo "<p style='margin: 10px 0 0 0; color: #155724;'>All logout buttons now show confirmation popups before logging out users.</p>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;'>\n";
    echo "<h4 style='color: #721c24; margin: 0;'>❌ Some Files Missing Confirmations</h4>\n";
    echo "<p style='margin: 10px 0 0 0; color: #721c24;'>Some logout buttons are missing confirmation popups.</p>\n";
    echo "</div>\n";
}

echo "<h2>🎯 Confirmation Features</h2>\n";
echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;'>\n";
echo "<h4 style='color: #0d47a1; margin-top: 0;'>📋 Logout Confirmation Features</h4>\n";
echo "<ul style='margin: 0; color: #0d47a1;'>\n";
echo "<li><strong>Confirmation Popup:</strong> Shows before logout</li>\n";
echo "<li><strong>Cancel Option:</strong> Users can cancel logout</li>\n";
echo "<li><strong>Proceed Option:</strong> Users can confirm logout</li>\n";
echo "<li><strong>Click Outside:</strong> Closes modal without logout</li>\n";
echo "<li><strong>Professional Design:</strong> Consistent styling across all pages</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🧪 Manual Testing Guide</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>\n";
echo "<h4 style='color: #856404; margin-top: 0;'>Testing Steps</h4>\n";
echo "<ol style='margin: 0; color: #856404;'>\n";
echo "<li><strong>Login as Admin:</strong> admin@davaocentralcollege.edu.ph / SchoolAdmin2024!</li>\n";
echo "<li><strong>Click Logout:</strong> Should show confirmation popup</li>\n";
echo "<li><strong>Test Cancel:</strong> Click Cancel - should stay logged in</li>\n";
echo "<li><strong>Test Confirm:</strong> Click Logout - should log out</li>\n";
echo "<li><strong>Repeat for Provider:</strong> Test provider dashboard logout</li>\n";
echo "<li><strong>Repeat for Student:</strong> Test student dashboard logout</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<h2>🔧 Modal Implementation Details</h2>\n";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>\n";
echo "<h5>JavaScript Functions:</h5>\n";
echo "<ul>\n";
echo "<li><code>confirmLogout()</code> - Shows the confirmation modal</li>\n";
echo "<li><code>closeLogoutModal()</code> - Closes modal without logout</li>\n";
echo "<li><code>proceedLogout()</code> - Proceeds with logout to logout.php</li>\n";
echo "</ul>\n";

echo "<h5>Modal Features:</h5>\n";
echo "<ul>\n";
echo "<li>Professional styling with ISKOLar colors</li>\n";
echo "<li>Clear confirmation message</li>\n";
echo "<li>Cancel and Logout buttons</li>\n";
echo "<li>Click outside to close</li>\n";
echo "<li>High z-index to appear above all content</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "\n<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>