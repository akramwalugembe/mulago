<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get current user and their permissions
$currentUser = $auth->getCurrentUser();
$isAdmin = ($currentUser['role'] === 'admin');
$isPharmacist = ($currentUser['role'] === 'pharmacist');

$db = db();

// Dashboard statistics
try {
    // Low stock items (below reorder level)
    $lowStock = $db->query("
        SELECT d.drug_id, d.drug_name, i.quantity_in_stock, d.reorder_level 
        FROM inventory i
        JOIN drugs d ON i.drug_id = d.drug_id
        WHERE i.quantity_in_stock <= d.reorder_level
        ORDER BY i.quantity_in_stock ASC
        LIMIT 5
    ")->fetchAll();

    // Recent transactions
    $recentTransactions = $db->query("
        SELECT t.transaction_id, d.drug_name, t.transaction_type, t.quantity, 
               t.created_at, u.full_name, dept.department_name
        FROM transactions t
        JOIN drugs d ON t.drug_id = d.drug_id
        JOIN users u ON t.created_by = u.user_id
        JOIN departments dept ON t.department_id = dept.department_id
        ORDER BY t.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Expiring soon (within 30 days)
    $expiringSoon = $db->query("
        SELECT i.inventory_id, d.drug_name, i.expiry_date, 
               DATEDIFF(i.expiry_date, CURDATE()) as days_remaining,
               dept.department_name
        FROM inventory i
        JOIN drugs d ON i.drug_id = d.drug_id
        JOIN departments dept ON i.department_id = dept.department_id
        WHERE i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY i.expiry_date ASC
        LIMIT 5
    ")->fetchAll();

    // Total drugs count
    $drugsCount = $db->query("SELECT COUNT(*) as count FROM drugs")->fetch()['count'];

    // Total inventory items
    $inventoryCount = $db->query("SELECT COUNT(*) as count FROM inventory")->fetch()['count'];

} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again later.";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pharmacy Dashboard</h1>
        <div class="d-none d-sm-inline-block">
            <span class="badge badge-primary">Last login: <?= formatDate($currentUser['last_login']) ?></span>
            <span class="badge badge-secondary ml-2">Role: <?= ucfirst($currentUser['role']) ?></span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Quick Stats Cards -->
    <div class="row">
        <!-- Drugs Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Drugs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $drugsCount ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-pills fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Inventory Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $inventoryCount ?></div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($lowStock) ?></div>
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
                                Expiring Soon</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($expiringSoon) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Low Stock Table -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-warning">Low Stock Items</h6>
                    <div class="dropdown no-arrow">
                        <a href="drugs.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Drug Name</th>
                                    <th>Current Stock</th>
                                    <th>Reorder Level</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStock as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['drug_name']) ?></td>
                                    <td class="<?= $item['quantity_in_stock'] < ($item['reorder_level'] / 2) ? 'text-danger font-weight-bold' : 'text-warning' ?>">
                                        <?= $item['quantity_in_stock'] ?>
                                    </td>
                                    <td><?= $item['reorder_level'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="showRestockModal(<?= $item['drug_id'] ?>, '<?= htmlspecialchars($item['drug_name']) ?>')">
                                            Restock
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($lowStock)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No low stock items</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Soon Table -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-danger">Expiring Soon</h6>
                    <div class="dropdown no-arrow">
                        <a href="inventory.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Drug Name</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiringSoon as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['drug_name']) ?></td>
                                    <td><?= formatDate($item['expiry_date']) ?></td>
                                    <td class="<?= $item['days_remaining'] < 7 ? 'text-danger font-weight-bold' : 'text-warning' ?>">
                                        <?= $item['days_remaining'] ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['department_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($expiringSoon)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No items expiring soon</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                    <div class="dropdown no-arrow">
                        <a href="/pham/inventory.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Drug</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Department</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $txn): ?>
                                <tr>
                                    <td><?= formatDateTime($txn['created_at']) ?></td>
                                    <td><?= htmlspecialchars($txn['drug_name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= getTransactionBadgeClass($txn['transaction_type']) ?>">
                                            <?= ucfirst($txn['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $txn['quantity'] ?></td>
                                    <td><?= htmlspecialchars($txn['department_name']) ?></td>
                                    <td><?= htmlspecialchars($txn['full_name']) ?></td>
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

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1" role="dialog" aria-labelledby="restockModalLabel" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restockModalLabel">Restock Drug</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="restockForm" method="post" action="/pham/inventory.php?action=restock">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" id="restockDrugId" name="drug_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="drugName">Drug Name</label>
                        <input type="text" class="form-control" id="drugName" readonly>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity to Add</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="supplier">Supplier</label>
                        <select class="form-control" id="supplier" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php
                            $suppliers = $db->query("SELECT supplier_id, supplier_name FROM suppliers WHERE is_active = 1")->fetchAll();
                            foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="batchNumber">Batch Number</label>
                        <input type="text" class="form-control" id="batchNumber" name="batch_number" required>
                    </div>
                    <div class="form-group">
                        <label for="expiryDate">Expiry Date</label>
                        <input type="date" class="form-control" id="expiryDate" name="expiry_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
