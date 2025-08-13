<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    logActivity($user['user_id'], 'logout', 'User logged out');
}

$auth->logout();

redirect('login.php');