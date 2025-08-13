<?php
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();
$message = '';
$error = '';

// Check for token in URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid password reset link. Please request a new one.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($password) || empty($confirmPassword)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        if ($auth->completePasswordReset($token, $password)) {
            $message = 'Your password has been reset successfully. You can now <a href="login.php">login</a> with your new password.';
            logActivity(null, 'password_reset_success', "Password reset for token: $token");
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Reset Password</h2>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                
                <?php if (empty($message)): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="form-text text-muted">At least 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Back to login</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>