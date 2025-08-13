<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getCurrentUser()['role'], ['admin', 'pharmacist'])) {
    die('Access denied. You do not have permission to view this page.');
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
                    $drugName = sanitizeInput($_POST['drug_name'] ?? '');
                    $genericName = sanitizeInput($_POST['generic_name'] ?? '');
                    $categoryId = (int)($_POST['category_id'] ?? 0);
                    $unitOfMeasure = sanitizeInput($_POST['unit_of_measure'] ?? '');
                    $reorderLevel = (int)($_POST['reorder_level'] ?? 0);
                    $description = sanitizeInput($_POST['description'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;

                    // Validate
                    if (empty($drugName) || empty($unitOfMeasure) || $reorderLevel < 0) {
                        throw new Exception('Drug name, unit of measure and valid reorder level are required.');
                    }

                    // Check if drug exists
                    $stmt = $db->prepare("SELECT drug_id FROM drugs WHERE drug_name = ?");
                    $stmt->execute([$drugName]);
                    if ($stmt->fetch()) {
                        throw new Exception('Drug already exists.');
                    }

                    // Insert drug
                    $stmt = $db->prepare("INSERT INTO drugs 
                                        (drug_name, generic_name, category_id, unit_of_measure, 
                                        reorder_level, description, is_active)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $drugName,
                        $genericName ?: null,
                        $categoryId > 0 ? $categoryId : null,
                        $unitOfMeasure,
                        $reorderLevel,
                        $description ?: null,
                        $isActive
                    ]);

                    $message = 'Drug added successfully.';
                    break;

                case 'edit':
                    $drugId = (int)($_POST['drug_id'] ?? 0);
                    $drugName = sanitizeInput($_POST['drug_name'] ?? '');
                    $genericName = sanitizeInput($_POST['generic_name'] ?? '');
                    $categoryId = (int)($_POST['category_id'] ?? 0);
                    $unitOfMeasure = sanitizeInput($_POST['unit_of_measure'] ?? '');
                    $reorderLevel = (int)($_POST['reorder_level'] ?? 0);
                    $description = sanitizeInput($_POST['description'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;

                    // Validate
                    if ($drugId <= 0) {
                        throw new Exception('Invalid drug ID.');
                    }

                    if (empty($drugName) || empty($unitOfMeasure) || $reorderLevel < 0) {
                        throw new Exception('Drug name, unit of measure and valid reorder level are required.');
                    }

                    // Update drug
                    $stmt = $db->prepare("UPDATE drugs SET 
                                        drug_name = ?, 
                                        generic_name = ?, 
                                        category_id = ?, 
                                        unit_of_measure = ?, 
                                        reorder_level = ?, 
                                        description = ?,
                                        is_active = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                        WHERE drug_id = ?");
                    $stmt->execute([
                        $drugName,
                        $genericName ?: null,
                        $categoryId > 0 ? $categoryId : null,
                        $unitOfMeasure,
                        $reorderLevel,
                        $description ?: null,
                        $isActive,
                        $drugId
                    ]);

                    $message = 'Drug updated successfully.';
                    break;

                case 'delete':
                    $drugId = (int)($_POST['drug_id'] ?? 0);

                    // Validate
                    if ($drugId <= 0) {
                        throw new Exception('Invalid drug ID.');
                    }

                    // Check if drug has inventory
                    $stmt = $db->prepare("SELECT COUNT(*) FROM inventory WHERE drug_id = ?");
                    $stmt->execute([$drugId]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Cannot delete drug with existing inventory.');
                    }

                    // Soft delete (set inactive)
                    $stmt = $db->prepare("UPDATE drugs SET 
                                        is_active = 0,
                                        updated_at = CURRENT_TIMESTAMP
                                        WHERE drug_id = ?");
                    $stmt->execute([$drugId]);

                    $message = 'Drug deactivated successfully.';
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all drugs with category names
$drugs = $db->query("
    SELECT d.*, c.category_name 
    FROM drugs d
    LEFT JOIN drug_categories c ON d.category_id = c.category_id
    ORDER BY d.drug_name
")->fetchAll();

// Get categories for dropdown
$categories = $db->query("SELECT category_id, category_name FROM drug_categories ORDER BY category_name")->fetchAll();

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Drug Management</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addDrugModal">
            <i class="fas fa-plus"></i> Add New Drug
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

    <!-- Drugs Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Drug Inventory</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="drugsTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Drug Name</th>
                            <th>Generic Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drugs as $drug): ?>
                        <tr class="<?= !$drug['is_active'] ? 'table-secondary' : '' ?>">
                            <td><?= htmlspecialchars($drug['drug_name']) ?></td>
                            <td><?= htmlspecialchars($drug['generic_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($drug['category_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($drug['unit_of_measure']) ?></td>
                            <td><?= $drug['reorder_level'] ?></td>
                            <td>
                                <span class="badge badge-<?= $drug['is_active'] ? 'success' : 'warning' ?>">
                                    <?= $drug['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-primary" 
                                            onclick="showEditModal(
                                                <?= $drug['drug_id'] ?>,
                                                '<?= htmlspecialchars(addslashes($drug['drug_name'])) ?>',
                                                '<?= htmlspecialchars(addslashes($drug['generic_name'] ?? '')) ?>',
                                                <?= $drug['category_id'] ?? 'null' ?>,
                                                '<?= htmlspecialchars(addslashes($drug['unit_of_measure'])) ?>',
                                                <?= $drug['reorder_level'] ?>,
                                                '<?= htmlspecialchars(addslashes($drug['description'] ?? '')) ?>',
                                                <?= $drug['is_active'] ?>
                                            )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($drug['is_active']): ?>
                                    <button class="btn btn-danger" 
                                            onclick="confirmDelete(<?= $drug['drug_id'] ?>, '<?= htmlspecialchars(addslashes($drug['drug_name'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
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

<!-- Add Drug Modal -->
<div class="modal fade" id="addDrugModal" tabindex="-1" role="dialog" aria-labelledby="addDrugModalLabel" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDrugModalLabel">Add New Drug</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=add">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="addDrugName">Drug Name *</label>
                        <input type="text" class="form-control" id="addDrugName" name="drug_name" required>
                    </div>
                    <div class="form-group">
                        <label for="addGenericName">Generic Name</label>
                        <input type="text" class="form-control" id="addGenericName" name="generic_name">
                    </div>
                    <div class="form-group">
                        <label for="addCategory">Category</label>
                        <select class="form-control" id="addCategory" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="addUnit">Unit of Measure *</label>
                        <input type="text" class="form-control" id="addUnit" name="unit_of_measure" required>
                    </div>
                    <div class="form-group">
                        <label for="addReorderLevel">Reorder Level *</label>
                        <input type="number" class="form-control" id="addReorderLevel" name="reorder_level" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="addDescription">Description</label>
                        <textarea class="form-control" id="addDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="addIsActive" name="is_active" checked>
                        <label class="form-check-label" for="addIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Drug</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Drug Modal -->
<div class="modal fade" id="editDrugModal" tabindex="-1" role="dialog" aria-labelledby="editDrugModalLabel" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDrugModalLabel">Edit Drug</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=edit">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="editDrugId" name="drug_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editDrugName">Drug Name *</label>
                        <input type="text" class="form-control" id="editDrugName" name="drug_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editGenericName">Generic Name</label>
                        <input type="text" class="form-control" id="editGenericName" name="generic_name">
                    </div>
                    <div class="form-group">
                        <label for="editCategory">Category</label>
                        <select class="form-control" id="editCategory" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editUnit">Unit of Measure *</label>
                        <input type="text" class="form-control" id="editUnit" name="unit_of_measure" required>
                    </div>
                    <div class="form-group">
                        <label for="editReorderLevel">Reorder Level *</label>
                        <input type="number" class="form-control" id="editReorderLevel" name="reorder_level" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group form-check">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true" style="z-index: 1052;">
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
                <input type="hidden" id="deleteDrugId" name="drug_id">
                <div class="modal-body">
                    <p>Are you sure you want to deactivate <strong id="deleteDrugName"></strong>?</p>
                    <p class="text-danger">This drug will no longer be available for selection in new transactions.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>