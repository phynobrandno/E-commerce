<?php
// classes/User.php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($username, $email, $password, $role) {
        // Check if email already exists
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered!'];
        }

        // Check if username already exists
        if ($this->usernameExists($username)) {
            return ['success' => false, 'message' => 'Username already taken!'];
        }

        $query = "INSERT INTO " . $this->table . " (username, email, password, role) VALUES (:username, :email, :password, :role)";
        $stmt = $this->conn->prepare($query);

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $role);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful!'];
        }

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    public function login($email, $password) {
        $query = "SELECT id, username, email, password, role FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            if (password_verify($password, $row['password'])) {
                return [
                    'success' => true,
                    'user' => [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'role' => $row['role']
                    ]
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid email or password!'];
    }

    private function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    private function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
?>