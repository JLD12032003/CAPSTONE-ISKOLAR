<?php
/**
 * Fix Application Issues
 * 1. Add sample scholarships to fix foreign key constraint
 * 2. Add application editing functionality
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    echo "🔧 Fixing Application Issues...\n\n";
    
    // 1. Check if we have any scholarships
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships");
    $scholarshipCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Current scholarships in database: {$scholarshipCount}\n";
    
    if ($scholarshipCount == 0) {
        echo "🚨 No scholarships found! Adding sample scholarships...\n\n";
        
        // Get provider user (assuming user ID 2 is a provider)
        $stmt = $conn->query("SELECT id FROM users WHERE user_type = 'provider' LIMIT 1");
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$provider) {
            // Create a sample provider if none exists
            $stmt = $conn->prepare("
                INSERT INTO users (fullname, email, password, user_type, email_verified, profile_completed) 
                VALUES (?, ?, ?, 'provider', 1, 1)
            ");
            $stmt->execute([
                'Sample Foundation',
                'provider@sample.com',
                password_hash('password123', PASSWORD_DEFAULT)
            ]);
            $providerId = $conn->lastInsertId();
            echo "✅ Created sample provider (ID: {$providerId})\n";
        } else {
            $providerId = $provider['id'];
            echo "✅ Using existing provider (ID: {$providerId})\n";
        }
        
        // Add sample scholarships
        $scholarships = [
            [
                'title' => 'Academic Excellence Scholarship',
                'description' => 'Merit-based scholarship for students with outstanding academic performance. This scholarship aims to support dedicated students who have shown exceptional academic achievement and leadership potential.',
                'scholarship_type' => 'Full',
                'amount' => 50000.00,
                'slots' => 10,
                'available_slots' => 10,
                'eligible_courses' => 'All Courses',
                'min_gwa' => 1.75,
                'max_family_income' => 500000.00,
                'year_levels' => '2nd Year, 3rd Year, 4th Year',
                'other_requirements' => 'Good Moral Character Certificate, Academic Transcript',
                'application_start' => date('Y-m-d', strtotime('-7 days')),
                'application_end' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'Active',
                'workflow_status' => 'APPROVED_FOR_PUBLICATION'
            ],
            [
                'title' => 'Financial Need Scholarship',
                'description' => 'Need-based scholarship for students from low-income families. This program provides financial assistance to deserving students who demonstrate financial need while maintaining good academic standing.',
                'scholarship_type' => 'Partial',
                'amount' => 25000.00,
                'slots' => 20,
                'available_slots' => 20,
                'eligible_courses' => 'All Courses',
                'min_gwa' => 2.50,
                'max_family_income' => 200000.00,
                'year_levels' => '1st Year, 2nd Year, 3rd Year, 4th Year',
                'other_requirements' => 'Certificate of Indigency, Good Moral Character Certificate, Parent/Guardian Income Certificate',
                'application_start' => date('Y-m-d', strtotime('-5 days')),
                'application_end' => date('Y-m-d', strtotime('+45 days')),
                'status' => 'Active',
                'workflow_status' => 'APPROVED_FOR_PUBLICATION'
            ],
            [
                'title' => 'STEM Excellence Grant',
                'description' => 'Special scholarship for Science, Technology, Engineering, and Mathematics students. Designed to encourage and support students pursuing STEM fields with additional benefits for research projects.',
                'scholarship_type' => 'Full',
                'amount' => 75000.00,
                'slots' => 5,
                'available_slots' => 5,
                'eligible_courses' => 'Computer Science, Engineering, Mathematics, Physics, Chemistry, Biology',
                'min_gwa' => 1.50,
                'max_family_income' => 800000.00,
                'year_levels' => '2nd Year, 3rd Year, 4th Year',
                'other_requirements' => 'Good Moral Character Certificate, Academic Transcript, Letter of Recommendation, Essay/Personal Statement',
                'application_start' => date('Y-m-d', strtotime('-3 days')),
                'application_end' => date('Y-m-d', strtotime('+60 days')),
                'status' => 'Active',
                'workflow_status' => 'APPROVED_FOR_PUBLICATION'
            ]
        ];
        
        foreach ($scholarships as $scholarship) {
            $stmt = $conn->prepare("
                INSERT INTO scholarships (
                    provider_id, title, description, scholarship_type, amount, slots, available_slots,
                    eligible_courses, min_gwa, max_family_income, year_levels, other_requirements,
                    application_start, application_end, status, workflow_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $providerId,
                $scholarship['title'],
                $scholarship['description'],
                $scholarship['scholarship_type'],
                $scholarship['amount'],
                $scholarship['slots'],
                $scholarship['available_slots'],
                $scholarship['eligible_courses'],
                $scholarship['min_gwa'],
                $scholarship['max_family_income'],
                $scholarship['year_levels'],
                $scholarship['other_requirements'],
                $scholarship['application_start'],
                $scholarship['application_end'],
                $scholarship['status'],
                $scholarship['workflow_status']
            ]);
            
            echo "✅ Added scholarship: {$scholarship['title']}\n";
        }
        
        echo "\n📊 Scholarships added successfully!\n\n";
    } else {
        echo "✅ Scholarships already exist in database\n\n";
    }
    
    // 2. Check current scholarship count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE status = 'Active'");
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "📈 Active scholarships available: {$activeCount}\n\n";
    
    // 3. Add edit_documents column to scholarship_applications if it doesn't exist
    echo "🔧 Checking application table structure...\n";
    
    $stmt = $conn->query("SHOW COLUMNS FROM scholarship_applications LIKE 'can_edit_documents'");
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        echo "➕ Adding can_edit_documents column...\n";
        $conn->exec("
            ALTER TABLE scholarship_applications 
            ADD COLUMN can_edit_documents TINYINT(1) DEFAULT 1 COMMENT 'Allow students to edit documents after submission'
        ");
        echo "✅ Added can_edit_documents column\n";
    } else {
        echo "✅ can_edit_documents column already exists\n";
    }
    
    // 4. Add last_document_update column
    $stmt = $conn->query("SHOW COLUMNS FROM scholarship_applications LIKE 'last_document_update'");
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        echo "➕ Adding last_document_update column...\n";
        $conn->exec("
            ALTER TABLE scholarship_applications 
            ADD COLUMN last_document_update TIMESTAMP NULL COMMENT 'Last time documents were updated'
        ");
        echo "✅ Added last_document_update column\n";
    } else {
        echo "✅ last_document_update column already exists\n";
    }
    
    // 5. Update existing applications to allow document editing
    $stmt = $conn->exec("
        UPDATE scholarship_applications 
        SET can_edit_documents = 1 
        WHERE status IN ('Submitted', 'Under Review') 
        AND can_edit_documents IS NULL
    ");
    echo "✅ Updated existing applications to allow document editing\n";
    
    echo "\n🎉 All fixes applied successfully!\n\n";
    
    echo "📋 Summary of Changes:\n";
    echo "=====================\n";
    echo "✅ Fixed foreign key constraint issue by adding sample scholarships\n";
    echo "✅ Added can_edit_documents column for application editing control\n";
    echo "✅ Added last_document_update column for tracking document changes\n";
    echo "✅ Enabled document editing for existing applications\n\n";
    
    echo "🔍 Next Steps:\n";
    echo "=============\n";
    echo "1. Students can now apply for scholarships without foreign key errors\n";
    echo "2. Document editing functionality needs to be implemented in the UI\n";
    echo "3. Test the application process to ensure it works correctly\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>