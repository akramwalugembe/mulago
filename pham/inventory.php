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

// Database connection
$db = db();

// Initialize variables
$inventoryItems = [];
$message = '';
$error = '';
$action = $_GET['action'] ?? '';

try {
    // Process inventory adjustments
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!verifyCsrfToken($csrfToken)) {
            throw new Exception('Invalid form submission.');
        }

        switch ($action) {
            case 'adjust':
                $inventoryId = (int)($_POST['inventory_id'] ?? 0);
                $adjustment = (int)($_POST['adjustment'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                $newExpiryDate = $_POST['new_expiry_date'] ?? null;

                if ($inventoryId <= 0) {
                    throw new Exception('Invalid inventory item.');
                }

                if ($adjustment === 0) {
                    throw new Exception('Adjustment quantity cannot be zero.');
                }

                // Get current quantity
                $stmt = $db->prepare("SELECT quantity_in_stock FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$inventoryId]);
                $currentQty = $stmt->fetchColumn();

                if ($currentQty === false) {
                    throw new Exception('Inventory item not found.');
                }

                $newQty = $currentQty + $adjustment;
                if ($newQty < 0) {
                    throw new Exception('Adjustment would result in negative stock.');
                }

                // Update inventory - include expiry date if provided
                if ($newExpiryDate) {
                    $stmt = $db->prepare("UPDATE inventory 
                            SET quantity_in_stock = ?, 
                                expiry_date = ?
                            WHERE inventory_id = ?");
                    $stmt->execute([$newQty, $newExpiryDate, $inventoryId]);
                } else {
                    $stmt = $db->prepare("UPDATE inventory 
                            SET quantity_in_stock = ? 
                            WHERE inventory_id = ?");
                    $stmt->execute([$newQty, $inventoryId]);
                }

                // Record transaction
                $transactionType = $adjustment > 0 ? 'stock_adjust_in' : 'stock_adjust_out';
                $stmt = $db->prepare("INSERT INTO transactions 
                    (drug_id, department_id, transaction_type, quantity, notes, created_by)
                    SELECT drug_id, department_id, ?, ?, ?, ?
                    FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$transactionType, abs($adjustment), $notes, $currentUser['user_id'], $inventoryId]);

                $message = 'Inventory adjusted successfully.';
                break;

            case 'remove_expiring':
                $inventoryId = (int)($_POST['inventory_id'] ?? 0);
                $quantity = (int)($_POST['quantity'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? 'Expired stock removal');

                if ($inventoryId <= 0) {
                    throw new Exception('Invalid inventory item.');
                }

                if ($quantity <= 0) {
                    throw new Exception('Quantity must be positive.');
                }

                // Get inventory details
                $stmt = $db->prepare("SELECT drug_id, department_id, quantity_in_stock FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$inventoryId]);
                $inventory = $stmt->fetch();

                if (!$inventory) {
                    throw new Exception('Inventory item not found.');
                }

                if ($inventory['quantity_in_stock'] < $quantity) {
                    throw new Exception('Not enough stock to remove.');
                }

                // Update inventory
                $newQty = $inventory['quantity_in_stock'] - $quantity;
                $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = ? WHERE inventory_id = ?");
                $stmt->execute([$newQty, $inventoryId]);

                // Record transaction
                $stmt = $db->prepare("INSERT INTO transactions 
                    (drug_id, department_id, transaction_type, quantity, notes, created_by)
                    VALUES (?, ?, 'stock_adjust_out', ?, ?, ?)");
                $stmt->execute([
                    $inventory['drug_id'],
                    $inventory['department_id'],
                    $quantity,
                    $notes,
                    $currentUser['user_id']
                ]);

                $message = 'Expiring stock removed successfully.';
                break;

            case 'restock':
                $drugId = (int)($_POST['drug_id'] ?? 0);
                $departmentId = (int)($_POST['department_id'] ?? 0);
                $quantity = (int)($_POST['quantity'] ?? 0);
                $batchNumber = sanitizeInput($_POST['batch_number'] ?? '');
                $expiryDate = $_POST['expiry_date'] ?? '';
                $supplierId = (int)($_POST['supplier_id'] ?? 0);
                $unitPrice = (float)($_POST['unit_price'] ?? 0);

                if ($drugId <= 0 || $departmentId <= 0) {
                    throw new Exception('Invalid drug or department.');
                }

                if ($quantity <= 0) {
                    throw new Exception('Quantity must be positive.');
                }

                if (empty($batchNumber)) {
                    throw new Exception('Batch number is required.');
                }

                if (empty($expiryDate)) {
                    throw new Exception('Expiry date is required.');
                }

                // Check if expiry date is at least 6 months from today
                $expiryDateTime = new DateTime($expiryDate);
                $sixMonthsFromNow = (new DateTime())->add(new DateInterval('P6M'));
                if ($expiryDateTime <= $sixMonthsFromNow) {
                    throw new Exception('Cannot add stock that expires in less than 6 months.');
                }

                if ($unitPrice <= 0) {
                    throw new Exception('Unit price must be positive.');
                }

                $totalPrice = $unitPrice * $quantity;

                $db->beginTransaction();

                try {

                    $stmt = $db->prepare("SELECT inventory_id FROM inventory 
                            WHERE drug_id = ? AND department_id = ? 
                            AND batch_number = ? AND expiry_date != ?");
                    $stmt->execute([$drugId, $departmentId, $batchNumber, $expiryDate]);

                    if ($existingItem = $stmt->fetch()) {
                        // Update existing batch with new expiry date
                        $stmt = $db->prepare("UPDATE inventory 
                                SET quantity_in_stock = quantity_in_stock + ?, 
                                    expiry_date = ?,
                                    last_restocked = CURDATE()
                                WHERE inventory_id = ?");
                        $stmt->execute([$quantity, $expiryDate, $existingItem['inventory_id']]);
                    }

                    $stmt = $db->prepare("SELECT inventory_id FROM inventory 
                                        WHERE drug_id = ? AND department_id = ? 
                                        AND batch_number = ? AND expiry_date = ?");
                    $stmt->execute([$drugId, $departmentId, $batchNumber, $expiryDate]);

                    if ($stmt->fetch()) {
                        // Update existing batch
                        $stmt = $db->prepare("UPDATE inventory 
                                            SET quantity_in_stock = quantity_in_stock + ?, 
                                                last_restocked = CURDATE()
                                            WHERE drug_id = ? AND department_id = ? 
                                            AND batch_number = ? AND expiry_date = ?");
                        $stmt->execute([$quantity, $drugId, $departmentId, $batchNumber, $expiryDate]);
                    } else {
                        // Create new batch
                        $stmt = $db->prepare("INSERT INTO inventory 
                                            (drug_id, department_id, quantity_in_stock, batch_number, expiry_date, last_restocked)
                                            VALUES (?, ?, ?, ?, ?, CURDATE())");
                        $stmt->execute([$drugId, $departmentId, $quantity, $batchNumber, $expiryDate]);
                    }

                    // Record purchase
                    $stmt = $db->prepare("INSERT INTO purchases 
                                        (drug_id, supplier_id, batch_number, quantity, unit_price, total_price, purchase_date, expiry_date, received_by)
                                        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)");
                    $stmt->execute([$drugId, $supplierId, $batchNumber, $quantity, $unitPrice, $totalPrice, $expiryDate, $currentUser['user_id']]);

                    // Record transaction
                    $stmt = $db->prepare("INSERT INTO transactions 
                                        (drug_id, department_id, transaction_type, quantity, notes, created_by)
                                        VALUES (?, ?, 'stock_receive', ?, 'Initial restock', ?)");
                    $stmt->execute([$drugId, $departmentId, $quantity, $currentUser['user_id']]);

                    $db->commit();
                    $message = 'Inventory restocked successfully.';
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
        }
    }

    // Get inventory data
    try {
        $inventoryItems = $db->query("
            SELECT i.inventory_id, d.drug_name, c.category_name, dept.department_name,
                   i.quantity_in_stock, d.reorder_level, i.expiry_date,
                   DATEDIFF(i.expiry_date, CURDATE()) as days_until_expiry,
                   i.batch_number, i.last_restocked, d.drug_id
            FROM inventory i
            JOIN drugs d ON i.drug_id = d.drug_id
            LEFT JOIN drug_categories c ON d.category_id = c.category_id
            JOIN departments dept ON i.department_id = dept.department_id
            ORDER BY dept.department_name, d.drug_name
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Inventory query error: " . $e->getMessage());
        $inventoryItems = [];
    }

    // Get drugs for restock dropdown
    $drugs = $db->query("SELECT drug_id, drug_name FROM drugs WHERE is_active = 1 ORDER BY drug_name")->fetchAll();

    // Get suppliers for restock dropdown
    $suppliers = $db->query("SELECT supplier_id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

    // Get departments for transfer dropdown
    try {
        $departments = $db->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
    } catch (PDOException $e) {
        error_log("Departments query error: " . $e->getMessage());
        $departments = [];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Generate CSRF token
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Inventory Management</h1>
        <div class="d-none d-sm-inline-block">
            <button class="btn btn-primary" data-toggle="modal" data-target="#exportModal">
                <i class="fas fa-download fa-sm"></i> Export Report
            </button>
        </div>
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

    <!-- Inventory Summary Cards -->
    <div class="row">
        <!-- Total Items Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Inventory Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count($inventoryItems ?? []) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Low Stock Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count(array_filter($inventoryItems, fn($item) => $item['quantity_in_stock'] <= $item['reorder_level'])) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Soon Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Expiring Soon (≤10 days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count(array_filter($inventoryItems, fn($item) => $item['days_until_expiry'] !== null && $item['days_until_expiry'] <= 10)) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Departments</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($departments ?? []) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clinic-medical fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Current Inventory</h6>
            <div>
                <button class="btn btn-sm btn-success mr-2" data-toggle="modal" data-target="#restockModal">
                    <i class="fas fa-plus"></i> Add Stock
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="inventoryTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Drug Name</th>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Batch #</th>
                            <th>Stock</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryItems as $item): ?>
                            <tr class="<?= $item['quantity_in_stock'] <= $item['reorder_level'] ? 'table-warning' : '' ?>
                                   <?= $item['days_until_expiry'] !== null && $item['days_until_expiry'] <= 10 ? 'table-danger' : '' ?>">
                                <td><?= htmlspecialchars($item['drug_name']) ?></td>
                                <td><?= htmlspecialchars($item['category_name']) ?></td>
                                <td><?= htmlspecialchars($item['department_name']) ?></td>
                                <td><?= htmlspecialchars($item['batch_number']) ?></td>
                                <td>
                                    <span class="<?= $item['quantity_in_stock'] <= $item['reorder_level'] ? 'font-weight-bold text-danger' : '' ?>">
                                        <?= $item['quantity_in_stock'] ?>
                                    </span>
                                    <?php if ($item['quantity_in_stock'] <= $item['reorder_level']): ?>
                                        <small class="text-muted d-block">Reorder: <?= $item['reorder_level'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['expiry_date']): ?>
                                        <?= formatDate($item['expiry_date']) ?>
                                        <small class="d-block <?= $item['days_until_expiry'] <= 10 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                                            (<?= $item['days_until_expiry'] ?> days)
                                        </small>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-info btn-sm"
                                            onclick="PharmacyModals.showAdjustModal(
        <?= $item['inventory_id'] ?>, 
        '<?= htmlspecialchars(addslashes($item['drug_name'])) ?>',
        '<?= isset($item['batch_number']) ? htmlspecialchars(addslashes($item['batch_number'])) : 'N/A' ?>',
        '<?= isset($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : 'N/A' ?>'
    )"
                                            title="Adjust Stock">
                                            <i class="fas fa-adjust"></i>
                                        </button>
                                        <?php if ($item['days_until_expiry'] <= 10): ?>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="PharmacyModals.showRemoveExpiringModal(<?= $item['inventory_id'] ?>, '<?= htmlspecialchars(addslashes($item['drug_name'])) ?>', <?= $item['quantity_in_stock'] ?>)"
                                                title="Remove Expiring Stock">
                                                <i class="fas fa-trash"></i>
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

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1" role="dialog" aria-labelledby="adjustModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustModalLabel">Adjust Stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=adjust">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="adjustInventoryId" name="inventory_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="adjustDrugName">Drug Name</label>
                        <input type="text" class="form-control" id="adjustDrugName" readonly>
                    </div>
                    <div class="form-group">
                        <label for="adjustBatchNumber">Batch Number</label>
                        <input type="text" class="form-control" id="adjustBatchNumber" name="batch_number" readonly>
                    </div>
                    <div class="form-group">
                        <label for="adjustExpiryDate">Current Expiry Date</label>
                        <input type="text" class="form-control" id="adjustExpiryDate" readonly>
                    </div>
                    <div class="form-group">
                        <label for="adjustment">Adjustment Quantity</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <select class="custom-select" id="adjustmentType" name="adjustment_type">
                                    <option value="1">Add</option>
                                    <option value="-1">Remove</option>
                                </select>
                            </div>
                            <input type="number" class="form-control" id="adjustment" name="adjustment" min="1" value="1" required>
                        </div>
                        <small class="form-text text-muted">Positive number to add/remove from stock</small>
                    </div>
                    <div class="form-group">
                        <label for="adjustNewExpiryDate">New Expiry Date (if adding stock)</label>
                        <input type="date" class="form-control" id="adjustNewExpiryDate" name="new_expiry_date">
                        <small class="form-text text-muted">Only required when adding new stock</small>
                    </div>
                    <div class="form-group">
                        <label for="adjustNotes">Notes</label>
                        <textarea class="form-control" id="adjustNotes" name="notes" rows="2" required></textarea>
                        <small class="form-text text-muted">Reason for adjustment</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Remove Expiring Stock Modal -->
<div class="modal fade" id="removeExpiringModal" tabindex="-1" role="dialog" aria-labelledby="removeExpiringModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeExpiringModalLabel">Remove Expiring Stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=remove_expiring">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="removeExpiringInventoryId" name="inventory_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="removeExpiringDrugName">Drug Name</label>
                        <input type="text" class="form-control" id="removeExpiringDrugName" readonly>
                    </div>
                    <div class="form-group">
                        <label for="removeExpiringQuantity">Quantity to Remove</label>
                        <input type="number" class="form-control" id="removeExpiringQuantity" name="quantity" min="1" value="1" required>
                        <small class="form-text text-muted">Current stock: <span id="currentExpiringStock"></span></small>
                    </div>
                    <div class="form-group">
                        <label for="removeExpiringNotes">Notes</label>
                        <textarea class="form-control" id="removeExpiringNotes" name="notes" rows="2">Removing expiring stock</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1" role="dialog" aria-labelledby="restockModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restockModalLabel">Restock Inventory</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=restock" id="restockForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="restockDrug">Drug *</label>
                        <select class="form-control" id="restockDrug" name="drug_id" required>
                            <option value="">Select Drug</option>
                            <?php foreach ($drugs as $drug): ?>
                                <option value="<?= $drug['drug_id'] ?>"><?= htmlspecialchars($drug['drug_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="restockDepartment">Department *</label>
                        <select class="form-control" id="restockDepartment" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="restockQuantity">Quantity *</label>
                        <input type="number" class="form-control" id="restockQuantity"
                            name="quantity" min="1" value="1" required>
                        <small class="form-text text-muted">Enter the quantity to add (minimum 1)</small>
                    </div>
                    <div class="form-group">
                        <label for="restockUnitPrice">Unit Price *</label>
                        <input type="number" class="form-control" id="restockUnitPrice"
                            name="unit_price" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="restockBatchNumber">Batch Number *</label>
                        <input type="text" class="form-control" id="restockBatchNumber" name="batch_number" required>
                    </div>
                    <div class="form-group">
                        <label for="restockExpiryDate">Expiry Date *</label>
                        <input type="date" class="form-control" id="restockExpiryDate" name="expiry_date" required>
                        <small class="form-text text-muted">Must be at least 6 months from today</small>
                    </div>
                    <div class="form-group">
                        <label for="restockSupplier">Supplier *</label>
                        <select class="form-control" id="restockSupplier" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>