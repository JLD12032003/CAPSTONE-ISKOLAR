<?php
// Setup Approval Workflow Database Schema
echo "<h1>ISKOLar Approval Workflow Setup</h1>";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=localhost;dbname=ISKOLAR_101", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connected to ISKOLAR_101 database</p>";
    
    // Create partnership_requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partnership_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            school_id INT NOT NULL,
            
            -- Request Details
            organization_name VARCHAR(255) NOT NULL,
            organization_type ENUM('Individual', 'Foundation', 'Corporation', 'NGO', 'Government') NOT NULL,
            contact_person VARCHAR(255) NOT NULL,
            contact_email VARCHAR(255) NOT NULL,
            contact_phone VARCHAR(50),
            
            -- Partnership Proposal
            partnership_title VARCHAR(255) NOT NULL,
            partnership_description TEXT NOT NULL,
            proposed_scholarship_amount DECIMAL(12,2) NOT NULL,
            proposed_scholarship_slots INT NOT NULL,
            partnership_duration_years INT DEFAULT 1,
            
            -- Supporting Documents
            registration_documents JSON,
            financial_statements JSON,
            partnership_proposal_document VARCHAR(255),
            
            -- Workflow State Management
            current_stage ENUM(
                'PENDING',
                'COMMITTEE_REVIEW', 
                'VP_REVIEW',
                'PRESIDENT_REVIEW',
                'APPROVED',
                'REJECTED'
            ) DEFAULT 'PENDING',
            
            -- Status Tracking
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            committee_notified_at TIMESTAMP NULL,
            vp_notified_at TIMESTAMP NULL,
            president_notified_at TIMESTAMP NULL,
            final_decision_at TIMESTAMP NULL,
            
            -- Rejection Tracking
            rejected_by_stage VARCHAR(50) NULL,
            rejection_reason TEXT NULL,
            
            -- Partnership Activation
            partnership_active TINYINT(1) DEFAULT 0,
            partnership_start_date DATE NULL,
            partnership_end_date DATE NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            
            INDEX idx_current_stage (current_stage),
            INDEX idx_provider_school (provider_id, school_id),
            INDEX idx_submitted_at (submitted_at)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Partnership requests table created</p>";
    
    // Create approval_stages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS approval_stages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partnership_request_id INT NOT NULL,
            
            -- Stage Information
            stage_name ENUM('COMMITTEE_REVIEW', 'VP_REVIEW', 'PRESIDENT_REVIEW') NOT NULL,
            stage_order INT NOT NULL,
            
            -- Email Details
            recipient_email VARCHAR(255) NOT NULL,
            recipient_role VARCHAR(50) NOT NULL,
            email_sent_at TIMESTAMP NULL,
            email_subject VARCHAR(255),
            
            -- Token Security
            approval_token VARCHAR(255) UNIQUE NOT NULL,
            token_expires_at DATETIME NOT NULL,
            token_used TINYINT(1) DEFAULT 0,
            token_used_at TIMESTAMP NULL,
            
            -- Decision Tracking
            decision ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
            decided_at TIMESTAMP NULL,
            decision_notes TEXT,
            decided_by_ip VARCHAR(45),
            
            -- Metadata
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (partnership_request_id) REFERENCES partnership_requests(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_stage_per_request (partnership_request_id, stage_name),
            INDEX idx_approval_token (approval_token),
            INDEX idx_token_expires (token_expires_at),
            INDEX idx_stage_order (stage_order)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Approval stages table created</p>";
    
    // Create approval_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS approval_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partnership_request_id INT NOT NULL,
            
            -- Event Details
            event_type ENUM(
                'REQUEST_SUBMITTED',
                'EMAIL_SENT',
                'EMAIL_OPENED',
                'APPROVAL_CLICKED',
                'REJECTION_CLICKED',
                'STAGE_APPROVED',
                'STAGE_REJECTED',
                'WORKFLOW_COMPLETED',
                'WORKFLOW_TERMINATED',
                'TOKEN_EXPIRED',
                'INVALID_ACCESS'
            ) NOT NULL,
            
            -- Context Information
            stage_name VARCHAR(50),
            recipient_role VARCHAR(50),
            actor_email VARCHAR(255),
            
            -- Event Data
            event_description TEXT,
            event_metadata JSON,
            
            -- Technical Details
            ip_address VARCHAR(45),
            user_agent TEXT,
            
            -- Timestamp
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (partnership_request_id) REFERENCES partnership_requests(id) ON DELETE CASCADE,
            
            INDEX idx_event_type (event_type),
            INDEX idx_logged_at (logged_at),
            INDEX idx_partnership_request (partnership_request_id)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Approval logs table created</p>";
    
    // Create school_roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS school_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            
            -- Role Configuration
            role_name ENUM('committee', 'vp', 'president') NOT NULL,
            role_title VARCHAR(255) NOT NULL,
            
            -- Contact Information
            email_address VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            phone_number VARCHAR(50),
            
            -- Role Settings
            is_active TINYINT(1) DEFAULT 1,
            approval_order INT NOT NULL,
            
            -- Email Configuration
            email_template_id VARCHAR(50),
            auto_reminder_days INT DEFAULT 3,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_role_per_school (school_id, role_name),
            INDEX idx_approval_order (approval_order),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ School roles table created</p>";
    
    // Create email_templates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(100) UNIQUE NOT NULL,
            
            -- Template Content
            subject_template VARCHAR(255) NOT NULL,
            body_template TEXT NOT NULL,
            
            -- Template Variables
            available_variables JSON,
            
            -- Settings
            is_active TINYINT(1) DEFAULT 1,
            template_type ENUM('committee', 'vp', 'president', 'notification') NOT NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_template_type (template_type)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Email templates table created</p>";
    
    // Insert default school roles for Davao Central College
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM school_roles WHERE school_id = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO school_roles (school_id, role_name, role_title, email_address, approval_order) VALUES
            (1, 'committee', 'Scholarship Committee', 'committee@davaocentralcollege.edu.ph', 1),
            (1, 'vp', 'Vice President for Academic Affairs', 'vp@davaocentralcollege.edu.ph', 2),
            (1, 'president', 'School President', 'president@davaocentralcollege.edu.ph', 3)
        ");
        echo "<p>✅ Default school roles created for Davao Central College</p>";
    }
    
    // Insert email templates
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_templates");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO email_templates (template_name, subject_template, body_template, template_type, available_variables) VALUES
            ('committee_approval', 'Partnership Request - {{organization_name}}', 
             'Dear Scholarship Committee,\\n\\nA new partnership request requires your review from {{organization_name}}.\\n\\nPartnership Title: {{partnership_title}}\\nProposed Amount: ₱{{proposed_amount}}\\nProposed Slots: {{proposed_slots}}\\n\\nPlease review and make your decision:\\n\\nApprove: {{approve_link}}\\nReject: {{reject_link}}\\n\\nThis approval link expires in 7 days.\\n\\nBest regards,\\nISKOLar System', 
             'committee', '[\"organization_name\", \"partnership_title\", \"proposed_amount\", \"proposed_slots\", \"approve_link\", \"reject_link\"]'),
            
            ('vp_approval', 'Partnership Request - VP Review Required', 
             'Dear Vice President,\\n\\nThe scholarship committee has approved a partnership request that now requires your review.\\n\\nOrganization: {{organization_name}}\\nPartnership Title: {{partnership_title}}\\nProposed Amount: ₱{{proposed_amount}}\\nProposed Slots: {{proposed_slots}}\\n\\nCommittee Decision: APPROVED\\n\\nPlease review and make your decision:\\n\\nApprove: {{approve_link}}\\nReject: {{reject_link}}\\n\\nThis approval link expires in 7 days.\\n\\nBest regards,\\nISKOLar System', 
             'vp', '[\"organization_name\", \"partnership_title\", \"proposed_amount\", \"proposed_slots\", \"approve_link\", \"reject_link\"]'),
            
            ('president_approval', 'Partnership Request - Final Approval Required', 
             'Dear President,\\n\\nA partnership request has been approved by both the scholarship committee and vice president, and now requires your final approval.\\n\\nOrganization: {{organization_name}}\\nPartnership Title: {{partnership_title}}\\nProposed Amount: ₱{{proposed_amount}}\\nProposed Slots: {{proposed_slots}}\\n\\nPrevious Approvals:\\n- Committee: APPROVED\\n- Vice President: APPROVED\\n\\nPlease make your final decision:\\n\\nApprove: {{approve_link}}\\nReject: {{reject_link}}\\n\\nThis approval link expires in 7 days.\\n\\nBest regards,\\nISKOLar System', 
             'president', '[\"organization_name\", \"partnership_title\", \"proposed_amount\", \"proposed_slots\", \"approve_link\", \"reject_link\"]')
        ");
        echo "<p>✅ Default email templates created</p>";
    }
    
    // Create additional indexes for performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_partnership_active ON partnership_requests(partnership_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_current_stage_submitted ON partnership_requests(current_stage, submitted_at)");
    echo "<p>✅ Performance indexes created</p>";
    
    echo "<h2>🎉 Approval Workflow Setup Complete!</h2>";
    echo "<p>The sequential approval workflow system is now ready with:</p>";
    echo "<ul>";
    echo "<li>✅ Partnership request tracking</li>";
    echo "<li>✅ Sequential approval stages (Committee → VP → President)</li>";
    echo "<li>✅ Secure email-based approval tokens</li>";
    echo "<li>✅ Complete audit logging</li>";
    echo "<li>✅ Role-based email configuration</li>";
    echo "<li>✅ Customizable email templates</li>";
    echo "</ul>";
    
    echo "<h3>Default Configuration:</h3>";
    echo "<ul>";
    echo "<li><strong>Committee Email:</strong> committee@davaocentralcollege.edu.ph</li>";
    echo "<li><strong>VP Email:</strong> vp@davaocentralcollege.edu.ph</li>";
    echo "<li><strong>President Email:</strong> president@davaocentralcollege.edu.ph</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update email addresses in school_roles table for your institution</li>";
    echo "<li>Configure email server settings for sending approval emails</li>";
    echo "<li>Test the workflow by submitting a partnership request</li>";
    echo "<li>Access provider partnership request form at: <a href='app/views/provider/partnership_request.php'>partnership_request.php</a></li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>