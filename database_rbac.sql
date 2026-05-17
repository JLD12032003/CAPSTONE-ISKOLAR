-- ============================================
-- ISKOLar RBAC Database Schema
-- Role-Based Access Control Implementation
-- ============================================

USE ISKOLAR_101;

-- ============================================
-- 1. UPDATE USERS TABLE - Add Admin Role
-- ============================================
ALTER TABLE users 
MODIFY COLUMN user_type ENUM('student', 'provider', 'admin') NOT NULL DEFAULT 'student';

-- Add profile completion tracking
ALTER TABLE users 
ADD COLUMN profile_completed TINYINT(1) DEFAULT 0 AFTER is_verified,
ADD COLUMN profile_completion_step INT DEFAULT 0 AFTER profile_completed,
ADD COLUMN created_by INT DEFAULT NULL AFTER profile_completion_step,
ADD COLUMN school_id INT DEFAULT NULL AFTER created_by;

-- ============================================
-- 2. SCHOOLS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    school_code VARCHAR(50) UNIQUE NOT NULL,
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    region VARCHAR(100),
    contact_number VARCHAR(50),
    email VARCHAR(255),
    president_name VARCHAR(255),
    vp_name VARCHAR(255),
    finance_officer_name VARCHAR(255),
    scholarship_coordinator_name VARCHAR(255),
    scholarship_coordinator_email VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert Davao Central College as default
INSERT INTO schools (school_name, school_code, address, city, province, region, contact_number, email, president_name, scholarship_coordinator_name) 
VALUES (
    'Davao Central College',
    'DCC-2024',
    'Tigatto Road, Buhangin',
    'Davao City',
    'Davao del Sur',
    'Region XI - Davao Region',
    '(082) 234-5678',
    'admin@davaocentralcollege.edu.ph',
    'Dr. Maria Santos',
    'Prof. Juan Dela Cruz'
);

-- ============================================
-- 3. STUDENT PROFILES TABLE (Based on CHED Form)
-- ============================================
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    
    -- Personal Information (Phase 1)
    last_name VARCHAR(100),
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    suffix VARCHAR(20),
    birthdate DATE,
    place_of_birth VARCHAR(255),
    sex ENUM('Male', 'Female'),
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Others'),
    citizenship VARCHAR(100),
    mobile_number VARCHAR(50),
    landline VARCHAR(50),
    present_address TEXT,
    permanent_address TEXT,
    zip_code VARCHAR(20),
    
    -- Educational Background (Phase 2)
    school_id INT,
    school_name VARCHAR(255),
    school_address TEXT,
    school_sector ENUM('Public', 'Private'),
    course VARCHAR(255),
    year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'),
    type_of_disability VARCHAR(255),
    
    -- Family Background (Phase 3)
    father_name VARCHAR(255),
    father_address TEXT,
    father_contact VARCHAR(50),
    father_occupation VARCHAR(255),
    father_employer VARCHAR(255),
    father_employer_address TEXT,
    father_education VARCHAR(100),
    father_income DECIMAL(12,2),
    
    mother_name VARCHAR(255),
    mother_address TEXT,
    mother_contact VARCHAR(50),
    mother_occupation VARCHAR(255),
    mother_employer VARCHAR(255),
    mother_employer_address TEXT,
    mother_education VARCHAR(100),
    mother_income DECIMAL(12,2),
    
    legal_guardian VARCHAR(255),
    num_siblings INT DEFAULT 0,
    
    -- Financial Information (Phase 4)
    family_monthly_income DECIMAL(12,2),
    is_4ps_beneficiary ENUM('Yes', 'No'),
    
    -- Academic Information (Phase 5)
    gwa DECIMAL(4,2),
    awards_received TEXT,
    
    -- Additional Information
    profile_photo VARCHAR(255),
    
    -- Metadata
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 4. PROVIDER PROFILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS provider_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    
    organization_name VARCHAR(255) NOT NULL,
    organization_type ENUM('Individual', 'Foundation', 'Corporation', 'NGO', 'Government') NOT NULL,
    registration_number VARCHAR(100),
    tin VARCHAR(50),
    
    contact_person VARCHAR(255),
    position VARCHAR(100),
    office_address TEXT,
    contact_number VARCHAR(50),
    website VARCHAR(255),
    
    mission TEXT,
    vision TEXT,
    
    is_verified TINYINT(1) DEFAULT 0,
    verification_documents TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 5. SCHOLARSHIPS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    school_id INT,
    
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scholarship_type ENUM('Full', 'Partial', 'Book Allowance', 'Tuition Only', 'Living Allowance') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    slots INT NOT NULL,
    available_slots INT NOT NULL,
    
    -- Eligibility Criteria
    eligible_courses TEXT,
    min_gwa DECIMAL(4,2),
    max_family_income DECIMAL(12,2),
    year_levels TEXT,
    other_requirements TEXT,
    
    -- Application Period
    application_start DATE,
    application_end DATE,
    
    -- Status
    status ENUM('Draft', 'Pending Approval', 'Active', 'Closed', 'Cancelled') DEFAULT 'Draft',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    
    -- Partnership Details
    partnership_agreement TEXT,
    meeting_date DATE,
    meeting_attendees TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 6. SCHOLARSHIP APPLICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS scholarship_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scholarship_id INT NOT NULL,
    student_id INT NOT NULL,
    
    -- Application Status
    status ENUM('Submitted', 'Under Review', 'Shortlisted', 'Interview', 'Approved', 'Rejected', 'Withdrawn') DEFAULT 'Submitted',
    
    -- Essay/Statement
    personal_statement TEXT,
    why_deserve_scholarship TEXT,
    
    -- Documents
    documents JSON,
    
    -- Review Process
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    
    -- Admin Tracking
    admin_notes TEXT,
    is_student_enrolled TINYINT(1) DEFAULT 1,
    enrollment_verified_at TIMESTAMP NULL,
    
    -- Provider Decision
    provider_decision ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    provider_notes TEXT,
    decided_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_application (scholarship_id, student_id)
) ENGINE=InnoDB;

