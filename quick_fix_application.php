<?php
/**
 * Quick Fix for Application Issue
 * Add validation and debugging to the application process
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔧 Applying Quick Fix for Application Issue...\n\n";
    
    // 1. Check current applications
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarship_applications");
    $currentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "📊 Current applications: {$currentCount}\n";
    
    // 2. Get valid scholarship IDs
    $stmt = $conn->query("SELECT id FROM scholarships WHERE status = 'Active'");
    $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Valid scholarship IDs: " . implode(', ', $validIds) . "\n";
    
    // 3. Create a simple test form to verify the process works
    echo "\n🧪 Creating test form...\n";
    
    $testFormHtml = '
<!DOCTYPE html>
<html>
<head>
    <title>Test Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Test Scholarship Application</h2>
        <form method="POST" action="app/views/student_home.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply">
            
            <div class="mb-3">
                <label for="scholarship_id" class="form-label">Scholarship ID</label>
                <select class="form-control" name="scholarship_id" required>
                    <option value="">Select Scholarship</option>';
    
    foreach ($validIds as $id) {
        $stmt = $conn->prepare("SELECT title FROM scholarships WHERE id = ?");
        $stmt->execute([$id]);
        $title = $stmt->fetch(PDO::FETCH_COLUMN);
        $testFormHtml .= "<option value=\"{$id}\">{$id} - {$title}</option>";
    }
    
    $testFormHtml .= '
                </select>
            </div>
            
            <div class="mb-3">
                <label for="personal_statement" class="form-label">Personal Statement</label>
                <textarea class="form-control" name="personal_statement" rows="3" required>This is a test personal statement.</textarea>
            </div>
            
            <div class="mb-3">
                <label for="why_deserve_scholarship" class="form-label">Why I Deserve This Scholarship</label>
                <textarea class="form-control" name="why_deserve_scholarship" rows="3" required>This is a test reason for deserving the scholarship.</textarea>
            </div>
            
            <div class="mb-3">
                <label for="academic_transcript" class="form-label">Academic Transcript</label>
                <input type="file" class="form-control" name="academic_transcript" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Test Application</button>
        </form>
    </div>
</body>
</html>';
    
    file_put_contents('test_application_form.html', $testFormHtml);
    echo "✅ Created test_application_form.html\n";
    
    echo "\n📋 Instructions:\n";
    echo "===============\n";
    echo "1. Open test_application_form.html in your browser\n";
    echo "2. Fill out the form and submit\n";
    echo "3. Check if the application is created successfully\n";
    echo "4. If it works, the issue is in the JavaScript modal\n";
    echo "5. If it fails, the issue is in the PHP processing\n\n";
    
    echo "🌐 Test URL: http://localhost/ISKOLAR_3RD_YEAR_EDITION/test_application_form.html\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>