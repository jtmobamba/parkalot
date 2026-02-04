<?php
class UserDAO {
    public $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function create($name, $email, $password, $role = 'customer') {
        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (?, ?, ?, ?)"
        );
        $success = $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        ]);
        
        if ($success) {
            // Return the new user_id instead of true
            return $this->db->lastInsertId();
        }
        
        return false;
    }

    public function updateRole($userId, $role) {
        $stmt = $this->db->prepare(
            "UPDATE users SET role = ? WHERE user_id = ?"
        );
        return $stmt->execute([$role, $userId]);
    }

    public function updateLastLogin($userId) {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login = NOW() WHERE user_id = ?"
        );
        return $stmt->execute([$userId]);
    }

    public function getAllUsers() {
        $stmt = $this->db->query(
            "SELECT user_id, full_name, email, role, email_verified, created_at, last_login 
             FROM users ORDER BY created_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>