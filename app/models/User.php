<?php
require_once __DIR__ . "/../../config/database.php";

class User {

    private $conn;

    public function __construct() {
        $this->conn = (new Database())->connect();
    }

    public function register($fullname, $email, $password, $user_type = 'student') {

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            "INSERT INTO users(fullname,email,password,user_type) VALUES(?,?,?,?)"
        );

        return $stmt->execute([$fullname,$email,$hashed,$user_type]);
    }

    /**
     * Store the Google identifier for the user. Called when a Google login is
     * performed, either at first registration or on subsequent logins.
     */
    public function saveGoogleId($user_id, $google_id) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET google_id=? WHERE id=?"
        );
        return $stmt->execute([$google_id, $user_id]);
    }

    /**
     * Find a user by their Google ID (optional fallback for lookups).
     */
    public function findByGoogleId($google_id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE google_id=?"
        );
        $stmt->execute([$google_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {

        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE email=?"
        );

        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE id=?"
        );

        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfileCompletion($user_id, $step, $completed = false) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET profile_completion_step = ?, profile_completed = ? WHERE id = ?"
        );
        return $stmt->execute([$step, $completed ? 1 : 0, $user_id]);
    }

    public function getProfileCompletionStatus($user_id) {
        $stmt = $this->conn->prepare(
            "SELECT profile_completed, profile_completion_step FROM users WHERE id = ?"
        );
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function saveToken($user_id,$token) {

        $stmt = $this->conn->prepare(
            "INSERT INTO email_verifications(user_id,token) VALUES(?,?)"
        );

        return $stmt->execute([$user_id,$token]);
    }

    /**
     * Mark a user account as verified (used for Google logins where email is trusted).
     */
    public function markVerified($user_id) {
        $stmt = $this->conn->prepare(
            "UPDATE users SET is_verified=1 WHERE id=?"
        );
        return $stmt->execute([$user_id]);
    }

    public function verifyEmail($token) {

        $stmt = $this->conn->prepare(
            "SELECT * FROM email_verifications WHERE token=?"
        );

        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if($data) {

            try {
                $this->conn->beginTransaction();

                $stmt = $this->conn->prepare(
                    "UPDATE users SET is_verified=1 WHERE id=?"
                );
                $stmt->execute([$data['user_id']]);

                // delete the token to prevent reuse
                $stmt = $this->conn->prepare(
                    "DELETE FROM email_verifications WHERE id=?"
                );
                $stmt->execute([$data['id']]);

                $this->conn->commit();
                return true;
            } catch (Exception $e) {
                $this->conn->rollBack();
                return false;
            }
        }

        return false;
    }

    public function saveOTP($user_id,$otp) {

        // delete any previous OTP for this user that are already expired
        $stmt = $this->conn->prepare(
            "DELETE FROM otp_codes WHERE user_id=? AND expires_at <= NOW()"
        );
        $stmt->execute([$user_id]);

        // insert new OTP; expiration calculated by the database to avoid timezone drift
        $stmt = $this->conn->prepare(
            "INSERT INTO otp_codes(user_id,otp_code,expires_at)
             VALUES(?,?,DATE_ADD(NOW(), INTERVAL 5 MINUTE))"
        );

        return $stmt->execute([$user_id,$otp]);
    }

    public function validateOTP($user_id,$otp) {

        $stmt = $this->conn->prepare(
            "SELECT * FROM otp_codes
             WHERE user_id=? AND otp_code=? AND expires_at > NOW()"
        );

        $stmt->execute([$user_id,$otp]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // return OTP row regardless of expiry (for diagnostics)
    public function getOTP($user_id,$otp) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM otp_codes
             WHERE user_id=? AND otp_code=?"
        );
        $stmt->execute([$user_id,$otp]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // delete an OTP entry by id
    public function deleteOTPById($id) {
        $stmt = $this->conn->prepare(
            "DELETE FROM otp_codes WHERE id=?"
        );
        return $stmt->execute([$id]);
    }
}
