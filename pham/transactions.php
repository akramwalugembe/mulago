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
                    $transactionType = in_array($_POST['transaction_type'], ['issue', 'return', 'adjustment', 'transfer']) 
                        ? $_POST['transaction_type'] 
                        : 'issue';
                    $quantity = (int)($_POST['quantity'] ?? 0);
                    $referenceNumber = sanitizeInput($_POST['reference_number'] ?? '');
                    $notes = sanitizeInput($_POST['notes'] ?? '');
                    $transferToDept = ($transactionType === 'transfer') ? (int)($_POST['transfer_to_department'] ?? 0) : null;

                    // Validate
                    if ($drugId <= 0 || $departmentId <= 0 || $quantity <= 0) {
                        throw new Exception('Drug, department and valid quantity are required.');
                    }

                    if ($transactionType === 'transfer' && $transferToDept <= 0) {
                        throw new Exception('Destination department is required for transfers.');
                    }

                    // Begin transaction
                    $db->beginTransaction();

                    try {
                        // For transfers, we need to handle both source and destination
                        if ($transactionType === 'transfer') {
                            // Check source inventory
                            $stmt = $db->prepare("SELECT quantity_in_stock FROM inventory 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$drugId, $departmentId]);
                            $currentQty = $stmt->fetchColumn();

                            if ($currentQty === false) {
                                throw new Exception('Drug not found in source department inventory.');
                            }

                            if ($currentQty < $quantity) {
                                throw new Exception('Not enough stock for transfer.');
                            }

                            // Update source inventory
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? 
                                                  WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$quantity, $drugId, $departmentId]);

                            // Update or create destination inventory
                            $stmt = $db->prepare("INSERT INTO inventory 
                                                (drug_id, department_id, quantity_in_stock)
                                                VALUES (?, ?, ?)
                                                ON DUPLICATE KEY UPDATE 
                                                quantity_in_stock = quantity_in_stock + VALUES(quantity_in_stock)");
                            $stmt->execute([$drugId, $transferToDept, $quantity]);
                        } 
                        // For other transaction types (issue, return, adjustment)
                        else {
                            $adjustment = ($transactionType === 'issue') ? -$quantity : $quantity;
                            
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$adjustment, $drugId, $departmentId]);

                            if ($stmt->rowCount() === 0) {
                                throw new Exception('Drug not found in department inventory.');
                            }
                        }

                        // Record the transaction
                        $stmt = $db->prepare("INSERT INTO transactions 
                                            (drug_id, department_id, transaction_type, quantity, 
                                            reference_number, notes, created_by, transfer_to_department)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $drugId,
                            $departmentId,
                            $transactionType,
                            $quantity,
                            $referenceNumber ?: null,
                            $notes ?: null,
                            $auth->getCurrentUser()['user_id'],
                            $transferToDept
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

                    // Validate
                    if ($transactionId <= 0) {
                        throw new Exception('Invalid transaction ID.');
                    }

                    // Get transaction details
                    $stmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
                    $stmt->execute([$transactionId]);
                    $transaction = $stmt->fetch();

                    if (!$transaction) {
                        throw new Exception('Transaction not found.');
                    }

                    // Begin transaction
                    $db->beginTransaction();

                    try {
                        // Reverse the transaction
                        if ($transaction['transaction_type'] === 'transfer') {
                            // Return quantity to source department
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$transaction['quantity'], $transaction['drug_id'], $transaction['department_id']]);

                            // Remove from destination department
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$transaction['quantity'], $transaction['drug_id'], $transaction['transfer_to_department']]);
                        } else {
                            $adjustment = ($transaction['transaction_type'] === 'issue') ? $transaction['quantity'] : -$transaction['quantity'];
                            
                            $stmt = $db->prepare("UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? 
                                                WHERE drug_id = ? AND department_id = ?");
                            $stmt->execute([$adjustment, $transaction['drug_id'], $transaction['department_id']]);
                        }

                        // Delete the transaction record
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

// Get all transactions with related data
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

// Get drugs and departments for dropdowns
$drugs = $db->query("SELECT drug_id, drug_name FROM drugs WHERE is_active = 1 ORDER BY drug_name")->fetchAll();
$departments = $db->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Transaction Management</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addTransactionModal">
            <i class="fas fa-plus"></i> Record Transaction
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

    <!-- Transactions Summary Cards -->
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

        <!-- Today's Transactions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Today's Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $db->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issues Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Issues (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $db->query("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'issue' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn() ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Returns Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Returns (Last 7 Days)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $db->query("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'return' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn() ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="transactionsTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Drug</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Reference</th>
                            <th>User</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><?= formatDateTime($txn['created_at']) ?></td>
                            <td><?= htmlspecialchars($txn['drug_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $txn['transaction_type'] === 'issue' ? 'danger' : 
                                    ($txn['transaction_type'] === 'return' ? 'success' : 
                                    ($txn['transaction_type'] === 'transfer' ? 'info' : 'warning')) 
                                ?>">
                                    <?= ucfirst($txn['transaction_type']) ?>
                                </span>
                            </td>
                            <td><?= $txn['quantity'] ?></td>
                            <td><?= htmlspecialchars($txn['from_department']) ?></td>
                            <td><?= htmlspecialchars($txn['to_department'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($txn['reference_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($txn['created_by_name']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete(<?= $txn['transaction_id'] ?>, '<?= htmlspecialchars(addslashes($txn['drug_name'])) ?>')">
                                    <i class="fas fa-trash-alt"></i>
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

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-labelledby="addTransactionModalLabel" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTransactionModalLabel">Record New Transaction</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=add">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="txnDrug">Drug *</label>
                                <select class="form-control" id="txnDrug" name="drug_id" required>
                                    <option value="">-- Select Drug --</option>
                                    <?php foreach ($drugs as $drug): ?>
                                    <option value="<?= $drug['drug_id'] ?>"><?= htmlspecialchars($drug['drug_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="txnDepartment">Department *</label>
                                <select class="form-control" id="txnDepartment" name="department_id" required>
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
                                <label for="txnType">Transaction Type *</label>
                                <select class="form-control" id="txnType" name="transaction_type" required>
                                    <option value="issue">Issue</option>
                                    <option value="return">Return</option>
                                    <option value="adjustment">Adjustment</option>
                                    <option value="transfer">Transfer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="txnQuantity">Quantity *</label>
                                <input type="number" class="form-control" id="txnQuantity" name="quantity" min="1" value="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="transferDeptRow" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="txnTransferTo">Transfer To Department *</label>
                                <select class="form-control" id="txnTransferTo" name="transfer_to_department">
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
                                <label for="txnReference">Reference Number</label>
                                <input type="text" class="form-control" id="txnReference" name="reference_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="txnNotes">Notes</label>
                                <input type="text" class="form-control" id="txnNotes" name="notes">
                            </div>
                        </div>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true" style="z-index: 1051;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Transaction Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="?action=delete">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="deleteTransactionId" name="transaction_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete this transaction?</p>
                    <p class="text-danger">This will reverse the inventory changes made by this transaction.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>