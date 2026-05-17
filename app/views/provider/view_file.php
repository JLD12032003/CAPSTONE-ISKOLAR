<?php
/**
 * Secure File Viewer for Provider
 * Allows providers to view uploaded application documents securely
 */

session_start();

// Check if user is logged in and is a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/../../../config/database.php';

$studentId = intval($_GET['student_id'] ?? 0);
$fileName = $_GET['file'] ?? '';

if (!$studentId || !$fileName) {
    http_response_code(400);
    exit('Invalid parameters');
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Verify that the provider has access to this student's application
    $stmt = $conn->prepare("
        SELECT sa.id, sa.documents
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        WHERE sa.student_id = ? AND s.provider_id = ?
    ");
    $stmt->execute([$studentId, $_SESSION['user_id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        http_response_code(403);
        exit('Access denied: No permission to view this file');
    }
    
    // Verify the file is in the application documents
    $documents = json_decode($application['documents'], true);
    $fileFound = false;
    
    if ($documents && is_array($documents)) {
        foreach ($documents as $docType => $docFileName) {
            if ($docFileName === $fileName) {
                $fileFound = true;
                break;
            }
        }
    }
    
    if (!$fileFound) {
        http_response_code(404);
        exit('File not found in application');
    }
    
    // Build file path - files are in app/views/uploads/applications/
    $filePath = __DIR__ . '/../uploads/applications/' . $studentId . '/' . $fileName;
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File not found on server');
    }
    
    // Get file info
    $fileSize = filesize($filePath);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Set appropriate content type
    $contentTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';
    
    // Set headers
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
    header('Cache-Control: private, max-age=3600');
    
    // Output file
    readfile($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Server error: ' . $e->getMessage());
}
?>