<?php
/**
 * Test Direct Application Creation
 * Test if we can create an application directly in the database
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🧪 Testing Direct Application Creation...\n\n";
    
    // Get a valid scholarship ID
    $stmt = $conn->query("SELECT id, title FROM scholarships WHERE status = 'Active' LIMIT 1");
    $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scholarship) {
        echo "❌ No active scholarships found\n";
        exit(1);
    }
    
    echo "📋 Using scholarship: ID {$scholarship['id']} - {$scholarship['title']}\n";
    
    // Get a valid student ID
    $stmt = $conn->query("SELECT id, fullname FROM users WHERE user_type = 'student' LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo "❌ No students found\n";
        exit(1);
    }
    
    echo "👤 Using student: ID {$student['id']} - {$student['fullname']}\n";
    
    // Check if application already exists
    $stmt = $conn->prepare("SELECT id FROM scholarship_applications WHERE scholarship_id = ? AND student_id = ?");
    $stmt->execute([$scholarship['id'], $student['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "ℹ️  Application already exists with ID: {$existing['id']}\n";
        echo "✅ This confirms the foreign key constraints are working\n";
    } else {
        echo "\n🔧 Creating test application...\n";
        
        $stmt = $conn->prepare("
            INSERT INTO scholarship_applications (
                scholarship_id, student_id, personal_statement, 
                why_deserve_scholarship, documents, status
            ) VALUES (?, ?, ?, ?, ?, 'Submitted')
        ");
        
        $testDocuments = json_encode([
            'academic_transcript' => 'test_transcript.pdf',
            'good_moral_certificate' => 'test_moral.pdf'
        ]);
        
        if ($stmt->execute([
            $scholarship['id'],
            $student['id'],
            'This is a test personal statement for debugging purposes.',
            'I deserve this scholarship because this is a test application.',
            $testDocuments
        ])) {
            $applicationId = $conn->lastInsertId();
            echo "✅ Test application created successfully with ID: {$applicationId}\n";
            
            // Verify it was created
            $stmt = $conn->prepare("SELECT * FROM scholarship_applications WHERE id = ?");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($application) {
                echo "✅ Application verified in database:\n";
                echo "   - Scholarship ID: {$application['scholarship_id']}\n";
                echo "   - Student ID: {$application['student_id']}\n";
                echo "   - Status: {$application['status']}\n";
                echo "   - Can Edit Documents: " . ($application['can_edit_documents'] ? 'Yes' : 'No') . "\n";
            }
        } else {
            echo "❌ Failed to create test application\n";
            $errorInfo = $stmt->errorInfo();
            echo "Error: " . $errorInfo[2] . "\n";
        }
    }
    
    echo "\n📊 Current application count: ";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarship_applications");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "{$count}\n";
    
    echo "\n✅ Direct application test completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>