-- ============================================
-- 7. SCHOLARSHIP AWARDS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS scholarship_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    scholarship_id INT NOT NULL,
    student_id INT NOT NULL,
    
    amount_awarded DECIMAL(12,2) NOT NULL,
    award_date DATE NOT NULL,
    
    -- Disbursement
    disbursement_status ENUM('Pending', 'Partial', 'Full') DEFAULT 'Pending',
    disbursement_schedule TEXT,
    
    -- Monitoring
    is_active TINYINT(1) DEFAULT 1,
    termination_reason TEXT,
    terminated_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES scholarship_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 8. ENROLLMENT TRACKING TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS enrollment_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester', '2nd Semester', 'Summer'),
    year_level VARCHAR(20),
    
    is_enrolled TINYINT(1) DEFAULT 1,
    enrollment_date DATE,
    
    verified_by INT,
    verified_at TIMESTAMP NULL,
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 9. COMMUNICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    
    subject VARCHAR(255),
    message TEXT NOT NULL,
    
    related_scholarship_id INT,
    related_application_id INT,
    
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_scholarship_id) REFERENCES scholarships(id) ON DELETE SET NULL,
    FOREIGN KEY (related_application_id) REFERENCES scholarship_applications(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 10. REPORTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    report_type ENUM('Monthly', 'Quarterly', 'Annual', 'Custom') NOT NULL,
    generated_by INT NOT NULL,
    school_id INT,
    
    title VARCHAR(255) NOT NULL,
    report_data JSON,
    file_path VARCHAR(255),
    
    period_start DATE,
    period_end DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 11. ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_action (user_id, action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================
-- 12. SYSTEM SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('admin_credentials_rotation_months', '6', 'Number of months before admin credentials expire'),
('max_applications_per_student', '5', 'Maximum scholarship applications per student'),
('application_review_days', '30', 'Days to review applications'),
('color_primary', '#0055FF', 'Primary brand color'),
('color_secondary', '#FDC500', 'Secondary brand color'),
('color_dark', '#012A4A', 'Dark theme color');

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_user_type ON users(user_type);
CREATE INDEX idx_user_school ON users(school_id);
CREATE INDEX idx_scholarship_status ON scholarships(status);
CREATE INDEX idx_scholarship_provider ON scholarships(provider_id);
CREATE INDEX idx_application_status ON scholarship_applications(status);
CREATE INDEX idx_application_student ON scholarship_applications(student_id);
CREATE INDEX idx_award_student ON scholarship_awards(student_id);
CREATE INDEX idx_enrollment_student ON enrollment_tracking(student_id);

-- ============================================
-- CREATE DEFAULT ADMIN ACCOUNT
-- ============================================
-- Password: Admin@DCC2024
INSERT INTO users (fullname, email, password, user_type, is_verified, school_id) 
VALUES (
    'DCC Admin',
    'admin@davaocentralcollege.edu.ph',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1,
    1
);

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- Active Scholarships View
CREATE OR REPLACE VIEW v_active_scholarships AS
SELECT 
    s.*,
    u.fullname as provider_name,
    pp.organization_name,
    sc.school_name,
    (s.slots - s.available_slots) as filled_slots
FROM scholarships s
JOIN users u ON s.provider_id = u.id
LEFT JOIN provider_profiles pp ON u.id = pp.user_id
LEFT JOIN schools sc ON s.school_id = sc.id
WHERE s.status = 'Active';

-- Student Applications Summary View
CREATE OR REPLACE VIEW v_student_applications AS
SELECT 
    sa.*,
    u.fullname as student_name,
    u.email as student_email,
    sp.course,
    sp.year_level,
    sp.gwa,
    s.title as scholarship_title,
    s.amount as scholarship_amount
FROM scholarship_applications sa
JOIN users u ON sa.student_id = u.id
LEFT JOIN student_profiles sp ON u.id = sp.user_id
JOIN scholarships s ON sa.scholarship_id = s.id;

-- Provider Dashboard Stats View
CREATE OR REPLACE VIEW v_provider_stats AS
SELECT 
    u.id as provider_id,
    u.fullname as provider_name,
    COUNT(DISTINCT s.id) as total_scholarships,
    SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_scholarships,
    COUNT(DISTINCT sa.id) as total_applications,
    COUNT(DISTINCT saw.id) as total_awards,
    COALESCE(SUM(saw.amount_awarded), 0) as total_amount_awarded
FROM users u
LEFT JOIN scholarships s ON u.id = s.provider_id
LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
LEFT JOIN scholarship_awards saw ON sa.id = saw.application_id
WHERE u.user_type = 'provider'
GROUP BY u.id;

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER //

-- Procedure to check student enrollment status
CREATE PROCEDURE sp_check_student_enrollment(IN p_student_id INT, IN p_academic_year VARCHAR(20))
BEGIN
    SELECT 
        et.is_enrolled,
        et.enrollment_date,
        et.year_level,
        s.school_name
    FROM enrollment_tracking et
    JOIN schools s ON et.school_id = s.id
    WHERE et.student_id = p_student_id 
    AND et.academic_year = p_academic_year
    ORDER BY et.created_at DESC
    LIMIT 1;
END //

-- Procedure to get scholarship statistics
CREATE PROCEDURE sp_scholarship_statistics(IN p_scholarship_id INT)
BEGIN
    SELECT 
        s.title,
        s.slots,
        s.available_slots,
        COUNT(sa.id) as total_applications,
        SUM(CASE WHEN sa.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN sa.status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN sa.status IN ('Submitted', 'Under Review') THEN 1 ELSE 0 END) as pending_count
    FROM scholarships s
    LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
    WHERE s.id = p_scholarship_id
    GROUP BY s.id;
END //

DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

DELIMITER //

-- Trigger to update available slots when application is approved
CREATE TRIGGER tr_update_slots_after_approval
AFTER UPDATE ON scholarship_applications
FOR EACH ROW
BEGIN
    IF NEW.provider_decision = 'Approved' AND OLD.provider_decision != 'Approved' THEN
        UPDATE scholarships 
        SET available_slots = available_slots - 1 
        WHERE id = NEW.scholarship_id AND available_slots > 0;
    END IF;
END //

-- Trigger to log user activities
CREATE TRIGGER tr_log_scholarship_creation
AFTER INSERT ON scholarships
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (NEW.provider_id, 'CREATE', 'scholarship', NEW.id, CONCAT('Created scholarship: ', NEW.title));
END //

DELIMITER ;

-- ============================================
-- GRANT PERMISSIONS (Optional - for production)
-- ============================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ISKOLAR_101.* TO 'iskolar_app'@'localhost';
-- FLUSH PRIVILEGES;
