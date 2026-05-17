<?php
/**
 * Update All Logout Buttons with Confirmation Modals
 * Adds logout confirmation popups to all remaining files
 */

echo "🔄 Updating logout buttons with confirmation modals...\n";

// Provider files to update
$providerFiles = [
    'app/views/provider/partnership_request.php',
    'app/views/provider/scholarships.php',
    'app/views/provider/partnership_status.php',
    'app/views/provider/edit_scholarship.php',
    'app/views/provider/applications.php',
    'app/views/provider/view_scholarship.php',
    'app/views/provider/create_scholarship.php'
];

// Logout confirmation modal HTML
$logoutModal = '
<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h2 style="color:#012A4A; margin-bottom:20px; font-weight:700;">Confirm Logout</h2>
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your provider dashboard.</p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeLogoutModal()" style="padding:10px 20px; background-color:#e9ecef; color:#333; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:\'Poppins\', sans-serif;">Cancel</button>
            <button onclick="proceedLogout()" style="padding:10px 20px; background-color:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-family:\'Poppins\', sans-serif;">Logout</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmLogout() {
    document.getElementById(\'logoutModal\').style.display = \'block\';
}

function closeLogoutModal() {
    document.getElementById(\'logoutModal\').style.display = \'none\';
}

function proceedLogout() {
    window.location.href = \'../../../logout.php\';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById(\'logoutModal\');
    if (event.target == modal) {
        closeLogoutModal();
    }
}
</script>
</body>
</html>';

foreach ($providerFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠️ File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Update logout button to use confirmation
    $content = str_replace(
        'href="../../../logout.php"',
        'href="#" onclick="confirmLogout(); return false;"',
        $content
    );
    
    // Add logout modal before closing body tag
    $content = str_replace(
        '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>',
        $logoutModal,
        $content
    );
    
    // Write updated content back to file
    if (file_put_contents($file, $content)) {
        echo "✅ Updated: {$file}\n";
    } else {
        echo "❌ Failed to update: {$file}\n";
    }
}

// Update student files
$studentFiles = [
    'app/views/home.php' => '../logout.php'
];

foreach ($studentFiles as $file => $logoutPath) {
    if (!file_exists($file)) {
        echo "⚠️ File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Update logout button to use confirmation
    $content = str_replace(
        'href="' . $logoutPath . '"',
        'href="#" onclick="confirmLogout(); return false;"',
        $content
    );
    
    // Add logout modal and JavaScript
    $studentModal = '
<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <h2 style="color:#012A4A; margin-bottom:20px; font-weight:700;">Confirm Logout</h2>
        <p style="color:#666; margin-bottom:30px; font-size:16px;">Are you sure you want to logout? You will need to log in again to access your account.</p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeLogoutModal()" style="padding:10px 20px; background-color:#e9ecef; color:#333; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Cancel</button>
            <button onclick="proceedLogout()" style="padding:10px 20px; background-color:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Logout</button>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    document.getElementById(\'logoutModal\').style.display = \'block\';
}

function closeLogoutModal() {
    document.getElementById(\'logoutModal\').style.display = \'none\';
}

function proceedLogout() {
    window.location.href = \'' . $logoutPath . '\';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById(\'logoutModal\');
    if (event.target == modal) {
        closeLogoutModal();
    }
}
</script>
</body>
</html>';
    
    $content = str_replace('</body>
</html>', $studentModal, $content);
    
    if (file_put_contents($file, $content)) {
        echo "✅ Updated: {$file}\n";
    } else {
        echo "❌ Failed to update: {$file}\n";
    }
}

echo "\n✅ All logout buttons updated with confirmation modals!\n";
echo "📋 Updated files:\n";
echo "- Admin files: 2 files\n";
echo "- Provider files: " . count($providerFiles) . " files\n";
echo "- Student files: " . count($studentFiles) . " files\n";
echo "\n🎯 All logout buttons now show confirmation popup before logging out.\n";
?>