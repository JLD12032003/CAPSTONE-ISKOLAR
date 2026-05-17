<?php
/**
 * Update VP and President email addresses
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->connect();

echo "<h1>Updating VP and President Email Addresses</h1>\n";

try {
    // Update the email addresses for Davao Central College
    $stmt = $conn->prepare("
        UPDATE schools 
        SET 
            vp_email = ?,
            president_email = ?
        WHERE id = 1
    ");
    
    $vpEmail = 'jhonlloyddioso1@gmail.com';
    $presidentEmail = 'diosojhonlloyd0@gmail.com';
    
    $result = $stmt->execute([$vpEmail, $presidentEmail]);
    
    if ($result) {
        echo "<p style='color: green;'>✓ Successfully updated email addresses!</p>\n";
        
        // Also update the school_approval_config table if it exists
        $stmt = $conn->prepare("
            UPDATE school_approval_config 
            SET 
                vp_email = ?,
                president_email = ?
            WHERE school_id = 1
        ");
        
        try {
            $stmt->execute([$vpEmail, $presidentEmail]);
            echo "<p style='color: green;'>✓ Updated school_approval_config table as well!</p>\n";
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                echo "<p style='color: blue;'>ℹ school_approval_config table doesn't exist (this is normal)</p>\n";
            } else {
                throw $e;
            }
        }
        
    } else {
        echo "<p style='color: red;'>✗ Failed to update email addresses!</p>\n";
    }
    
    // Show current configuration
    $stmt = $conn->prepare("SELECT school_name, email, committee_email, vp_email, president_email FROM schools WHERE id = 1");
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        echo "<h2>Updated School Configuration:</h2>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Field</th><th>Value</th></tr>\n";
        echo "<tr><td>School Name</td><td>" . htmlspecialchars($school['school_name']) . "</td></tr>\n";
        echo "<tr><td>Admin Email</td><td>" . htmlspecialchars($school['email']) . "</td></tr>\n";
        echo "<tr><td>Committee Email</td><td>" . htmlspecialchars($school['committee_email']) . "</td></tr>\n";
        echo "<tr><td><strong>VP Email</strong></td><td><strong style='color: green;'>" . htmlspecialchars($school['vp_email']) . "</strong></td></tr>\n";
        echo "<tr><td><strong>President Email</strong></td><td><strong style='color: green;'>" . htmlspecialchars($school['president_email']) . "</strong></td></tr>\n";
        echo "</table>\n";
    }
    
    echo "<h2>Email Configuration Summary:</h2>\n";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>\n";
    echo "<p><strong>✅ Email addresses successfully updated!</strong></p>\n";
    echo "<ul>\n";
    echo "<li><strong>VP Email:</strong> {$vpEmail}</li>\n";
    echo "<li><strong>President Email:</strong> {$presidentEmail}</li>\n";
    echo "</ul>\n";
    echo "<p>These emails will now be used for the scholarship approval workflow:</p>\n";
    echo "<ol>\n";
    echo "<li>School Admin reviews and forwards to Committee</li>\n";
    echo "<li>Committee approves and automatically forwards to VP</li>\n";
    echo "<li>VP approves and automatically forwards to President</li>\n";
    echo "<li>President gives final approval and scholarship is published</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h3>Next Steps:</h3>\n";
echo "<ul>\n";
echo "<li>Test the scholarship approval workflow with the new email addresses</li>\n";
echo "<li>Verify emails are sent to the correct recipients</li>\n";
echo "<li>Test the automatic forwarding system</li>\n";
echo "</ul>\n";

?>