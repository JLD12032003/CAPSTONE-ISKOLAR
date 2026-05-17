<?php
/**
 * LogEncryption Class
 * Handles encryption and decryption of sensitive log data
 * Only authorized personnel can decrypt logs
 */

class LogEncryption {
    private $conn;
    private $masterKey;
    private $algorithm = 'AES-256-CBC';
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->conn = (new Database())->connect();
        $this->initializeMasterKey();
    }
    
    /**
     * Initialize master encryption key
     * In production, this should be stored securely (environment variables, key management service)
     */
    private function initializeMasterKey() {
        // In production, get this from secure environment variable
        $this->masterKey = hash('sha256', 'ISKOLAR_LOG_ENCRYPTION_KEY_2024_SECURE', true);
    }
    
    /**
     * Encrypt sensitive data for logging
     */
    public function encryptLogData($data, $keyName = 'admin_logs_key') {
        try {
            if (empty($data)) {
                return null;
            }
            
            // Convert data to JSON if it's an array
            if (is_array($data)) {
                $data = json_encode($data);
            }
            
            // Generate a random IV
            $iv = random_bytes(16);
            
            // Get encryption key
            $encryptionKey = $this->getEncryptionKey($keyName);
            
            // Encrypt the data
            $encrypted = openssl_encrypt($data, $this->algorithm, $encryptionKey, 0, $iv);
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            // Combine IV and encrypted data
            $result = base64_encode($iv . $encrypted);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Log encryption error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Decrypt sensitive log data
     * Only users with proper permissions can decrypt
     */
    public function decryptLogData($encryptedData, $userId, $keyName = 'admin_logs_key') {
        try {
            // Check if user has decryption permissions
            if (!$this->hasDecryptionPermission($userId)) {
                throw new Exception('Access denied: Insufficient permissions to decrypt logs');
            }
            
            if (empty($encryptedData)) {
                return null;
            }
            
            // Decode the base64 data
            $data = base64_decode($encryptedData);
            
            if ($data === false) {
                throw new Exception('Invalid encrypted data format');
            }
            
            // Extract IV and encrypted content
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            // Get decryption key
            $decryptionKey = $this->getEncryptionKey($keyName);
            
            // Decrypt the data
            $decrypted = openssl_decrypt($encrypted, $this->algorithm, $decryptionKey, 0, $iv);
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            // Try to decode as JSON, return as string if not valid JSON
            $jsonDecoded = json_decode($decrypted, true);
            return $jsonDecoded !== null ? $jsonDecoded : $decrypted;
            
        } catch (Exception $e) {
            error_log("Log decryption error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get encryption key from database
     */
    private function getEncryptionKey($keyName) {
        $stmt = $this->conn->prepare("
            SELECT encrypted_key FROM encryption_keys 
            WHERE key_name = ? AND is_active = TRUE
        ");
        $stmt->execute([$keyName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("Encryption key not found: $keyName");
        }
        
        // In a real implementation, the key would be encrypted with the master key
        // For now, we'll use a derived key
        return hash('sha256', $this->masterKey . $keyName, true);
    }
    
    /**
     * Check if user has permission to decrypt logs
     */
    public function hasDecryptionPermission($userId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM log_access_permissions 
            WHERE user_id = ? 
            AND permission_level = 'decrypt_logs' 
            AND is_active = TRUE 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    /**
     * Check if user can read specific log categories
     */
    public function canReadLogCategory($userId, $category) {
        $stmt = $this->conn->prepare("
            SELECT log_categories FROM log_access_permissions 
            WHERE user_id = ? 
            AND is_active = TRUE 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($permissions as $permission) {
            $categories = json_decode($permission['log_categories'], true);
            if (in_array($category, $categories) || in_array('all', $categories)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rotate encryption keys (security best practice)
     */
    public function rotateEncryptionKey($keyName, $adminId) {
        try {
            $this->conn->beginTransaction();
            
            // Generate new key
            $newKey = random_bytes(32);
            $encryptedNewKey = base64_encode($newKey);
            
            // Update key version and mark old key as inactive
            $stmt = $this->conn->prepare("
                UPDATE encryption_keys 
                SET is_active = FALSE 
                WHERE key_name = ?
            ");
            $stmt->execute([$keyName]);
            
            // Insert new key
            $stmt = $this->conn->prepare("
                INSERT INTO encryption_keys (key_name, encrypted_key, key_version, created_by, last_rotated) 
                VALUES (?, ?, (SELECT COALESCE(MAX(key_version), 0) + 1 FROM encryption_keys WHERE key_name = ?), ?, NOW())
            ");
            $stmt->execute([$keyName, $encryptedNewKey, $keyName, $adminId]);
            
            $this->conn->commit();
            
            // Log the key rotation
            $this->logAdminActivity($adminId, 'SYSTEM_CONFIG', 'encryption_key', null, 
                "Rotated encryption key: $keyName", 'HIGH');
            
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Key rotation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log admin activity (used internally)
     */
    private function logAdminActivity($adminId, $actionType, $entityType, $entityId, $description, $riskLevel = 'LOW') {
        $stmt = $this->conn->prepare("
            INSERT INTO admin_activity_logs (
                admin_id, action_type, entity_type, entity_id, action_description,
                ip_address, user_agent, risk_level, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $adminId,
            $actionType,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'System',
            $riskLevel
        ]);
    }
}
?>