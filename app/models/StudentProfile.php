<?php
require_once __DIR__ . "/../../config/database.php";

class StudentProfile {
    private $conn;

    public function __construct() {
        $this->conn = (new Database())->connect();
    }

    public function getProfile($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createProfile($user_id) {
        $stmt = $this->conn->prepare("INSERT INTO student_profiles (user_id) VALUES (?)");
        return $stmt->execute([$user_id]);
    }

    public function updatePhase1($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE student_profiles SET
                last_name = ?,
                first_name = ?,
                middle_name = ?,
                suffix = ?,
                birthdate = ?,
                place_of_birth = ?,
                sex = ?,
                civil_status = ?,
                citizenship = ?,
                mobile_number = ?,
                landline = ?,
                present_address = ?,
                permanent_address = ?,
                zip_code = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['last_name'],
            $data['first_name'],
            $data['middle_name'],
            $data['suffix'],
            $data['birthdate'],
            $data['place_of_birth'],
            $data['sex'],
            $data['civil_status'],
            $data['citizenship'],
            $data['mobile_number'],
            $data['landline'],
            $data['present_address'],
            $data['permanent_address'],
            $data['zip_code'],
            $user_id
        ]);
    }

    public function updatePhase2($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE student_profiles SET
                school_id = ?,
                school_name = ?,
                school_address = ?,
                school_sector = ?,
                course = ?,
                year_level = ?,
                type_of_disability = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['school_id'],
            $data['school_name'],
            $data['school_address'],
            $data['school_sector'],
            $data['course'],
            $data['year_level'],
            $data['type_of_disability'],
            $user_id
        ]);
    }

    public function updatePhase3($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE student_profiles SET
                father_name = ?,
                father_address = ?,
                father_contact = ?,
                father_occupation = ?,
                father_employer = ?,
                father_employer_address = ?,
                father_education = ?,
                father_income = ?,
                mother_name = ?,
                mother_address = ?,
                mother_contact = ?,
                mother_occupation = ?,
                mother_employer = ?,
                mother_employer_address = ?,
                mother_education = ?,
                mother_income = ?,
                legal_guardian = ?,
                num_siblings = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['father_name'],
            $data['father_address'],
            $data['father_contact'],
            $data['father_occupation'],
            $data['father_employer'],
            $data['father_employer_address'],
            $data['father_education'],
            $data['father_income'],
            $data['mother_name'],
            $data['mother_address'],
            $data['mother_contact'],
            $data['mother_occupation'],
            $data['mother_employer'],
            $data['mother_employer_address'],
            $data['mother_education'],
            $data['mother_income'],
            $data['legal_guardian'],
            $data['num_siblings'],
            $user_id
        ]);
    }

    public function updatePhase4($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE student_profiles SET
                family_monthly_income = ?,
                is_4ps_beneficiary = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['family_monthly_income'],
            $data['is_4ps_beneficiary'],
            $user_id
        ]);
    }

    public function updatePhase5($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE student_profiles SET
                gwa = ?,
                awards_received = ?,
                profile_photo = ?,
                completed_at = NOW()
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['gwa'],
            $data['awards_received'],
            $data['profile_photo'],
            $user_id
        ]);
    }

    public function getSchools() {
        $stmt = $this->conn->prepare("SELECT * FROM schools WHERE is_active = 1 ORDER BY school_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
