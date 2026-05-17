<?php
/**
 * Data Encryption Security Module
 * Handles encryption of sensitive student data and secure file storage
 */

class DataEncryption {
    private $conn;
    private $encryptionKey;
    private $cipher = 'AES-256-CBC';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
        $this->initializeEncryption();
    }
    
    /**
     * Initialize encryption key (should be stored securely in production)
     */
    private function initializeEncryption() {
        // In production, store this in environment variables or secure key management
        $this->encryptionKey = hash('sha256', 'ISKOLar_Encryption_Key_2024_Secure!' . date('Y-m'));
    }
    
    /**
     * Feature 1: Encryption of Sensitive Student Data
     * Encrypts GWA, income, and personal information
     */
    public function encryptSensitiveData($data) {
        try {
            $iv = random_bytes(16); // Generate random IV
            $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, 0, $iv);
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            // Combine IV and encrypted data
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData($encryptedData) {
        try {
            $data = base64_decode($encryptedData);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->encryptionKey, 0, $iv);
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store encrypted student profile data
     */
    public function storeEncryptedProfile($userId, $sensitiveData) {
        try {
            // Encrypt sensitive fields
            $encryptedData = [
                'encrypted_gwa' => $sensitiveData['gwa'] ? $this->encryptSensitiveData($sensitiveData['gwa']) : null,
                'encrypted_family_income' => $sensitiveData['family_monthly_income'] ? $this->encryptSensitiveData($sensitiveData['family_monthly_income']) : null,
                'encrypted_father_income' => $sensitiveData['father_income'] ? $this->encryptSensitiveData($sensitiveData['father_income']) : null,
                'encrypted_mother_income' => $sensitiveData['mother_income'] ? $this->encryptSensitiveData($sensitiveData['mother_income']) : null,
                'encrypted_mobile_number' => $sensitiveData['mobile_number'] ? $this->encryptSensitiveData($sensitiveData['mobile_number']) : null,
                'encrypted_birthdate' => $sensitiveData['birthdate'] ? $this->encryptSensitiveData($sensitiveData['birthdate']) : null
            ];
            
            // Check if record exists
            $stmt = $this->conn->prepare("SELECT id FROM encrypted_student_data WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $updateFields = [];
                $updateValues = [];
                
                foreach ($encryptedData as $field => $value) {
                    if ($value !== null) {
                        $updateFields[] = "$field = ?";
                        $updateValues[] = $value;
                    }
                }
                
                if (!empty($updateFields)) {
                    $updateValues[] = date('Y-m-d'); // encryption_key_id (date-based)
                    $updateValues[] = $userId;
                    
                    $sql = "UPDATE encrypted_student_data SET " . implode(', ', $updateFields) . 
                           ", encryption_key_id = ? WHERE user_id = ?";
                    
                    $stmt = $this->conn->prepare($sql);
                    return $stmt->execute($updateValues);
                }
            } else {
                // Insert new record
                $stmt = $this->conn->prepare("
                    INSERT INTO encrypted_student_data (
                        user_id, encrypted_gwa, encrypted_family_income, 
                        encrypted_father_income, encrypted_mother_income,
                        encrypted_mobile_number, encrypted_birthdate, encryption_key_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                return $stmt->execute([
                    $userId,
                    $encryptedData['encrypted_gwa'],
                    $encryptedData['encrypted_family_income'],
                    $encryptedData['encrypted_father_income'],
                    $encryptedData['encrypted_mother_income'],
                    $encryptedData['encrypted_mobile_number'],
                    $encryptedData['encrypted_birthdate'],
                    date('Y-m-d') // encryption_key_id
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store encrypted profile: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve and decrypt student profile data
     */
    public function getDecryptedProfile($userId, $userRole) {
        try {
            // Check data classification access
            $authSecurity = new AuthorizationSecurity();
            $accessibleFields = $authSecurity->getAccessibleFields($userRole, 'student_profiles');
            
            $stmt = $this->conn->prepare("SELECT * FROM encrypted_student_data WHERE user_id = ?");
            $stmt->execute([$userId]);
            $encryptedData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$encryptedData) {
                return [];
            }
            
            $decryptedData = [];
            
            // Decrypt only accessible fields
            $fieldMapping = [
                'gwa' => 'encrypted_gwa',
                'family_monthly_income' => 'encrypted_family_income',
                'father_income' => 'encrypted_father_income',
                'mother_income' => 'encrypted_mother_income',
                'mobile_number' => 'encrypted_mobile_number',
                'birthdate' => 'encrypted_birthdate'
            ];
            
            foreach ($fieldMapping as $originalField => $encryptedField) {
                if (in_array($originalField, $accessibleFields) && $encryptedData[$encryptedField]) {
                    $decrypted = $this->decryptSensitiveData($encryptedData[$encryptedField]);
                    if ($decrypted !== false) {
                        $decryptedData[$originalField] = $decrypted;
                    }
                }
            }
            
            return $decryptedData;
        } catch (Exception $e) {
            error_log("Failed to get decrypted profile: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Feature 2: Secure File Storage for Documents
     * Handles secure upload and storage of student documents
     */
    public function secureFileUpload($file, $userId, $documentType) {
        try {
            // Validate file type
            $allowedTypes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/jpg',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            if (!in_array($file['type'], $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid file type. Only PDF, JPG, PNG, and DOC files are allowed.'
                ];
            }
            
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return [
                    'success' => false,
                    'message' => 'File size too large. Maximum size is 5MB.'
                ];
            }
            
            // Create secure directory structure
            $uploadDir = 'secure_uploads/' . date('Y') . '/' . date('m') . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate secure filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $secureFilename = hash('sha256', $userId . $documentType . time() . $file['name']) . '.' . $extension;
            $filePath = $uploadDir . $secureFilename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Encrypt file content
                $fileContent = file_get_contents($filePath);
                $encryptedContent = $this->encryptSensitiveData($fileContent);
                
                if ($encryptedContent) {
                    // Replace original file with encrypted version
                    file_put_contents($filePath . '.enc', $encryptedContent);
                    unlink($filePath); // Remove unencrypted file
                    
                    // Store file metadata in database
                    $this->storeFileMetadata($userId, $documentType, $filePath . '.enc', $file['name'], $file['size']);
                    
                    return [
                        'success' => true,
                        'message' => 'File uploaded and encrypted successfully.',
                        'file_path' => $filePath . '.enc'
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Failed to upload file.'
            ];
        } catch (Exception $e) {
            error_log("Secure file upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'File upload failed due to server error.'
            ];
        }
    }
    
    /**
     * Store file metadata in database
     */
    private function storeFileMetadata($userId, $documentType, $filePath, $originalName, $fileSize) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO student_documents (
                    user_id, document_type, file_path, original_filename, 
                    file_size, is_encrypted, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            
            return $stmt->execute([$userId, $documentType, $filePath, $originalName, $fileSize]);
        } catch (Exception $e) {
            error_log("Failed to store file metadata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve and decrypt file
     */
    public function getDecryptedFile($fileId, $userId, $userRole) {
        try {
            // Verify user has access to this file
            $stmt = $this->conn->prepare("
                SELECT * FROM student_documents 
                WHERE id = ? AND (user_id = ? OR ? = 'admin')
            ");
            $stmt->execute([$fileId, $userId, $userRole]);
            $fileData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$fileData) {
                return [
                    'success' => false,
                    'message' => 'File not found or access denied.'
                ];
            }
            
            // Read and decrypt file
            $encryptedContent = file_get_contents($fileData['file_path']);
            $decryptedContent = $this->decryptSensitiveData($encryptedContent);
            
            if ($decryptedContent === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to decrypt file.'
                ];
            }
            
            return [
                'success' => true,
                'content' => $decryptedContent,
                'filename' => $fileData['original_filename'],
                'size' => $fileData['file_size']
            ];
        } catch (Exception $e) {
            error_log("Failed to get decrypted file: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'File retrieval failed.'
            ];
        }
    }
    
    /**
     * Get encryption statistics for monitoring
     */
    public function getEncryptionStats() {
        try {
            $stats = [];
            
            // Count encrypted profiles
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM encrypted_student_data");
            $stats['encrypted_profiles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Count encrypted files
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM student_documents WHERE is_encrypted = 1");
            $stats['encrypted_files'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Get encryption key rotation info
            $stmt = $this->conn->query("
                SELECT encryption_key_id, COUNT(*) as count 
                FROM encrypted_student_data 
                GROUP BY encryption_key_id
            ");
            $stats['key_usage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get encryption stats: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Data Classification Helper
 * Manages data sensitivity levels and access controls
 */
class DataClassificationHelper {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Get data classification for a field
     */
    public function getFieldClassification($tableName, $columnName) {
        try {
            $stmt = $this->conn->prepare("
                SELECT classification, encryption_required, access_roles
                FROM data_classification
                WHERE table_name = ? AND column_name = ?
            ");
            $stmt->execute([$tableName, $columnName]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get field classification: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Filter data based on user role and classification
     */
    public function filterDataByClassification($data, $tableName, $userRole) {
        $filteredData = [];
        
        foreach ($data as $field => $value) {
            $classification = $this->getFieldClassification($tableName, $field);
            
            if (!$classification) {
                // If no classification found, treat as public
                $filteredData[$field] = $value;
                continue;
            }
            
            $accessRoles = json_decode($classification['access_roles'], true) ?? [];
            
            if (in_array($userRole, $accessRoles)) {
                $filteredData[$field] = $value;
            } else {
                // Replace with masked value based on classification
                switch ($classification['classification']) {
                    case 'SENSITIVE':
                        $filteredData[$field] = '***SENSITIVE***';
                        break;
                    case 'CONFIDENTIAL':
                        $filteredData[$field] = '***CONFIDENTIAL***';
                        break;
                    default:
                        $filteredData[$field] = $value;
                }
            }
        }
        
        return $filteredData;
    }
}
?>