-- ISKOLAR Sequential Approval Workflow Database Schema
-- Designed for strict Committee → VP → President approval flow

-- =====================================================
-- CORE TABLES FOR APPROVAL WORKFLOW
-- =====================================================

-- Partnership Requests (Main tracking table)
CREATE TABLE partnership_requests (
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
) ENGINE=InnoDB;

-- Approval Stages (Stage-specific tracking)
CREATE TABLE approval_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partnership_request_id INT NOT NULL,
    
    -- Stage Information
    stage_name ENUM('COMMITTEE_REVIEW', 'VP_REVIEW', 'PRESIDENT_REVIEW') NOT NULL,
    stage_order INT NOT NULL, -- 1=Committee, 2=VP, 3=President
    
    -- Email Details
    recipient_email VARCHAR(255) NOT NULL,
    recipient_role VARCHAR(50) NOT NULL,
    email_sent_at TIMESTAMP NULL,
    email_subject VARCHAR(255),
    
    -- Token Security
    approval_token VARCHAR(255) UNIQUE NOT NULL,
    token_expires_at TIMESTAMP NOT NULL,
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
) ENGINE=InnoDB;

-- Email Tokens (Secure token management)
CREATE TABLE email_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partnership_request_id INT NOT NULL,
    approval_stage_id INT NOT NULL,
    
    -- Token Details
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    token_type ENUM('APPROVAL', 'REJECTION') NOT NULL,
    
    -- Security
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    is_used TINYINT(1) DEFAULT 0,
    
    -- Usage Tracking
    access_attempts INT DEFAULT 0,
    last_access_ip VARCHAR(45),
    last_access_at TIMESTAMP NULL,
    
    FOREIGN KEY (partnership_request_id) REFERENCES partnership_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approval_stage_id) REFERENCES approval_stages(id) ON DELETE CASCADE,
    
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Approval Logs (Complete audit trail)
CREATE TABLE approval_logs (
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
) ENGINE=InnoDB;

-- School Roles (Role-email mapping per school)
CREATE TABLE school_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    
    -- Role Configuration
    role_name ENUM('committee', 'vp', 'president') NOT NULL,
    role_title VARCHAR(255) NOT NULL, -- e.g., "Scholarship Committee", "Vice President"
    
    -- Contact Information
    email_address VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone_number VARCHAR(50),
    
    -- Role Settings
    is_active TINYINT(1) DEFAULT 1,
    approval_order INT NOT NULL, -- 1=Committee, 2=VP, 3=President
    
    -- Email Configuration
    email_template_id VARCHAR(50),
    auto_reminder_days INT DEFAULT 3,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_role_per_school (school_id, role_name),
    INDEX idx_approval_order (approval_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Email Templates (Customizable email content)
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    
    -- Template Content
    subject_template VARCHAR(255) NOT NULL,
    body_template TEXT NOT NULL,
    
    -- Template Variables (JSON array of available variables)
    available_variables JSON,
    
    -- Settings
    is_active TINYINT(1) DEFAULT 1,
    template_type ENUM('committee', 'vp', 'president', 'notification') NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_template_type (template_type)
) ENGINE=InnoDB;

-- =====================================================
-- VIEWS FOR EASY DATA ACCESS
-- =====================================================

