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

// Handle form submissions
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

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
                
                // Update inventory
                $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = ? WHERE inventory_id = ?");
                $stmt->execute([$newQty, $inventoryId]);
                
                // Record transaction
                $stmt = $db->prepare("INSERT INTO transactions 
                    (drug_id, department_id, transaction_type, quantity, notes, created_by)
                    SELECT drug_id, department_id, 'adjustment', ?, ?, ?
                    FROM inventory WHERE inventory_id = ?");
                $stmt->execute([abs($adjustment), $notes, $currentUser['user_id'], $inventoryId]);
                
                $message = 'Inventory adjusted successfully.';
                break;
                
            case 'transfer':
                $inventoryId = (int)($_POST['inventory_id'] ?? 0);
                $quantity = (int)($_POST['quantity'] ?? 0);
                $toDept = (int)($_POST['to_department'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if ($inventoryId <= 0 || $toDept <= 0) {
                    throw new Exception('Invalid transfer parameters.');
                }
                
                if ($quantity <= 0) {
                    throw new Exception('Transfer quantity must be positive.');
                }
                
                // Get inventory details
                $stmt = $db->prepare("SELECT drug_id, department_id, quantity_in_stock 
                                    FROM inventory WHERE inventory_id = ?");
                $stmt->execute([$inventoryId]);
                $inventory = $stmt->fetch();
                
                if (!$inventory) {
                    throw new Exception('Inventory item not found.');
                }
                
                if ($inventory['quantity_in_stock'] < $quantity) {
                    throw new Exception('Not enough stock for transfer.');
                }
                
                // Check if destination is different
                if ($inventory['department_id'] == $toDept) {
                    throw new Exception('Cannot transfer to the same department.');
                }
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Reduce source inventory
                    $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? 
                                        WHERE inventory_id = ?");
                    $stmt->execute([$quantity, $inventoryId]);
                    
                    // Add to destination inventory (or create if doesn't exist)
                    $stmt = $db->prepare("INSERT INTO inventory 
                                        (drug_id, department_id, quantity_in_stock)
                                        VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE 
                                        quantity_in_stock = quantity_in_stock + VALUES(quantity_in_stock)");
                    $stmt->execute([$inventory['drug_id'], $toDept, $quantity]);
                    
                    // Record transaction
                    $stmt = $db->prepare("INSERT INTO transactions 
                                        (drug_id, department_id, transaction_type, quantity, 
                                        transfer_to_department, notes, created_by)
                                        VALUES (?, ?, 'transfer', ?, ?, ?, ?)");
                    $stmt->execute([
                        $inventory['drug_id'],
                        $inventory['department_id'],
                        $quantity,
                        $toDept,
                        $notes,
                        $currentUser['user_id']
                    ]);
                    
                    $db->commit();
                    $message = 'Inventory transfer completed successfully.';
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
        }
    }

    // Get inventory data
    $inventoryItems = $db->query("
        SELECT i.inventory_id, d.drug_name, c.category_name, dept.department_name,
               i.quantity_in_stock, d.reorder_level, i.expiry_date,
               DATEDIFF(i.expiry_date, CURDATE()) as days_until_expiry,
               i.batch_number, i.last_restocked
        FROM inventory i
        JOIN drugs d ON i.drug_id = d.drug_id
        JOIN drug_categories c ON d.category_id = c.category_id
        JOIN departments dept ON i.department_id = dept.department_id
        ORDER BY dept.department_name, d.drug_name
    ")->fetchAll();

    // Get departments for transfer dropdown
    $departments = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

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
                                <?= count($inventoryItems) ?></div>
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
                                Expiring Soon (≤30 days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count(array_filter($inventoryItems, fn($item) => $item['days_until_expiry'] !== null && $item['days_until_expiry'] <= 30)) ?>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count($departments) ?></div>
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
                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#filterModal">
                    <i class="fas fa-filter"></i> Filter
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
                                   <?= $item['days_until_expiry'] !== null && $item['days_until_expiry'] <= 30 ? 'table-danger' : '' ?>">
                            <td><?= htmlspecialchars($item['drug_name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= htmlspecialchars($item['department_name']) ?></td>
                            <td><?= htmlspecialchars($item['batch_number']) ?></td>
                            <td>
                                <span class="<?= $item['quantity_in_stock'] <= $item['reorder_level'] ? 'font-weight-bold text-warning' : '' ?>">
                                    <?= $item['quantity_in_stock'] ?>
                                </span>
                                <?php if ($item['quantity_in_stock'] <= $item['reorder_level']): ?>
                                <small class="text-muted d-block">Reorder: <?= $item['reorder_level'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['expiry_date']): ?>
                                    <?= formatDate($item['expiry_date']) ?>
                                    <small class="d-block <?= $item['days_until_expiry'] <= 30 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                                        (<?= $item['days_until_expiry'] ?> days)
                                    </small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-info btn-sm" 
                                            onclick="showAdjustModal(<?= $item['inventory_id'] ?>, '<?= htmlspecialchars(addslashes($item['drug_name'])) ?>')"
                                            title="Adjust Stock">
                                        <i class="fas fa-adjust"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm" 
                                            onclick="showTransferModal(<?= $item['inventory_id'] ?>, '<?= htmlspecialchars(addslashes($item['drug_name'])) ?>', <?= $item['quantity_in_stock'] ?>)"
                                            title="Transfer Stock">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <a href="/pham/drugs.php?action=view&id=<?= $item['drug_id'] ?>" 
                                       class="btn btn-secondary btn-sm" title="View Drug">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
<div class="modal fade" id="adjustModal" tabindex="-1" role="dialog" aria-labelledby="adjustModalLabel" aria-hidden="true" style="z-index: 1050;">
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
                        <label for="adjustment">Adjustment Quantity</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <select class="custom-select" id="adjustmentType">
                                    <option value="1">Add</option>
                                    <option value="-1">Remove</option>
                                </select>
                            </div>
                            <input type="number" class="form-control" id="adjustment" name="adjustment" min="1" value="1" required>
                        </div>
                        <small class="form-text text-muted">Positive number to add/remove from stock</small>
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

<!-- Transfer Stock Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transferModalLabel">Transfer Stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=transfer">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="transferInventoryId" name="inventory_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="transferDrugName">Drug Name</label>
                        <input type="text" class="form-control" id="transferDrugName" readonly>
                    </div>
                    <div class="form-group">
                        <label for="transferQuantity">Quantity to Transfer</label>
                        <input type="number" class="form-control" id="transferQuantity" name="quantity" min="1" value="1" required>
                        <small class="form-text text-muted">Current stock: <span id="currentStock"></span></small>
                    </div>
                    <div class="form-group">
                        <label for="toDepartment">Destination Department</label>
                        <select class="form-control" id="toDepartment" name="to_department" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transferNotes">Notes</label>
                        <textarea class="form-control" id="transferNotes" name="notes" rows="2"></textarea>
                        <small class="form-text text-muted">Optional transfer notes</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Transfer Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Inventory Report</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="/pham/export_inventory.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="exportFormat">Format</label>
                        <select class="form-control" id="exportFormat" name="format" required>
                            <option value="csv">CSV (Excel)</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="exportType">Report Type</label>
                        <select class="form-control" id="exportType" name="type" required>
                            <option value="all">Full Inventory</option>
                            <option value="low">Low Stock Only</option>
                            <option value="expiring">Expiring Soon (≤30 days)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
