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
                    $drugId = (int)($_POST['drug_id'] ?? 0);
                    $departmentId = (int)($_POST['department_id'] ?? 0);
                    $transactionType = $_POST['transaction_type'] === 'return' ? 'return' : 'sale';
                    $quantity = (int)($_POST['quantity'] ?? 0);
                    $referenceNumber = sanitizeInput($_POST['reference_number'] ?? '');
                    $notes = sanitizeInput($_POST['notes'] ?? '');

                    // Validate
                    if ($drugId <= 0 || $departmentId <= 0 || $quantity <= 0) {
                        throw new Exception('Drug, department and valid quantity are required.');
                    }

                    // Check for near-expiry drugs before sale
                    if ($transactionType === 'sale') {
                        // First check stock availability
                        $stmt = $db->prepare("SELECT quantity_in_stock FROM inventory 
                                            WHERE drug_id = ? AND department_id = ?");
                        $stmt->execute([$drugId, $departmentId]);
                        $stock = $stmt->fetchColumn();

                        if ($stock === false) {
                            throw new Exception('Not enough drugs in inventory');
                        }

                        if ($stock < $quantity) {
                            throw new Exception('Not enough stock available. Only ' . $stock . ' units in stock.');
                        }

                        // Then check expiry dates
                        $stmt = $db->prepare("SELECT expiry_date FROM inventory 
                                            WHERE drug_id = ? AND department_id = ?
                                            AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)
                                            LIMIT 1");
                        $stmt->execute([$drugId, $departmentId]);
                        if ($stmt->fetch()) {
                            throw new Exception('Cannot sell drugs expiring within 10 days');
                        }
                    }

                    $db->beginTransaction();

                    try {
                        $unitPrice = null;
                        $totalAmount = null;

                        if ($transactionType === 'sale') {

                            $stmt = $db->prepare("SELECT quantity_in_stock FROM inventory 
                        WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$drugId, $departmentId]);
                            $stock = $stmt->fetchColumn();

                            if ($stock === false) {
                                throw new Exception('This drug is not available in the selected department.');
                            }

                            if ($stock <= 0) {
                                throw new Exception('This drug is out of stock in the selected department.');
                            }

                            if ($stock < $quantity) {
                                throw new Exception('Insufficient stock. Only ' . $stock . ' units available in this department.');
                            }

                            // Get current price
                            $stmt = $db->prepare("SELECT unit_price FROM purchases 
                                                WHERE drug_id = ? 
                                                ORDER BY purchase_date DESC LIMIT 1");
                            $stmt->execute([$drugId]);
                            $unitPrice = $stmt->fetchColumn();

                            if (!$unitPrice || $unitPrice <= 0) {
                                throw new Exception('Valid unit price not found for this drug');
                            }

                            $totalAmount = $quantity * $unitPrice;

                            // Deduct from inventory
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$quantity, $drugId, $departmentId]);

                            if ($stmt->rowCount() === 0) {
                                throw new Exception('Drug not found in department inventory');
                            }
                        } else {
                            // For returns, get the original sale price to calculate refund
                            $stmt = $db->prepare("SELECT unit_price, total_amount FROM transactions 
                                                WHERE drug_id = ? AND transaction_type = 'sale'
                                                ORDER BY created_at DESC LIMIT 1");
                            $stmt->execute([$drugId]);
                            $sale = $stmt->fetch();

                            if ($sale) {
                                $unitPrice = $sale['unit_price'];
                                $totalAmount = -1 * abs($quantity * $unitPrice);
                            } else {
                                // Fallback to purchase price if no sale record found
                                $stmt = $db->prepare("SELECT unit_price FROM purchases 
                                                    WHERE drug_id = ? 
                                                    ORDER BY purchase_date DESC LIMIT 1");
                                $stmt->execute([$drugId]);
                                $unitPrice = $stmt->fetchColumn();
                                $totalAmount = $unitPrice ? -1 * abs($quantity * $unitPrice) : null;
                            }

                            // Add to inventory
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$quantity, $drugId, $departmentId]);
                        }

                        // Record transaction
                        $stmt = $db->prepare("INSERT INTO transactions 
                                            (drug_id, department_id, transaction_type, quantity, 
                                            reference_number, notes, created_by, unit_price, total_amount)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $drugId,
                            $departmentId,
                            $transactionType,
                            $quantity,
                            $referenceNumber ?: null,
                            $notes ?: null,
                            $auth->getCurrentUser()['user_id'],
                            $unitPrice,
                            $totalAmount
                        ]);

                        $db->commit();
                        $message = 'Transaction recorded successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                case 'delete':
                    $transactionId = (int)($_POST['transaction_id'] ?? 0);

                    if ($transactionId <= 0) {
                        throw new Exception('Invalid transaction ID.');
                    }

                    $stmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
                    $stmt->execute([$transactionId]);
                    $transaction = $stmt->fetch();

                    if (!$transaction) {
                        throw new Exception('Transaction not found.');
                    }

                    $db->beginTransaction();

                    try {
                        // Reverse inventory changes
                        $adjustment = ($transaction['transaction_type'] === 'sale')
                            ? $transaction['quantity']
                            : -$transaction['quantity'];

                        $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? 
                                            WHERE drug_id = ? AND department_id = ?");
                        $stmt->execute([
                            $adjustment,
                            $transaction['drug_id'],
                            $transaction['department_id']
                        ]);

                        // Delete the transaction
                        $stmt = $db->prepare("DELETE FROM transactions WHERE transaction_id = ?");
                        $stmt->execute([$transactionId]);

                        $db->commit();
                        $message = 'Transaction reversed and deleted successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get transactions with financial data
$transactions = $db->query("
    SELECT t.*, 
           d.drug_name,
           dept.department_name as from_department,
           dept2.department_name as to_department,
           u.full_name as created_by_name
    FROM transactions t
    JOIN drugs d ON t.drug_id = d.drug_id
    JOIN departments dept ON t.department_id = dept.department_id
    LEFT JOIN departments dept2 ON t.transfer_to_department = dept2.department_id
    JOIN users u ON t.created_by = u.user_id
    ORDER BY t.created_at DESC
")->fetchAll();

// Get financial summary data
$salesData = $db->query("
    SELECT 
        SUM(CASE WHEN transaction_type = 'sale' THEN total_amount ELSE 0 END) as total_sales,
        ABS(SUM(CASE WHEN transaction_type = 'return' THEN total_amount ELSE 0 END)) as total_returns,
        SUM(total_amount) as net_sales
    FROM transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch();

$drugs = $db->query("SELECT drug_id, drug_name FROM drugs WHERE is_active = 1 ORDER BY drug_name")->fetchAll();
$departments = $db->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Transaction Management</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addTransactionModal">
            <i class="fas fa-plus"></i> Record Transaction
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

    <div class="row">
        <!-- Total Transactions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= count($transactions) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Sales (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($salesData['total_sales'] ?? 0, 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <span class="text-gray-300" style="font-size: 1.5rem; font-weight: bold;">UGX</span>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Returns Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Returns (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format(abs($salesData['total_returns'] ?? 0), 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-undo fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Sales Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Net Sales (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($salesData['net_sales'] ?? 0, 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calculator fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Sales and Returns Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sales & Returns</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="salesTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Drug</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Amount</th>
                            <th>Department</th>
                            <th>Reference</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <?php if (in_array($txn['transaction_type'], ['sale', 'return'])): ?>
                                <tr>
                                    <td><?= formatDateTime($txn['created_at']) ?></td>
                                    <td><?= htmlspecialchars($txn['drug_name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $txn['transaction_type'] === 'sale' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($txn['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $txn['quantity'] ?></td>
                                    <td><?= isset($txn['unit_price']) ? number_format($txn['unit_price'], 0) : 'N/A' ?></td>
                                    <td class="<?= $txn['transaction_type'] === 'return' ? 'text-danger' : 'text-success' ?>">
                                        <?= isset($txn['total_amount']) ? number_format($txn['total_amount'], 0) : 'N/A' ?>
                                    </td>
                                    <td><?= htmlspecialchars($txn['from_department']) ?></td>
                                    <td><?= htmlspecialchars($txn['reference_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($txn['created_by_name']) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inventory Movements Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Inventory Movements</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="inventoryTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Drug</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>From Dept</th>
                            <th>To Dept</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <?php if (in_array($txn['transaction_type'], ['stock_receive', 'stock_adjust_in', 'stock_adjust_out', 'stock_transfer'])): ?>
                                <tr>
                                    <td><?= formatDateTime($txn['created_at']) ?></td>
                                    <td><?= htmlspecialchars($txn['drug_name']) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = [
                                            'stock_receive' => 'success',
                                            'stock_adjust_in' => 'warning',
                                            'stock_adjust_out' => 'danger',
                                            'stock_transfer' => 'secondary'
                                        ][$txn['transaction_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $badgeClass ?>">
                                            <?= str_replace('_', ' ', ucfirst($txn['transaction_type'])) ?>
                                        </span>
                                    </td>
                                    <td><?= $txn['quantity'] ?></td>
                                    <td><?= htmlspecialchars($txn['from_department']) ?></td>
                                    <td><?= htmlspecialchars($txn['to_department'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($txn['reference_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($txn['notes'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($txn['created_by_name']) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="?action=add">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Transaction Type *</label>
                            <select class="form-control" name="transaction_type" required>
                                <option value="sale">Sale to Customer</option>
                                <option value="return">Return to Inventory</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Drug *</label>
                                    <select class="form-control" name="drug_id" required>
                                        <option value="">-- Select Drug --</option>
                                        <?php foreach ($drugs as $drug): ?>
                                            <option value="<?= $drug['drug_id'] ?>"><?= htmlspecialchars($drug['drug_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department *</label>
                                    <select class="form-control" name="department_id" required>
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Quantity *</label>
                                    <input type="number" class="form-control" name="quantity" min="1" required>
                                    <small class="form-text text-muted stock-info">
                                        Available stock: <span class="available-stock">Select drug and department first</span>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference Number</label>
                                    <input type="text" class="form-control" name="reference_number">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>