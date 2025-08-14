<?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = db();
$drugId = (int)($_GET['drug_id'] ?? 0);
$departmentId = (int)($_GET['department_id'] ?? 0);

if (!$drugId || !$departmentId) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT quantity_in_stock FROM inventory WHERE drug_id = ? AND department_id = ?");
    $stmt->execute([$drugId, $departmentId]);
    $stock = $stmt->fetchColumn();
    
    echo json_encode([
        'stock' => $stock !== false ? (int)$stock : 0
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}