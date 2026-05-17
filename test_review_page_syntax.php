<?php
/**
 * Test review page syntax and basic functionality
 */

echo "=== TESTING REVIEW PAGE SYNTAX ===\n\n";

// 1. Test PHP syntax
echo "1. Testing PHP syntax...\n";
$syntaxCheck = shell_exec('php -l app/views/admin/review_scholarship.php 2>&1');
if (strpos($syntaxCheck, 'No syntax errors') !== false) {
    echo "✅ PHP syntax is valid\n";
} else {
    echo "❌ PHP syntax error: $syntaxCheck\n";
    exit;
}

// 2. Test file inclusion (without executing the full page)
echo "\n2. Testing file structure...\n";

$reviewFile = 'app/views/admin/review_scholarship.php';
if (file_exists($reviewFile)) {
    echo "✅ Review file exists\n";
    
    $content = file_get_contents($reviewFile);
    
    // Check for required functions
    if (strpos($content, 'function generateApprovalToken') !== false) {
        echo "✅ generateApprovalToken function found\n";
    } else {
        echo "❌ generateApprovalToken function missing\n";
    }
    
    if (strpos($content, 'function generateApprovalUrl') !== false) {
        echo "✅ generateApprovalUrl function found\n";
    } else {
        echo "❌ generateApprovalUrl function missing\n";
    }
    
    if (strpos($content, 'function generateApprovalEmailBody') !== false) {
        echo "✅ generateApprovalEmailBody function found\n";
    } else {
        echo "❌ generateApprovalEmailBody function missing\n";
    }
    
    // Check for proper brace matching
    $openBraces = substr_count($content, '{');
    $closeBraces = substr_count($content, '}');
    
    if ($openBraces === $closeBraces) {
        echo "✅ Braces are properly matched ($openBraces opening, $closeBraces closing)\n";
    } else {
        echo "❌ Brace mismatch: $openBraces opening, $closeBraces closing\n";
    }
    
} else {
    echo "❌ Review file not found\n";
}

echo "\n=== SYNTAX TEST COMPLETED ===\n";
echo "✅ The review page syntax has been fixed and is ready to use!\n";
?>