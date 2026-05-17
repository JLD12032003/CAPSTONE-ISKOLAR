-- Scholarship Management System with Multi-Level Approval Workflow
-- Database Schema for Email-Based Approval System

-- Enhanced scholarships table with workflow support
ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS workflow_status ENUM(
    'DRAFT',
    'PENDING_SCHOOL_ADMIN_REVIEW',
    'PENDING_COMMITTEE_REVIEW', 
    'PENDING_VP_REVIEW',
    'PENDING_PRESIDENT_REVIEW',
    'APPROVED_FOR_PUBLICATION',
    'REJECTED_BY_SCHOOL_ADMIN',
    'REJECTED_BY_COMMITTEE',
    'REJECTED_BY_VP',
    'REJECTED_BY_PRESIDENT'
) DEFAULT 'DRAFT';

ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS loa_document VARCHAR(255) NULL;
ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP NULL;
ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS published_at TIMESTAMP NULL;
ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL;
ALTER TABLE scholarships ADD COLUMN IF NOT EXISTS rejection_stage VARCHAR(50) NULL;

-- Scholarship approval stages table
CREATE TABLE IF NOT EXISTS scholarship_approval_stages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scholarship_id INT NOT NULL,
    stage_name ENUM('SCHOOL_ADMIN', 'COMMITTEE', 'VP', 'PRESIDENT') NOT NULL,
    stage_order INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_role VARCHAR(100) NOT NULL,
    approval_token VARCHAR(255) UNIQUE NOT NULL,
    token_expires_at TIMESTAMP NOT NULL,
    token_used TINYINT(1) DEFAULT 0,
    token_used_at TIMESTAMP NULL,
    decision ENUM('APPROVED', 'REJECTED') NULL,
    decided_at TIMESTAMP NULL,
    decided_by_ip VARCHAR(45) NULL,
    decision_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    INDEX idx_scholarship_stage (scholarship_id, stage_name),
    INDEX idx_token (approval_token),
    INDEX idx_expires (token_expires_at)
);

-- Committee member votes table (for individual vote tracking)
CREATE TABLE IF NOT EXISTS committee_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scholarship_id INT NOT NULL,
    approval_stage_id INT NOT NULL,
    member_email VARCHAR(255) NOT NULL,
    member_name VARCHAR(255) NULL,
    vote ENUM('APPROVED', 'REJECTED') NOT NULL,
    vote_notes TEXT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vote_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    FOREIGN KEY (approval_stage_id) REFERENCES scholarship_approval_stages(id) ON DELETE CASCADE,
    INDEX idx_scholarship_member (scholarship_id, member_email),
    INDEX idx_vote_token (vote_token)
);

-- Scholarship workflow audit log
CREATE TABLE IF NOT EXISTS scholarship_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scholarship_id INT NOT NULL,
    action_type ENUM(
        'SCHOLARSHIP_CREATED',
        'SUBMITTED_FOR_REVIEW',
        'STAGE_APPROVED',
        'STAGE_REJECTED',
        'FORWARDED_TO_NEXT_STAGE',
        'PUBLISHED',
        'WORKFLOW_COMPLETED',
        'EMAIL_SENT',
        'TOKEN_GENERATED',
        'VOTE_RECORDED'
    ) NOT NULL,
    stage_name VARCHAR(50) NULL,
    actor_role VARCHAR(100) NULL,
    actor_email VARCHAR(255) NULL,
    actor_ip VARCHAR(45) NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    action_details TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    INDEX idx_scholarship_audit (scholarship_id, created_at),
    INDEX idx_action_type (action_type),
    INDEX idx_stage_name (stage_name)
);

-- Letter of Agreement (LOA) templates
CREATE TABLE IF NOT EXISTS loa_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    template_type ENUM('STANDARD', 'ACADEMIC', 'MERIT', 'NEED_BASED', 'CORPORATE') DEFAULT 'STANDARD',
    template_content TEXT NOT NULL,
    variables JSON NULL, -- Template variables like {provider_name}, {scholarship_amount}
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- School approval configuration
CREATE TABLE IF NOT EXISTS school_approval_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id INT NOT NULL,
    admin_email VARCHAR(255) NOT NULL,
    committee_emails JSON NOT NULL, -- Array of committee member emails
    vp_email VARCHAR(255) NOT NULL,
    president_email VARCHAR(255) NOT NULL,
    committee_quorum INT DEFAULT 3, -- Minimum votes needed from committee
    approval_timeout_days INT DEFAULT 7,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_school_config (school_id)
);

