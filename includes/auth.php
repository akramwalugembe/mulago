<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }

    /**
     * User login
     * @param string $username
     * @param string $password
     * @return array|false Returns user data on success, false on failure
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['user_id']);
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user's last login timestamp
     * @param int $userId
     */
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }

    /**
     * Change user password
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verify current password first
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return false;
            }
            
            // Update to new password
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
            $stmt->bindParam(':password_hash', $newHash, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initiate password reset process
     * @param string $email
     * @return string|false Returns reset token on success, false on failure
     */
    public function initiatePasswordReset($email) {
        try {
            // Check if email exists
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Generate reset token (valid for 1 hour)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $this->db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE user_id = :user_id");
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->bindParam(':expires', $expires, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return $token;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Password reset initiation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Complete password reset process
     * @param string $token
     * @param string $newPassword
     * @return bool
     */
    public function completePasswordReset($token, $newPassword) {
        try {
            // Verify token exists and isn't expired
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE reset_token = :token AND reset_expires > NOW() LIMIT 1");
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Update password and clear reset fields
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_expires = NULL WHERE user_id = :user_id");
            $stmt->bindParam(':password_hash', $newHash, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Password reset completion error: " . $e->getMessage());
            return false;
        }
    }


    public function logout() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return !empty($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Failed to get current user: " . $e->getMessage());
            return null;
        }
    }
}
?>