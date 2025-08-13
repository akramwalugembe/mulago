<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('/auth/login.php');
}

// Check permissions
$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['admin', 'pharmacist'])) {
    die('Access denied. You do not have permission to view this page.');
}

$db = db();
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        
        if (!verifyCsrfToken($csrfToken)) {
            throw new Exception('Invalid form submission.');
        }

        switch ($action) {
            case 'add':
                $categoryName = sanitizeInput($_POST['category_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($categoryName)) {
                    throw new Exception('Category name is required.');
                }
                
                $stmt = $db->prepare("INSERT INTO drug_categories (category_name, description) VALUES (?, ?)");
                $stmt->execute([$categoryName, $description]);
                $message = 'Category added successfully.';
                break;
                
            case 'edit':
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $categoryName = sanitizeInput($_POST['category_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if ($categoryId <= 0) {
                    throw new Exception('Invalid category.');
                }
                
                $stmt = $db->prepare("UPDATE drug_categories SET category_name = ?, description = ? WHERE category_id = ?");
                $stmt->execute([$categoryName, $description, $categoryId]);
                $message = 'Category updated successfully.';
                break;
                
            case 'delete':
                $categoryId = (int)($_POST['category_id'] ?? 0);
                
                if ($categoryId <= 0) {
                    throw new Exception('Invalid category.');
                }
                
                // Check if category is in use
                $stmt = $db->prepare("SELECT COUNT(*) FROM drugs WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete category - it is assigned to drugs.');
                }
                
                $stmt = $db->prepare("DELETE FROM drug_categories WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                $message = 'Category deleted successfully.';
                break;
        }
    }

    // Get all categories
    $categories = $db->query("SELECT * FROM drug_categories ORDER BY category_name")->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Drug Categories</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>

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

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="categoriesTable">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= htmlspecialchars($category['category_name']) ?></td>
                            <td><?= htmlspecialchars($category['description']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="showEditModal(<?= $category['category_id'] ?>, '<?= htmlspecialchars(addslashes($category['category_name'])) ?>', '<?= htmlspecialchars(addslashes($category['description'])) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="confirmDelete(<?= $category['category_id'] ?>, '<?= htmlspecialchars(addslashes($category['category_name'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=add">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="categoryName">Category Name</label>
                        <input type="text" class="form-control" id="categoryName" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=edit">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="editCategoryId" name="category_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editCategoryName">Category Name</label>
                        <input type="text" class="form-control" id="editCategoryName" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=delete">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="deleteCategoryId" name="category_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete category: <strong id="deleteCategoryName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show edit modal
function showEditModal(categoryId, categoryName, description) {
    $('#editCategoryId').val(categoryId);
    $('#editCategoryName').val(categoryName);
    $('#editDescription').val(description);
    $('#editCategoryModal').modal('show');
}

// Show delete confirmation modal
function confirmDelete(categoryId, categoryName) {
    $('#deleteCategoryId').val(categoryId);
    $('#deleteCategoryName').text(categoryName);
    $('#deleteModal').modal('show');
}

// Initialize DataTable
$(document).ready(function() {
    $('#categoriesTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']]
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>