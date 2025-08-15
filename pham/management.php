<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('/auth/login.php');
}

// Check permissions
$currentUser = $auth->getCurrentUser();
if (!in_array($currentUser['role'], ['admin', 'department_staff'])) {
    $_SESSION['flash_message'] = 'Access denied. You need higher privileges.';
    header('Location: index.php');
    exit();
    // die('Access denied. You do not have permission to view this page.');
}

$db = db();
$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'categories';
$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!verifyCsrfToken($csrfToken)) {
            throw new Exception('Invalid form submission.');
        }

        switch ($action) {
            // Category actions
            case 'add_category':
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');

                if (empty($name)) {
                    throw new Exception('Category name is required.');
                }

                $stmt = $db->prepare("INSERT INTO drug_categories 
                    (category_name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $message = 'Category added successfully.';
                $tab = 'categories';
                break;

            case 'edit_category':
                $id = (int)($_POST['id'] ?? 0);
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Invalid category.');
                }

                $stmt = $db->prepare("UPDATE drug_categories SET 
                    category_name = ?, description = ? 
                    WHERE category_id = ?");
                $stmt->execute([$name, $description, $id]);
                $message = 'Category updated successfully.';
                $tab = 'categories';
                break;

            case 'delete_category':
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Invalid category.');
                }

                // Check if category is in use
                $stmt = $db->prepare("SELECT COUNT(*) FROM drugs WHERE category_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete category - it is assigned to drugs.');
                }

                $stmt = $db->prepare("DELETE FROM drug_categories WHERE category_id = ?");
                $stmt->execute([$id]);
                $message = 'Category deleted successfully.';
                $tab = 'categories';
                break;

            // Supplier actions
            case 'add_supplier':
                $name = sanitizeInput($_POST['name'] ?? '');
                $contactPerson = sanitizeInput($_POST['contact_person'] ?? '');
                $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $address = sanitizeInput($_POST['address'] ?? '');

                if (empty($name)) {
                    throw new Exception('Supplier name is required.');
                }

                $stmt = $db->prepare("INSERT INTO suppliers 
                    (supplier_name, contact_person, contact_phone, email, address) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contactPerson, $contactPhone, $email, $address]);
                $message = 'Supplier added successfully.';
                $tab = 'suppliers';
                break;

            case 'edit_supplier':
                $id = (int)($_POST['id'] ?? 0);
                $name = sanitizeInput($_POST['name'] ?? '');
                $contactPerson = sanitizeInput($_POST['contact_person'] ?? '');
                $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $address = sanitizeInput($_POST['address'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($id <= 0) {
                    throw new Exception('Invalid supplier.');
                }

                $stmt = $db->prepare("UPDATE suppliers SET 
                    supplier_name = ?, contact_person = ?, contact_phone = ?, 
                    email = ?, address = ?, is_active = ? 
                    WHERE supplier_id = ?");
                $stmt->execute([$name, $contactPerson, $contactPhone, $email, $address, $isActive, $id]);
                $message = 'Supplier updated successfully.';
                $tab = 'suppliers';
                break;

            case 'delete_supplier':
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Invalid supplier.');
                }

                // Check if supplier is in use
                $stmt = $db->prepare("SELECT COUNT(*) FROM purchases WHERE supplier_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete supplier - they have purchase records.');
                }

                $stmt = $db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $message = 'Supplier deleted successfully.';
                $tab = 'suppliers';
                break;
        }
    }

    $categories = $db->query("SELECT * FROM drug_categories ORDER BY category_name")->fetchAll();
    $suppliers = $db->query("SELECT * FROM suppliers ORDER BY supplier_name")->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Management</h1>
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

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="managementTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'categories' ? 'active' : '' ?>"
                id="categories-tab" data-toggle="tab" href="#categories" role="tab">
                Drug Categories
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'suppliers' ? 'active' : '' ?>"
                id="suppliers-tab" data-toggle="tab" href="#suppliers" role="tab">
                Suppliers
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="managementTabsContent">
        <!-- Categories Tab -->
        <div class="tab-pane fade <?= $tab === 'categories' ? 'show active' : '' ?>" id="categories" role="tabpanel">
            <div class="card shadow mt-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Drug Categories</h6>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCategoryModal">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="categoriesTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories ?? [] as $cat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                                        <td><?= htmlspecialchars($cat['description']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-primary btn-sm"
                                                    onclick="PharmacyModals.showCategoryEditModal(
                <?= $cat['category_id'] ?>, 
                '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>', 
                '<?= htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') ?>'
            )"
                                                    title="Edit Category">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="PharmacyModals.showDeleteModal(
                'category', 
                <?= $cat['category_id'] ?>, 
                '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>'
            )"
                                                    title="Delete Category">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

        <!-- Suppliers Tab -->
        <div class="tab-pane fade <?= $tab === 'suppliers' ? 'show active' : '' ?>" id="suppliers" role="tabpanel">
            <div class="card shadow mt-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Suppliers</h6>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addSupplierModal">
                        <i class="fas fa-plus"></i> Add Supplier
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="suppliersTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact Phone</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers ?? [] as $supplier): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
                                        <td><?= htmlspecialchars($supplier['contact_phone']) ?></td>
                                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $supplier['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $supplier['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-primary btn-sm"
                                                    onclick="PharmacyModals.showSupplierEditModal(
                                                        <?= $supplier['supplier_id'] ?>, 
                                                        '<?= htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8') ?>', 
                                                        '<?= htmlspecialchars($supplier['contact_person'], ENT_QUOTES, 'UTF-8') ?>', 
                                                        '<?= htmlspecialchars($supplier['contact_phone'], ENT_QUOTES, 'UTF-8') ?>', 
                                                        '<?= htmlspecialchars($supplier['email'], ENT_QUOTES, 'UTF-8') ?>', 
                                                        '<?= htmlspecialchars($supplier['address'], ENT_QUOTES, 'UTF-8') ?>', 
                                                        <?= $supplier['is_active'] ? 1 : 0 ?>
                                                    )"
                                                    title="Edit Supplier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="PharmacyModals.showDeleteModal(
                                                        'supplier', 
                                                        <?= $supplier['supplier_id'] ?>, 
                                                        '<?= htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8') ?>'
                                                    )"
                                                    title="Delete Supplier">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=add_category&tab=categories">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="catName">Category Name *</label>
                        <input type="text" class="form-control" id="catName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="catDescription">Description</label>
                        <textarea class="form-control" id="catDescription" name="description" rows="3"></textarea>
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
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=edit_category&tab=categories">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="editCatId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editCatName">Category Name *</label>
                        <input type="text" class="form-control" id="editCatName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="editCatDescription">Description</label>
                        <textarea class="form-control" id="editCatDescription" name="description" rows="3"></textarea>
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=add_supplier&tab=suppliers">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="supplierName">Supplier Name *</label>
                        <input type="text" class="form-control" id="supplierName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="supplierContactPerson">Contact Person</label>
                        <input type="text" class="form-control" id="supplierContactPerson" name="contact_person">
                    </div>
                    <div class="form-group">
                        <label for="supplierContactPhone">Contact Phone</label>
                        <input type="text" class="form-control" id="supplierContactPhone" name="contact_phone">
                    </div>
                    <div class="form-group">
                        <label for="supplierEmail">Email</label>
                        <input type="email" class="form-control" id="supplierEmail" name="email">
                    </div>
                    <div class="form-group">
                        <label for="supplierAddress">Address</label>
                        <textarea class="form-control" id="supplierAddress" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Supplier</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="?action=edit_supplier&tab=suppliers">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="editSupplierId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editSupplierName">Supplier Name *</label>
                        <input type="text" class="form-control" id="editSupplierName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="editSupplierContactPerson">Contact Person</label>
                        <input type="text" class="form-control" id="editSupplierContactPerson" name="contact_person">
                    </div>
                    <div class="form-group">
                        <label for="editSupplierContactPhone">Contact Phone</label>
                        <input type="text" class="form-control" id="editSupplierContactPhone" name="contact_phone">
                    </div>
                    <div class="form-group">
                        <label for="editSupplierEmail">Email</label>
                        <input type="email" class="form-control" id="editSupplierEmail" name="email">
                    </div>
                    <div class="form-group">
                        <label for="editSupplierAddress">Address</label>
                        <textarea class="form-control" id="editSupplierAddress" name="address" rows="2"></textarea>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="editSupplierIsActive" name="is_active" value="1">
                        <label class="form-check-label" for="editSupplierIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1052;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post" action="" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="deleteId" name="id">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>