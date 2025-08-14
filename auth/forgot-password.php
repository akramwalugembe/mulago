<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/header.php';

$auth = new Auth();
$message = '';
$error = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('../pham/index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $token = $auth->initiatePasswordReset($email);
        
        if ($token) {
            $resetLink = "http://$_SERVER[HTTP_HOST]/reset-password.php?token=$token";
            $message = "Password reset link has been generated. <a href=\"$resetLink\">Click here to reset your password</a>.";
            logActivity(null, 'password_reset_request', "Reset requested for email: $email");
        } else {
            $error = 'If that email exists in our system, you will receive a password reset link.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Forgot Password</h2>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Request Reset Link</button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Back to login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>