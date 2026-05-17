<?php
require_once __DIR__ . "/../../config/database.php";

class Scholarship {
    private $conn;

    public function __construct() {
        $this->conn = (new Database())->connect();
    }

    public function getActiveScholarships($limit = null) {
        $sql = "SELECT s.*, u.fullname as provider_name, pp.organization_name, sc.school_name
                FROM scholarships s
                JOIN users u ON s.provider_id = u.id
                LEFT JOIN provider_profiles pp ON u.id = pp.user_id
                LEFT JOIN schools sc ON s.school_id = sc.id
                WHERE s.status = 'Active' 
                AND (s.workflow_status = 'APPROVED_FOR_PUBLICATION' OR s.workflow_status IS NULL)
                AND s.application_start <= CURDATE() 
                AND s.application_end >= CURDATE()
                ORDER BY s.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScholarshipById($id) {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.fullname as provider_name, pp.organization_name, sc.school_name
            FROM scholarships s
            JOIN users u ON s.provider_id = u.id
            LEFT JOIN provider_profiles pp ON u.id = pp.user_id
            LEFT JOIN schools sc ON s.school_id = sc.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        return $this->getScholarshipById($id);
    }

    public function getProviderScholarships($provider_id) {
        $stmt = $this->conn->prepare("
            SELECT s.*, sc.school_name,
                   (s.slots - s.available_slots) as filled_slots,
                   COUNT(DISTINCT sa.id) as total_applications,
                   s.workflow_status
            FROM scholarships s
            LEFT JOIN schools sc ON s.school_id = sc.id
            LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
            WHERE s.provider_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$provider_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createScholarship($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO scholarships (
                provider_id, school_id, title, description, scholarship_type,
                amount, slots, available_slots, eligible_courses, min_gwa,
                max_family_income, year_levels, other_requirements, partnership_letter,
                application_start, application_end, status, workflow_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['provider_id'],
            $data['school_id'],
            $data['title'],
            $data['description'],
            $data['scholarship_type'],
            $data['amount'],
            $data['slots'],
            $data['slots'], // available_slots initially equals slots
            $data['eligible_courses'],
            $data['min_gwa'],
            $data['max_family_income'],
            $data['year_levels'],
            $data['other_requirements'],
            $data['partnership_letter'] ?? '',
            $data['application_start'],
            $data['application_end'],
            $data['status'],
            $data['workflow_status'] ?? 'DRAFT'
        ]);
        
        if ($result) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateScholarship($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE scholarships SET
                title = ?,
                description = ?,
                scholarship_type = ?,
                amount = ?,
                slots = ?,
                eligible_courses = ?,
                min_gwa = ?,
                max_family_income = ?,
                year_levels = ?,
                other_requirements = ?,
                application_start = ?,
                application_end = ?,
                status = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['scholarship_type'],
            $data['amount'],
            $data['slots'],
            $data['eligible_courses'],
            $data['min_gwa'],
            $data['max_family_income'],
            $data['year_levels'],
            $data['other_requirements'],
            $data['application_start'],
            $data['application_end'],
            $data['status'],
            $id
        ]);
    }

    public function getApplications($scholarship_id) {
        $stmt = $this->conn->prepare("
            SELECT sa.*, u.fullname as student_name, u.email as student_email,
                   sp.course, sp.year_level, sp.gwa, sp.school_name
            FROM scholarship_applications sa
            JOIN users u ON sa.student_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE sa.scholarship_id = ?
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$scholarship_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentApplications($student_id) {
        $stmt = $this->conn->prepare("
            SELECT sa.*, s.title, s.amount, s.scholarship_type,
                   u.fullname as provider_name
            FROM scholarship_applications sa
            JOIN scholarships s ON sa.scholarship_id = s.id
            JOIN users u ON s.provider_id = u.id
            WHERE sa.student_id = ?
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applyForScholarship($scholarship_id, $student_id, $data) {
        $stmt = $this->conn->prepare("
            INSERT INTO scholarship_applications (
                scholarship_id, student_id, personal_statement,
                why_deserve_scholarship, status
            ) VALUES (?, ?, ?, ?, 'Submitted')
        ");
        
        return $stmt->execute([
            $scholarship_id,
            $student_id,
            $data['personal_statement'],
            $data['why_deserve_scholarship']
        ]);
    }

    public function updateApplicationStatus($application_id, $status, $notes = null) {
        $stmt = $this->conn->prepare("
            UPDATE scholarship_applications 
            SET status = ?, provider_notes = ?, decided_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $notes, $application_id]);
    }

    public function deleteScholarship($id, $provider_id) {
        try {
            $this->conn->beginTransaction();
            
            // Verify ownership before deletion
            $stmt = $this->conn->prepare("SELECT id, workflow_status, title FROM scholarships WHERE id = ? AND provider_id = ?");
            $stmt->execute([$id, $provider_id]);
            $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$scholarship) {
                throw new Exception("Scholarship not found or access denied");
            }
            
            // Log the deletion attempt
            $stmt = $this->conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'DELETE_ATTEMPT', 'scholarship', ?, ?)");
            $stmt->execute([$provider_id, $id, "Attempting to delete scholarship: " . $scholarship['title']]);
            
            // Allow deletion of scholarships in any workflow status
            // Note: This will also clean up any workflow records
            
            // Delete related records first (due to foreign key constraints)
            
            // Delete workflow-related records (check if tables exist first)
            $stmt = $this->conn->prepare("DELETE FROM scholarship_email_log WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->conn->prepare("DELETE FROM committee_votes WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->conn->prepare("DELETE FROM scholarship_approval_stages WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->conn->prepare("DELETE FROM scholarship_audit_log WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            // Delete workflow stages and audit (only if tables exist)
            try {
                $stmt = $this->conn->prepare("DELETE FROM scholarship_workflow_stages WHERE scholarship_id = ?");
                $stmt->execute([$id]);
            } catch (PDOException $e) {
                // Table doesn't exist, skip
                if ($e->getCode() !== '42S02') {
                    throw $e;
                }
            }
            
            try {
                $stmt = $this->conn->prepare("DELETE FROM scholarship_workflow_audit WHERE scholarship_id = ?");
                $stmt->execute([$id]);
            } catch (PDOException $e) {
                // Table doesn't exist, skip
                if ($e->getCode() !== '42S02') {
                    throw $e;
                }
            }
            
            // Delete communications related to this scholarship
            $stmt = $this->conn->prepare("DELETE FROM communications WHERE related_scholarship_id = ?");
            $stmt->execute([$id]);
            
            // Delete applications and awards (these have foreign key constraints)
            $stmt = $this->conn->prepare("DELETE FROM scholarship_awards WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->conn->prepare("DELETE FROM scholarship_applications WHERE scholarship_id = ?");
            $stmt->execute([$id]);
            
            // Delete activity logs related to this scholarship (but not the deletion logs we just created)
            $stmt = $this->conn->prepare("DELETE FROM activity_logs WHERE entity_type = 'scholarship' AND entity_id = ? AND action != 'DELETE_ATTEMPT'");
            $stmt->execute([$id]);
            
            // Finally, delete the scholarship itself
            $stmt = $this->conn->prepare("DELETE FROM scholarships WHERE id = ? AND provider_id = ?");
            $result = $stmt->execute([$id, $provider_id]);
            
            if (!$result) {
                throw new Exception("Failed to delete scholarship record");
            }
            
            // Log successful deletion
            $stmt = $this->conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'DELETE_SUCCESS', 'scholarship', ?, ?)");
            $stmt->execute([$provider_id, $id, "Successfully deleted scholarship: " . $scholarship['title']]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            
            // Log the error
            if (isset($provider_id)) {
                $stmt = $this->conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'DELETE_ERROR', 'scholarship', ?, ?)");
                $stmt->execute([$provider_id, $id, "Delete failed: " . $e->getMessage()]);
            }
            
            throw $e;
        }
    }

    public function deleteApplication($application_id, $student_id) {
        try {
            $this->conn->beginTransaction();
            
            // Verify ownership before deletion
            $stmt = $this->conn->prepare("
                SELECT sa.id, sa.status, s.title 
                FROM scholarship_applications sa
                JOIN scholarships s ON sa.scholarship_id = s.id
                WHERE sa.id = ? AND sa.student_id = ?
            ");
            $stmt->execute([$application_id, $student_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                throw new Exception("Application not found or access denied");
            }
            
            // Only allow deletion of applications that are not yet approved
            if ($application['status'] == 'Approved') {
                throw new Exception("Cannot delete approved applications");
            }
            
            // Log the deletion attempt
            require_once __DIR__ . '/../core/ActivityLogger.php';
            $logger = new ActivityLogger();
            
            // Delete the application
            $stmt = $this->conn->prepare("DELETE FROM scholarship_applications WHERE id = ? AND student_id = ?");
            $result = $stmt->execute([$application_id, $student_id]);
            
            if (!$result) {
                throw new Exception("Failed to delete application");
            }
            
            // Log successful deletion
            $logger->logSystemActivity(
                $student_id, 
                'student', 
                'APPLICATION_DELETE', 
                'scholarship_application', 
                $application_id,
                "Application deleted for scholarship: " . $application['title'] . " (Status was: " . $application['status'] . ")"
            );
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getScholarshipApplications($scholarship_id, $provider_id) {
        $stmt = $this->conn->prepare("
            SELECT sa.*, u.fullname as student_name, u.email as student_email,
                   sp.course, sp.year_level, sp.gwa, sp.school_name
            FROM scholarship_applications sa
            JOIN scholarships s ON sa.scholarship_id = s.id
            JOIN users u ON sa.student_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE sa.scholarship_id = ? AND s.provider_id = ?
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$scholarship_id, $provider_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
