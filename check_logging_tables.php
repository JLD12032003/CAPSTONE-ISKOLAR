<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "📋 Checking Logging Tables...\n";
    echo "============================\n";
    
    $tables = [
        'login_attempts' => 'Login attempt tracking',
        'admin_activity_logs' => 'Admin activity logging', 
        'system_activity_logs' => 'System activity logging',
        'security_events' => 'Security incident tracking',
        'audit_trail' => 'Compliance audit trail'
    ];
    
    foreach ($tables as $table => $description) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "✅ {$table}: {$count} records ({$description})\n";
        } catch (Exception $e) {
            echo "❌ {$table}: Table not found\n";
        }
    }
    
    echo "\n📊 Sample Queries You Can Now Run:\n";
    echo "==================================\n";
    
    echo "-- Login attempts (last 50)\n";
    echo "SELECT id, email, ip_address, attempt_type as status, created_at as attempt_time\n";
    echo "FROM login_attempts\n";
    echo "ORDER BY created_at DESC\n";
    echo "LIMIT 50;\n\n";
    
    echo "-- Admin activities (last 50)\n";
    echo "SELECT id, admin_id, action_type, risk_level, created_at\n";
    echo "FROM admin_activity_logs\n";
    echo "ORDER BY created_at DESC\n";
    echo "LIMIT 50;\n\n";
    
    echo "-- Security events\n";
    echo "SELECT id, event_type, severity, event_description, created_at\n";
    echo "FROM security_events\n";
    echo "ORDER BY created_at DESC\n";
    echo "LIMIT 50;\n\n";
    
    echo "-- System activities\n";
    echo "SELECT id, user_type, action_type, action_description, created_at\n";
    echo "FROM system_activity_logs\n";
    echo "ORDER BY created_at DESC\n";
    echo "LIMIT 50;\n\n";
    
    echo "🔐 For Secure Access:\n";
    echo "====================\n";
    echo "Use the command-line decryption tool:\n";
    echo "php decrypt_logs.php\n\n";
    
    echo "Or access via database with these views:\n";
    echo "- admin_activity_summary (non-sensitive admin data)\n";
    echo "- security_dashboard (security events overview)\n";
    echo "- recent_login_attempts (login attempt summary)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>