<?php
/**
 * Log Decryption Tool - SYSTEM ADMIN ONLY
 * Use this to decrypt sensitive log data
 * 
 * SECURITY: This script should only be run by authorized system administrators
 * Run from command line only for maximum security
 */

// SECURITY CHECK: Only allow command line execution
if (php_sapi_name() !== 'cli') {
    die("🚫 SECURITY ERROR: This script can only be run from command line for security reasons.\n");
}

require_once 'config/database.php';
require_once 'app/core/LogEncryption.php';

// Configuration
$SYSTEM_ADMIN_ID = 1; // Change this to your system admin user ID
$MAX_RECORDS = 20;    // Maximum records to decrypt per query

echo "🔓 ISKOLar Log Decryption Tool\n";
echo "============================\n";
echo "⚠️  AUTHORIZED PERSONNEL ONLY\n";
echo "🔐 All activities are logged and monitored\n\n";

try {
    $database = new Database();
    $conn = $database->connect();
    $encryption = new LogEncryption();
    
    // Verify system admin has decryption permissions
    if (!$encryption->hasDecryptionPermission($SYSTEM_ADMIN_ID)) {
        die("❌ ACCESS DENIED: User ID {$SYSTEM_ADMIN_ID} does not have decryption permissions.\n");
    }
    
    echo "✅ Access granted for System Admin ID: {$SYSTEM_ADMIN_ID}\n\n";
    
    // Menu system
    while (true) {
        echo "📋 SELECT LOG TYPE TO DECRYPT:\n";
        echo "1. Admin Activity Details\n";
        echo "2. Security Event Evidence\n";
        echo "3. System Activity Metadata\n";
        echo "4. Audit Trail Sensitive Data\n";
        echo "5. Show Log Statistics\n";
        echo "6. Search Logs by Date Range\n";
        echo "0. Exit\n\n";
        
        echo "Enter your choice (0-6): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                decryptAdminActivities($conn, $encryption, $SYSTEM_ADMIN_ID, $MAX_RECORDS);
                break;
            case '2':
                decryptSecurityEvents($conn, $encryption, $SYSTEM_ADMIN_ID, $MAX_RECORDS);
                break;
            case '3':
                decryptSystemActivities($conn, $encryption, $SYSTEM_ADMIN_ID, $MAX_RECORDS);
                break;
            case '4':
                decryptAuditTrail($conn, $encryption, $SYSTEM_ADMIN_ID, $MAX_RECORDS);
                break;
            case '5':
                showLogStatistics($conn);
                break;
            case '6':
                searchLogsByDateRange($conn, $encryption, $SYSTEM_ADMIN_ID);
                break;
            case '0':
                echo "👋 Exiting securely...\n";
                exit(0);
            default:
                echo "❌ Invalid choice. Please try again.\n\n";
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
        echo "\n" . str_repeat("=", 50) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Decrypt Admin Activity Details
 */
function decryptAdminActivities($conn, $encryption, $adminId, $maxRecords) {
    echo "🔓 DECRYPTING ADMIN ACTIVITY DETAILS\n";
    echo "===================================\n";
    
    echo "Enter hours to look back (default 24): ";
    $hours = trim(fgets(STDIN));
    $hours = is_numeric($hours) ? intval($hours) : 24;
    
    $stmt = $conn->prepare("
        SELECT 
            aal.id,
            aal.admin_id,
            u.fullname as admin_name,
            u.email as admin_email,
            aal.action_type,
            aal.action_description,
            aal.entity_type,
            aal.entity_id,
            aal.encrypted_details,
            aal.risk_level,
            aal.ip_address,
            aal.created_at
        FROM admin_activity_logs aal
        JOIN users u ON aal.admin_id = u.id
        WHERE aal.encrypted_details IS NOT NULL 
        AND aal.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY aal.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$hours, $maxRecords]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "ℹ️  No encrypted admin activities found in the last {$hours} hours.\n";
        return;
    }
    
    echo "📊 Found " . count($logs) . " encrypted admin activities:\n\n";
    
    foreach ($logs as $log) {
        echo "🔍 LOG ID: {$log['id']}\n";
        echo "👤 Admin: {$log['admin_name']} ({$log['admin_email']})\n";
        echo "🎯 Action: {$log['action_type']}\n";
        echo "📝 Description: {$log['action_description']}\n";
        echo "🏷️  Entity: " . ($log['entity_type'] ? "{$log['entity_type']} #{$log['entity_id']}" : "N/A") . "\n";
        echo "⚠️  Risk Level: {$log['risk_level']}\n";
        echo "🌐 IP Address: {$log['ip_address']}\n";
        echo "📅 Created: {$log['created_at']}\n";
        
        try {
            $decryptedDetails = $encryption->decryptLogData(
                $log['encrypted_details'], 
                $adminId, 
                'admin_logs_key'
            );
            echo "🔓 DECRYPTED DETAILS:\n";
            echo json_encode($decryptedDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ Decryption Error: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 60) . "\n";
    }
}

/**
 * Decrypt Security Event Evidence
 */
function decryptSecurityEvents($conn, $encryption, $adminId, $maxRecords) {
    echo "🔓 DECRYPTING SECURITY EVENT EVIDENCE\n";
    echo "====================================\n";
    
    echo "Enter days to look back (default 7): ";
    $days = trim(fgets(STDIN));
    $days = is_numeric($days) ? intval($days) : 7;
    
    $stmt = $conn->prepare("
        SELECT 
            se.id,
            se.event_type,
            se.severity,
            se.event_description,
            se.encrypted_evidence,
            se.ip_address,
            se.resolved,
            se.created_at,
            u.fullname as user_name,
            u.email as user_email
        FROM security_events se
        LEFT JOIN users u ON se.user_id = u.id
        WHERE se.encrypted_evidence IS NOT NULL 
        AND se.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY 
            CASE se.severity 
                WHEN 'CRITICAL' THEN 1 
                WHEN 'HIGH' THEN 2 
                WHEN 'MEDIUM' THEN 3 
                ELSE 4 
            END,
            se.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$days, $maxRecords]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "ℹ️  No encrypted security events found in the last {$days} days.\n";
        return;
    }
    
    echo "📊 Found " . count($logs) . " encrypted security events:\n\n";
    
    foreach ($logs as $log) {
        echo "🚨 EVENT ID: {$log['id']}\n";
        echo "🏷️  Type: {$log['event_type']}\n";
        echo "⚠️  Severity: {$log['severity']}\n";
        echo "📝 Description: {$log['event_description']}\n";
        echo "👤 User: " . ($log['user_name'] ? "{$log['user_name']} ({$log['user_email']})" : "System/Unknown") . "\n";
        echo "🌐 IP Address: {$log['ip_address']}\n";
        echo "✅ Resolved: " . ($log['resolved'] ? "Yes" : "No") . "\n";
        echo "📅 Created: {$log['created_at']}\n";
        
        try {
            $decryptedEvidence = $encryption->decryptLogData(
                $log['encrypted_evidence'], 
                $adminId, 
                'sensitive_data_key'
            );
            echo "🔓 DECRYPTED EVIDENCE:\n";
            echo json_encode($decryptedEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ Decryption Error: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 60) . "\n";
    }
}

/**
 * Decrypt System Activity Metadata
 */
function decryptSystemActivities($conn, $encryption, $adminId, $maxRecords) {
    echo "🔓 DECRYPTING SYSTEM ACTIVITY METADATA\n";
    echo "=====================================\n";
    
    echo "Enter hours to look back (default 24): ";
    $hours = trim(fgets(STDIN));
    $hours = is_numeric($hours) ? intval($hours) : 24;
    
    $stmt = $conn->prepare("
        SELECT 
            sal.id,
            sal.user_type,
            sal.action_type,
            sal.action_description,
            sal.entity_type,
            sal.entity_id,
            sal.encrypted_metadata,
            sal.ip_address,
            sal.created_at,
            u.fullname as user_name,
            u.email as user_email
        FROM system_activity_logs sal
        LEFT JOIN users u ON sal.user_id = u.id
        WHERE sal.encrypted_metadata IS NOT NULL 
        AND sal.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY sal.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$hours, $maxRecords]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "ℹ️  No encrypted system activities found in the last {$hours} hours.\n";
        return;
    }
    
    echo "📊 Found " . count($logs) . " encrypted system activities:\n\n";
    
    foreach ($logs as $log) {
        echo "🔍 LOG ID: {$log['id']}\n";
        echo "👤 User: " . ($log['user_name'] ? "{$log['user_name']} ({$log['user_email']})" : "System") . "\n";
        echo "🏷️  User Type: {$log['user_type']}\n";
        echo "🎯 Action: {$log['action_type']}\n";
        echo "📝 Description: {$log['action_description']}\n";
        echo "🏷️  Entity: " . ($log['entity_type'] ? "{$log['entity_type']} #{$log['entity_id']}" : "N/A") . "\n";
        echo "🌐 IP Address: {$log['ip_address']}\n";
        echo "📅 Created: {$log['created_at']}\n";
        
        try {
            $decryptedMetadata = $encryption->decryptLogData(
                $log['encrypted_metadata'], 
                $adminId, 
                'sensitive_data_key'
            );
            echo "🔓 DECRYPTED METADATA:\n";
            echo json_encode($decryptedMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ Decryption Error: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 60) . "\n";
    }
}

/**
 * Decrypt Audit Trail Sensitive Data
 */
function decryptAuditTrail($conn, $encryption, $adminId, $maxRecords) {
    echo "🔓 DECRYPTING AUDIT TRAIL SENSITIVE DATA\n";
    echo "=======================================\n";
    
    echo "Enter days to look back (default 7): ";
    $days = trim(fgets(STDIN));
    $days = is_numeric($days) ? intval($days) : 7;
    
    $stmt = $conn->prepare("
        SELECT 
            at.id,
            at.audit_type,
            at.table_name,
            at.record_id,
            at.action,
            at.old_values,
            at.new_values,
            at.encrypted_sensitive_data,
            at.compliance_flags,
            at.created_at,
            u.fullname as user_name,
            u.email as user_email,
            a.fullname as admin_name,
            a.email as admin_email
        FROM audit_trail at
        LEFT JOIN users u ON at.user_id = u.id
        LEFT JOIN users a ON at.admin_id = a.id
        WHERE at.encrypted_sensitive_data IS NOT NULL 
        AND at.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY at.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$days, $maxRecords]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "ℹ️  No encrypted audit trail data found in the last {$days} days.\n";
        return;
    }
    
    echo "📊 Found " . count($logs) . " encrypted audit trail entries:\n\n";
    
    foreach ($logs as $log) {
        echo "🔍 AUDIT ID: {$log['id']}\n";
        echo "🏷️  Type: {$log['audit_type']}\n";
        echo "🗃️  Table: {$log['table_name']}\n";
        echo "🆔 Record ID: {$log['record_id']}\n";
        echo "🎯 Action: {$log['action']}\n";
        echo "👤 User: " . ($log['user_name'] ? "{$log['user_name']} ({$log['user_email']})" : "N/A") . "\n";
        echo "👨‍💼 Admin: " . ($log['admin_name'] ? "{$log['admin_name']} ({$log['admin_email']})" : "N/A") . "\n";
        echo "📅 Created: {$log['created_at']}\n";
        
        if ($log['old_values']) {
            echo "📋 Old Values: " . $log['old_values'] . "\n";
        }
        if ($log['new_values']) {
            echo "📋 New Values: " . $log['new_values'] . "\n";
        }
        if ($log['compliance_flags']) {
            echo "🏛️  Compliance: " . $log['compliance_flags'] . "\n";
        }
        
        try {
            $decryptedSensitiveData = $encryption->decryptLogData(
                $log['encrypted_sensitive_data'], 
                $adminId, 
                'audit_trail_key'
            );
            echo "🔓 DECRYPTED SENSITIVE DATA:\n";
            echo json_encode($decryptedSensitiveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ Decryption Error: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 60) . "\n";
    }
}

/**
 * Show Log Statistics
 */
function showLogStatistics($conn) {
    echo "📊 LOG STATISTICS DASHBOARD\n";
    echo "==========================\n";
    
    // Login attempts statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN attempt_type = 'SUCCESS' THEN 1 END) as success,
            COUNT(CASE WHEN attempt_type = 'FAILED' THEN 1 END) as failed,
            COUNT(CASE WHEN attempt_type = 'BLOCKED' THEN 1 END) as blocked,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
        FROM login_attempts
    ");
    $loginStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "🔐 LOGIN ATTEMPTS:\n";
    echo "  Total: {$loginStats['total']}\n";
    echo "  Success: {$loginStats['success']}\n";
    echo "  Failed: {$loginStats['failed']}\n";
    echo "  Blocked: {$loginStats['blocked']}\n";
    echo "  Last 24h: {$loginStats['last_24h']}\n\n";
    
    // Admin activities statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN risk_level = 'LOW' THEN 1 END) as low_risk,
            COUNT(CASE WHEN risk_level = 'MEDIUM' THEN 1 END) as medium_risk,
            COUNT(CASE WHEN risk_level = 'HIGH' THEN 1 END) as high_risk,
            COUNT(CASE WHEN risk_level = 'CRITICAL' THEN 1 END) as critical_risk,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
        FROM admin_activity_logs
    ");
    $adminStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "👨‍💼 ADMIN ACTIVITIES:\n";
    echo "  Total: {$adminStats['total']}\n";
    echo "  Low Risk: {$adminStats['low_risk']}\n";
    echo "  Medium Risk: {$adminStats['medium_risk']}\n";
    echo "  High Risk: {$adminStats['high_risk']}\n";
    echo "  Critical Risk: {$adminStats['critical_risk']}\n";
    echo "  Last 24h: {$adminStats['last_24h']}\n\n";
    
    // Security events statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN severity = 'LOW' THEN 1 END) as low_severity,
            COUNT(CASE WHEN severity = 'MEDIUM' THEN 1 END) as medium_severity,
            COUNT(CASE WHEN severity = 'HIGH' THEN 1 END) as high_severity,
            COUNT(CASE WHEN severity = 'CRITICAL' THEN 1 END) as critical_severity,
            COUNT(CASE WHEN resolved = FALSE THEN 1 END) as unresolved,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
        FROM security_events
    ");
    $securityStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "🚨 SECURITY EVENTS:\n";
    echo "  Total: {$securityStats['total']}\n";
    echo "  Low Severity: {$securityStats['low_severity']}\n";
    echo "  Medium Severity: {$securityStats['medium_severity']}\n";
    echo "  High Severity: {$securityStats['high_severity']}\n";
    echo "  Critical Severity: {$securityStats['critical_severity']}\n";
    echo "  Unresolved: {$securityStats['unresolved']}\n";
    echo "  Last 24h: {$securityStats['last_24h']}\n\n";
    
    // System activities statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN user_type = 'admin' THEN 1 END) as admin_activities,
            COUNT(CASE WHEN user_type = 'provider' THEN 1 END) as provider_activities,
            COUNT(CASE WHEN user_type = 'student' THEN 1 END) as student_activities,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
        FROM system_activity_logs
    ");
    $systemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "⚙️  SYSTEM ACTIVITIES:\n";
    echo "  Total: {$systemStats['total']}\n";
    echo "  Admin: {$systemStats['admin_activities']}\n";
    echo "  Provider: {$systemStats['provider_activities']}\n";
    echo "  Student: {$systemStats['student_activities']}\n";
    echo "  Last 24h: {$systemStats['last_24h']}\n\n";
}

/**
 * Search Logs by Date Range
 */
function searchLogsByDateRange($conn, $encryption, $adminId) {
    echo "🔍 SEARCH LOGS BY DATE RANGE\n";
    echo "===========================\n";
    
    echo "Enter start date (YYYY-MM-DD): ";
    $startDate = trim(fgets(STDIN));
    
    echo "Enter end date (YYYY-MM-DD): ";
    $endDate = trim(fgets(STDIN));
    
    echo "Select log type:\n";
    echo "1. Admin Activities\n";
    echo "2. Security Events\n";
    echo "3. System Activities\n";
    echo "Enter choice (1-3): ";
    $logType = trim(fgets(STDIN));
    
    $tableName = '';
    $encryptedColumn = '';
    $keyName = '';
    
    switch ($logType) {
        case '1':
            $tableName = 'admin_activity_logs';
            $encryptedColumn = 'encrypted_details';
            $keyName = 'admin_logs_key';
            break;
        case '2':
            $tableName = 'security_events';
            $encryptedColumn = 'encrypted_evidence';
            $keyName = 'sensitive_data_key';
            break;
        case '3':
            $tableName = 'system_activity_logs';
            $encryptedColumn = 'encrypted_metadata';
            $keyName = 'sensitive_data_key';
            break;
        default:
            echo "❌ Invalid choice.\n";
            return;
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM {$tableName}
        WHERE {$encryptedColumn} IS NOT NULL 
        AND DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$startDate, $endDate]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "ℹ️  No encrypted logs found in the specified date range.\n";
        return;
    }
    
    echo "📊 Found " . count($logs) . " encrypted logs between {$startDate} and {$endDate}:\n\n";
    
    foreach ($logs as $log) {
        echo "🔍 LOG ID: {$log['id']}\n";
        echo "📅 Created: {$log['created_at']}\n";
        
        try {
            $decryptedData = $encryption->decryptLogData(
                $log[$encryptedColumn], 
                $adminId, 
                $keyName
            );
            echo "🔓 DECRYPTED DATA:\n";
            echo json_encode($decryptedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (Exception $e) {
            echo "❌ Decryption Error: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 40) . "\n";
    }
}
?>