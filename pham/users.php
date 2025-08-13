<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->getCurrentUser()['role'] !== 'admin') {
    die('Access denied. Administrator privileges required.');
}

$db = db();
$message = '';
$error = '';
$action = $_GET['action'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid form submission.';
    } else {
        try {
            switch ($action) {
                case 'add':
                    $username = sanitizeInput($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $fullName = sanitizeInput($_POST['full_name'] ?? '');
                    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                    $departmentId = (int)($_POST['department_id'] ?? 0);
                    $role = in_array($_POST['role'] ?? '', ['admin', 'pharmacist', 'department_staff']) 
                            ? $_POST['role'] 
                            : 'department_staff';
                    $isActive = isset($_POST['is_active']) ? 1 : 0;

                    // Validate
                    if (empty($username) || empty($password) || empty($fullName)) {
                        throw new Exception('Username, password and full name are required.');
                    }

                    if (strlen($password) < 8) {
                        throw new Exception('Password must be at least 8 characters.');
                    }

                    // Check if username exists
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username already exists.');
                    }

                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    // Insert user
                    $stmt = $db->prepare("INSERT INTO users 
                                        (username, password_hash, full_name, email, 
                                        department_id, role, is_active)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $username,
                        $passwordHash,
                        $fullName,
                        $email,
                        $departmentId > 0 ? $departmentId : null,
                        $role,
                        $isActive
                    ]);

                    $message = 'User added successfully.';
                    break;

                case 'edit':
                    $userId = (int)($_POST['user_id'] ?? 0);
                    $fullName = sanitizeInput($_POST['full_name'] ?? '');
                    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                    $departmentId = (int)($_POST['department_id'] ?? 0);
                    $role = in_array($_POST['role'] ?? '', ['admin', 'pharmacist', 'department_staff']) 
                            ? $_POST['role'] 
                            : 'department_staff';
                    $isActive = isset($_POST['is_active']) ? 1 : 0;

                    // Validate
                    if ($userId <= 0) {
                        throw new Exception('Invalid user ID.');
                    }

                    if (empty($fullName)) {
                        throw new Exception('Full name is required.');
                    }

                    // Update user
                    $stmt = $db->prepare("UPDATE users SET 
                                        full_name = ?, 
                                        email = ?, 
                                        department_id = ?, 
                                        role = ?, 
                                        is_active = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                        WHERE user_id = ?");
                    $stmt->execute([
                        $fullName,
                        $email,
                        $departmentId > 0 ? $departmentId : null,
                        $role,
                        $isActive,
                        $userId
                    ]);

                    $message = 'User updated successfully.';
                    break;

                case 'password':
                    $userId = (int)($_POST['user_id'] ?? 0);
                    $password = $_POST['password'] ?? '';

                    // Validate
                    if ($userId <= 0) {
                        throw new Exception('Invalid user ID.');
                    }

                    if (strlen($password) < 8) {
                        throw new Exception('Password must be at least 8 characters.');
                    }

                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    // Update password
                    $stmt = $db->prepare("UPDATE users SET 
                                        password_hash = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                        WHERE user_id = ?");
                    $stmt->execute([$passwordHash, $userId]);

                    $message = 'Password updated successfully.';
                    break;

                case 'delete':
                    $userId = (int)($_POST['user_id'] ?? 0);
                    $currentUserId = $auth->getCurrentUser()['user_id'];

                    // Validate
                    if ($userId <= 0) {
                        throw new Exception('Invalid user ID.');
                    }

                    if ($userId === $currentUserId) {
                        throw new Exception('You cannot delete your own account.');
                    }

                    // Soft delete (set inactive)
                    $stmt = $db->prepare("UPDATE users SET 
                                        is_active = 0,
                                        updated_at = CURRENT_TIMESTAMP
                                        WHERE user_id = ?");
                    $stmt->execute([$userId]);

                    $message = 'User deactivated successfully.';
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all users with department names
$users = $db->query("
    SELECT u.*, d.department_name 
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    ORDER BY u.role, u.full_name
")->fetchAll();

// Get departments for dropdown
$departments = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">System Users</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr class="<?= !$user['is_active'] ? 'table-secondary' : '' ?>">
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $user['role'] === 'admin' ? 'danger' : 
                                    ($user['role'] === 'pharmacist' ? 'primary' : 'secondary') 
                                ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['is_active'] ? 'success' : 'warning' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?></td>
                            <td class="text-center">
    <div class="btn-group" role="group">
        <button class="btn btn-sm btn-outline-primary" 
                title="Edit User"
                onclick="showEditModal(
                    <?= $user['user_id'] ?>,
                    '<?= htmlspecialchars(addslashes($user['username'])) ?>',
                    '<?= htmlspecialchars(addslashes($user['full_name'])) ?>',
                    '<?= htmlspecialchars(addslashes($user['email'])) ?>',
                    <?= $user['department_id'] ?? 'null' ?>,
                    '<?= $user['role'] ?>',
                    <?= $user['is_active'] ?>
                )">
            <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm btn-outline-warning"
                title="Change Password"
                onclick="showPasswordModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>')">
            <i class="fas fa-key"></i>
        </button>
        <?php if ($user['is_active'] && $user['user_id'] != $auth->getCurrentUser()['user_id']): ?>
        <button class="btn btn-sm btn-outline-danger"
                title="Deactivate User"
                onclick="confirmDelete(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>')">
            <i class="fas fa-user-times"></i>
        </button>
        <?php endif; ?>
    </div>
</td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="?action=add">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="addUsername" class="form-label">Username *</label>
            <input type="text" class="form-control" id="addUsername" name="username" required>
          </div>
          <div class="mb-3">
            <label for="addPassword" class="form-label">Password *</label>
            <input type="password" class="form-control" id="addPassword" name="password" required>
            <small class="form-text text-muted">At least 8 characters</small>
          </div>
          <div class="mb-3">
            <label for="addFullName" class="form-label">Full Name *</label>
            <input type="text" class="form-control" id="addFullName" name="full_name" required>
          </div>
          <div class="mb-3">
            <label for="addEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="addEmail" name="email">
          </div>
          <div class="mb-3">
            <label for="addDepartment" class="form-label">Department</label>
            <select class="form-control" id="addDepartment" name="department_id">
              <option value="">-- Select Department --</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="addRole" class="form-label">Role *</label>
            <select class="form-control" id="addRole" name="role" required>
              <option value="department_staff">Department Staff</option>
              <option value="pharmacist">Pharmacist</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="addIsActive" name="is_active" checked>
            <label class="form-check-label" for="addIsActive">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="?action=edit">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="editFullName" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="editDepartment" class="form-label">Department</label>
                        <select class="form-select" id="editDepartment" name="department_id">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role *</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="department_staff">Department Staff</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel" aria-hidden="true" style="z-index: 1052;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">Change Password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=password">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="passwordUserId" name="user_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="passwordUsername">Username</label>
                        <input type="text" class="form-control" id="passwordUsername" readonly>
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="password" required>
                        <small class="form-text text-muted">At least 8 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true" style="z-index: 1053;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deactivation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=delete">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="deleteUserId" name="user_id">
                <div class="modal-body">
                    <p>Are you sure you want to deactivate user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger">This action cannot be undone. The user will no longer be able to log in.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
