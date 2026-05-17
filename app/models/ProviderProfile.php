<?php
require_once __DIR__ . "/../../config/database.php";

class ProviderProfile {
    private $conn;

    public function __construct() {
        $this->conn = (new Database())->connect();
    }

    public function createProfile($user_id, $data) {
        $stmt = $this->conn->prepare("
            INSERT INTO provider_profiles (
                user_id, organization_name, organization_type, registration_number,
                tin, contact_person, position, office_address, contact_number,
                website, mission, vision
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $data['organization_name'],
            $data['organization_type'],
            $data['registration_number'] ?? null,
            $data['tin'] ?? null,
            $data['contact_person'] ?? null,
            $data['position'] ?? null,
            $data['office_address'] ?? null,
            $data['contact_number'] ?? null,
            $data['website'] ?? null,
            $data['mission'] ?? null,
            $data['vision'] ?? null
        ]);
    }

    public function getProfile($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM provider_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($user_id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE provider_profiles SET
                organization_name = ?,
                organization_type = ?,
                registration_number = ?,
                tin = ?,
                contact_person = ?,
                position = ?,
                office_address = ?,
                contact_number = ?,
                website = ?,
                mission = ?,
                vision = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $data['organization_name'],
            $data['organization_type'],
            $data['registration_number'] ?? null,
            $data['tin'] ?? null,
            $data['contact_person'] ?? null,
            $data['position'] ?? null,
            $data['office_address'] ?? null,
            $data['contact_number'] ?? null,
            $data['website'] ?? null,
            $data['mission'] ?? null,
            $data['vision'] ?? null,
            $user_id
        ]);
    }

    public function getAllProviders() {
        $stmt = $this->conn->prepare("
            SELECT pp.*, u.fullname, u.email, u.created_at as user_created
            FROM provider_profiles pp
            JOIN users u ON pp.user_id = u.id
            WHERE u.user_type = 'provider'
            ORDER BY pp.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifyProvider($user_id, $verified = true) {
        $stmt = $this->conn->prepare("
            UPDATE provider_profiles SET is_verified = ? WHERE user_id = ?
        ");
        return $stmt->execute([$verified ? 1 : 0, $user_id]);
    }
}
?>