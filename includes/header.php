<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mulago Pharmacy Inventory System</title>
<!-- Bootstrap 5.3.7 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<!-- Font Awesome 5 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body style="padding-top: 56px;">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand">Mulago Pharmacy</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($currentUser): ?>
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>

                    <?php if (in_array($currentUser['role'], ['admin', 'pharmacist'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">Transactions</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($currentUser['role'], ['admin', 'department_staff'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="drugs.php">Drugs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">Inventory</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="management.php">Management</a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <?= htmlspecialchars($currentUser['full_name']) ?>
                        </a>
                        <div class="dropdown-menu">
                            <!-- <a class="dropdown-item" href="../auth/profile.php">Profile</a> -->
                            <a class="dropdown-item" href="change-password.php">Change Password</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../auth/logout.php">Logout</a>
                        </div>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <!-- <div class="container mt-4"> -->
    <div class="container-fluid mt-4 px-md-4"></div>