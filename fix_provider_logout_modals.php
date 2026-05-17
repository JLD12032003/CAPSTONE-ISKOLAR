<?php
/**
 * Fix Provider Logout Modals
 * Properly adds logout confirmation modals to provider files
 */

echo "🔧 Fixing provider logout modals...\n";

$providerFiles = [
    'app/views/provider/partnership_request.php',
    'app/views/provider/scholarships.php',
    'app/views/provider/partnership_status.php',
    'app/views/provider/edit_scholarship.php',
    'app/views/provider/applications.php',
    'app/views/provider/view_scholarship.php',
    'app/views/provider/create_scholarship.php'
];

$logoutModalAndScript = '
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
    
    // Check if modal already exists
    if (strpos($content, 'logoutModal') !== false) {
        echo "✅ Already has modal: {$file}\n";
        continue;
    }
    
    // Update logout button to use confirmation (if not already done)
    if (strpos($content, 'onclick="confirmLogout()') === false) {
        $content = str_replace(
            'href="../../../logout.php"',
            'href="#" onclick="confirmLogout(); return false;"',
            $content
        );
    }
    
    // Find the closing tags and replace them
    $patterns = [
        '</body>
</html>',
        '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>',
        '</body></html>'
    ];
    
    $replaced = false;
    foreach ($patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $content = str_replace($pattern, $logoutModalAndScript, $content);
            $replaced = true;
            break;
        }
    }
    
    if (!$replaced) {
        // If no standard ending found, append before the last </html>
        $content = str_replace('</html>', $logoutModalAndScript, $content);
    }
    
    // Write updated content back to file
    if (file_put_contents($file, $content)) {
        echo "✅ Fixed: {$file}\n";
    } else {
        echo "❌ Failed to fix: {$file}\n";
    }
}

echo "\n✅ All provider logout modals fixed!\n";
?>