<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->connect();
$stmt = $conn->query('SELECT id, fullname FROM users WHERE user_type = "provider" LIMIT 1');
$provider = $stmt->fetch(PDO::FETCH_ASSOC);
if ($provider) {
    echo $provider['id'];
} else {
    echo '0';
}
?>