-- Partnership Request Status View
CREATE VIEW partnership_status_view AS
SELECT 
    pr.id,
    pr.organization_name,
    pr.partnership_title,
    pr.current_stage,
    pr.submitted_at,
    pr.partnership_active,
    s.school_name,
    u.fullname as provider_name,
    u.email as provider_email,
    
    -- Stage Progress
    CASE 
        WHEN pr.current_stage = 'PENDING' THEN 0
        WHEN pr.current_stage = 'COMMITTEE_REVIEW' THEN 25
        WHEN pr.current_stage = 'VP_REVIEW' THEN 50
        WHEN pr.current_stage = 'PRESIDENT_REVIEW' THEN 75
        WHEN pr.current_stage = 'APPROVED' THEN 100
        ELSE 0
    END as progress_percentage,
    
    -- Time Tracking
    DATEDIFF(NOW(), pr.submitted_at) as days_since_submission,
    
    -- Current Stage Info
    (SELECT email_sent_at FROM approval_stages 
     WHERE partnership_request_id = pr.id 
     AND stage_name = pr.current_stage 
     LIMIT 1) as current_stage_email_sent,
     
    (SELECT token_expires_at FROM approval_stages 
     WHERE partnership_request_id = pr.id 
     AND stage_name = pr.current_stage 
     LIMIT 1) as current_stage_expires
     
FROM partnership_requests pr
JOIN schools s ON pr.school_id = s.id
JOIN users u ON pr.provider_id = u.id;

-- =====================================================
-- STORED PROCEDURES FOR WORKFLOW MANAGEMENT
-- =====================================================

DELIMITER //

-- Initialize Partnership Request Workflow
CREATE PROCEDURE InitializePartnershipWorkflow(
    IN p_partnership_request_id INT
)
BEGIN
    DECLARE v_school_id INT;
    DECLARE v_committee_email VARCHAR(255);
    DECLARE v_approval_token VARCHAR(255);
    DECLARE v_token_expires TIMESTAMP;
    
    -- Get school ID
    SELECT school_id INTO v_school_id 
    FROM partnership_requests 
    WHERE id = p_partnership_request_id;
    
    -- Get committee email
    SELECT email_address INTO v_committee_email
    FROM school_roles 
    WHERE school_id = v_school_id 
    AND role_name = 'committee' 
    AND is_active = 1;
    
    -- Generate secure token
    SET v_approval_token = SHA2(CONCAT(p_partnership_request_id, 'committee', NOW(), RAND()), 256);
    SET v_token_expires = DATE_ADD(NOW(), INTERVAL 7 DAY);
    
    -- Update request status
    UPDATE partnership_requests 
    SET current_stage = 'COMMITTEE_REVIEW',
        committee_notified_at = NOW()
    WHERE id = p_partnership_request_id;
    
    -- Create approval stage
    INSERT INTO approval_stages (
        partnership_request_id, stage_name, stage_order,
        recipient_email, recipient_role, approval_token, token_expires_at
    ) VALUES (
        p_partnership_request_id, 'COMMITTEE_REVIEW', 1,
        v_committee_email, 'committee', v_approval_token, v_token_expires
    );
    
    -- Log event
    INSERT INTO approval_logs (
        partnership_request_id, event_type, stage_name, 
        recipient_role, event_description
    ) VALUES (
        p_partnership_request_id, 'REQUEST_SUBMITTED', 'COMMITTEE_REVIEW',
        'committee', 'Partnership request submitted and committee notified'
    );
    
END //

