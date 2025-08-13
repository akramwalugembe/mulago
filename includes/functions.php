<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize user input
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to specified URL
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user has required role
 * @param string $requiredRole
 * @return bool
 */
function hasRole($requiredRole) {
    require_once __DIR__ . '/auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
    
    return $user && $user['role'] === $requiredRole;
}

/**
 * Log activity to database
 * @param int $userId
 * @param string $action
 * @param string $details
 * @return bool
 */
function logActivity($userId, $action, $details = '') {
    try {
        $db = db();
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}


function formatDate($dateString) {
    if (empty($dateString)) return '';
    $date = new DateTime($dateString);
    return $date->format('M j, Y');
}

function formatDateTime($datetimeString) {
    if (empty($datetimeString)) return '';
    $date = new DateTime($datetimeString);
    return $date->format('M j, Y g:i A');
}

function getTransactionBadgeClass($type) {
    switch ($type) {
        case 'issue': return 'primary';
        case 'return': return 'success';
        case 'adjustment': return 'warning';
        case 'transfer': return 'info';
        default: return 'secondary';
    }
}


?>