-- Email notification log
CREATE TABLE IF NOT EXISTS scholarship_email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scholarship_id INT NOT NULL,
    stage_name VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    email_type ENUM('APPROVAL_REQUEST', 'APPROVAL_REMINDER', 'DECISION_NOTIFICATION', 'PUBLICATION_NOTICE') NOT NULL,
    email_subject VARCHAR(500) NOT NULL,
    email_body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_status ENUM('SENT', 'DELIVERED', 'FAILED', 'BOUNCED') DEFAULT 'SENT',
    error_message TEXT NULL,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    INDEX idx_scholarship_email (scholarship_id, sent_at),
    INDEX idx_recipient (recipient_email),
    INDEX idx_delivery_status (delivery_status)
);

-- Insert default LOA template
INSERT INTO loa_templates (template_name, template_type, template_content, variables) VALUES 
('Standard Scholarship LOA', 'STANDARD', 
'LETTER OF AGREEMENT - SCHOLARSHIP PARTNERSHIP

Provider: {provider_name}
Organization: {organization_name}
School: {school_name}

SCHOLARSHIP DETAILS:
- Title: {scholarship_title}
- Amount: ₱{scholarship_amount}
- Number of Recipients: {number_of_slots}
- Duration: {duration_years} year(s)
- Type: {scholarship_type}

PROVIDER RESPONSIBILITIES:
1. Provide scholarship funds as specified
2. Participate in recipient selection process
3. Maintain communication with school administration
4. Provide certificates/recognition for recipients
5. Submit annual scholarship reports

SCHOOL OBLIGATIONS:
1. Facilitate fair and transparent selection process
2. Verify student eligibility and academic standing
3. Monitor recipient academic progress
4. Provide regular updates to scholarship provider
5. Ensure proper use of scholarship funds

ELIGIBILITY RULES:
- Minimum GPA requirement: {min_gpa}
- Financial need assessment required
- Full-time enrollment status
- Good moral standing
- Compliance with school policies

IMPLEMENTATION DETAILS:
- Application period: {application_period}
- Selection committee: School scholarship committee
- Award notification: Within 30 days of application deadline
- Fund disbursement: Per semester/trimester
- Renewal criteria: Maintain minimum GPA and enrollment

This agreement is subject to approval by:
1. School Administrator
2. Scholarship Committee  
3. Vice President for Academic Affairs
4. School President

Effective upon final approval and signature of all parties.',
'{"provider_name": "text", "organization_name": "text", "school_name": "text", "scholarship_title": "text", "scholarship_amount": "number", "number_of_slots": "number", "duration_years": "number", "scholarship_type": "text", "min_gpa": "number", "application_period": "text"}');

-- Insert default school approval configuration for Davao Central College
INSERT INTO school_approval_config (
    school_id, 
    admin_email, 
    committee_emails, 
    vp_email, 
    president_email,
    committee_quorum
) VALUES (
    1, -- Davao Central College ID
    'admin@davaocentralcollege.edu.ph',
    '["committee1@davaocentralcollege.edu.ph", "committee2@davaocentralcollege.edu.ph", "committee3@davaocentralcollege.edu.ph"]',
    'vp@davaocentralcollege.edu.ph',
    'president@davaocentralcollege.edu.ph',
    2 -- Minimum 2 out of 3 committee votes needed
) ON DUPLICATE KEY UPDATE
    admin_email = VALUES(admin_email),
    committee_emails = VALUES(committee_emails),
    vp_email = VALUES(vp_email),
    president_email = VALUES(president_email);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_scholarships_workflow ON scholarships(workflow_status, created_at);
CREATE INDEX IF NOT EXISTS idx_scholarships_provider ON scholarships(provider_id, workflow_status);
CREATE INDEX IF NOT EXISTS idx_scholarships_school ON scholarships(school_id, workflow_status);

-- Create view for scholarship workflow summary
CREATE OR REPLACE VIEW scholarship_workflow_summary AS
SELECT 
    s.id,
    s.title,
    s.provider_id,
    s.school_id,
    s.workflow_status,
    s.amount,
    s.slots,
    s.created_at,
    s.submitted_at,
    s.published_at,
    u.fullname as provider_name,
    pp.organization_name,
    sch.school_name,
    COUNT(sas.id) as total_stages,
    COUNT(CASE WHEN sas.decision = 'APPROVED' THEN 1 END) as approved_stages,
    COUNT(CASE WHEN sas.decision = 'REJECTED' THEN 1 END) as rejected_stages,
    COUNT(CASE WHEN sas.decision IS NULL THEN 1 END) as pending_stages
FROM scholarships s
LEFT JOIN users u ON s.provider_id = u.id
LEFT JOIN provider_profiles pp ON u.id = pp.user_id
LEFT JOIN schools sch ON s.school_id = sch.id
LEFT JOIN scholarship_approval_stages sas ON s.id = sas.scholarship_id
WHERE s.workflow_status != 'DRAFT'
GROUP BY s.id;