-- Process Stage Approval
CREATE PROCEDURE ProcessStageApproval(
    IN p_token VARCHAR(255),
    IN p_decision ENUM('APPROVED', 'REJECTED'),
    IN p_notes TEXT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    DECLARE v_partnership_id INT;
    DECLARE v_stage_name VARCHAR(50);
    DECLARE v_stage_order INT;
    DECLARE v_next_stage VARCHAR(50);
    DECLARE v_next_email VARCHAR(255);
    DECLARE v_new_token VARCHAR(255);
    DECLARE v_token_expires TIMESTAMP;
    
    -- Validate and get stage info
    SELECT 
        partnership_request_id, stage_name, stage_order
    INTO 
        v_partnership_id, v_stage_name, v_stage_order
    FROM approval_stages 
    WHERE approval_token = p_token 
    AND token_expires_at > NOW() 
    AND token_used = 0;
    
    IF v_partnership_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or expired token';
    END IF;
    
    -- Mark token as used
    UPDATE approval_stages 
    SET decision = p_decision,
        decided_at = NOW(),
        decision_notes = p_notes,
        decided_by_ip = p_ip_address,
        token_used = 1,
        token_used_at = NOW()
    WHERE approval_token = p_token;
    
    IF p_decision = 'REJECTED' THEN
        -- Handle rejection
        UPDATE partnership_requests 
        SET current_stage = 'REJECTED',
            rejected_by_stage = v_stage_name,
            rejection_reason = p_notes,
            final_decision_at = NOW()
        WHERE id = v_partnership_id;
        
        -- Log rejection
        INSERT INTO approval_logs (
            partnership_request_id, event_type, stage_name,
            event_description, ip_address
        ) VALUES (
            v_partnership_id, 'STAGE_REJECTED', v_stage_name,
            CONCAT('Request rejected at ', v_stage_name, ' stage'), p_ip_address
        );
        
    ELSE
        -- Handle approval - determine next stage
        IF v_stage_order = 1 THEN
            SET v_next_stage = 'VP_REVIEW';
        ELSEIF v_stage_order = 2 THEN
            SET v_next_stage = 'PRESIDENT_REVIEW';
        ELSEIF v_stage_order = 3 THEN
            SET v_next_stage = 'APPROVED';
        END IF;
        
        IF v_next_stage = 'APPROVED' THEN
            -- Final approval
            UPDATE partnership_requests 
            SET current_stage = 'APPROVED',
                partnership_active = 1,
                partnership_start_date = CURDATE(),
                final_decision_at = NOW()
            WHERE id = v_partnership_id;
            
            -- Log completion
            INSERT INTO approval_logs (
                partnership_request_id, event_type, 
                event_description, ip_address
            ) VALUES (
                v_partnership_id, 'WORKFLOW_COMPLETED',
                'Partnership request fully approved', p_ip_address
            );
            
        ELSE
            -- Move to next stage
            UPDATE partnership_requests 
            SET current_stage = v_next_stage
            WHERE id = v_partnership_id;
            
            -- Create next approval stage
            -- (Implementation continues with next stage setup)
        END IF;
    END IF;
    
END //

DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for query optimization
CREATE INDEX idx_partnership_active ON partnership_requests(partnership_active);
CREATE INDEX idx_current_stage_submitted ON partnership_requests(current_stage, submitted_at);
CREATE INDEX idx_email_sent_expires ON approval_stages(email_sent_at, token_expires_at);
CREATE INDEX idx_logs_event_time ON approval_logs(event_type, logged_at);

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Insert default school roles for Davao Central College
INSERT INTO school_roles (school_id, role_name, role_title, email_address, approval_order) VALUES
(1, 'committee', 'Scholarship Committee', 'committee@davaocentralcollege.edu.ph', 1),
(1, 'vp', 'Vice President for Academic Affairs', 'vp@davaocentralcollege.edu.ph', 2),
(1, 'president', 'School President', 'president@davaocentralcollege.edu.ph', 3);

-- Insert email templates
INSERT INTO email_templates (template_name, subject_template, body_template, template_type, available_variables) VALUES
('committee_approval', 'Partnership Request - {{organization_name}}', 
'Dear Scholarship Committee,\n\nA new partnership request requires your review...\n\nApprove: {{approve_link}}\nReject: {{reject_link}}', 
'committee', '["organization_name", "approve_link", "reject_link"]'),

('vp_approval', 'Partnership Request - VP Review Required', 
'Dear Vice President,\n\nThe scholarship committee has approved a partnership request...\n\nApprove: {{approve_link}}\nReject: {{reject_link}}', 
'vp', '["organization_name", "approve_link", "reject_link"]'),

('president_approval', 'Partnership Request - Final Approval Required', 
'Dear President,\n\nA partnership request requires your final approval...\n\nApprove: {{approve_link}}\nReject: {{reject_link}}', 
'president', '["organization_name", "approve_link", "reject_link"]');