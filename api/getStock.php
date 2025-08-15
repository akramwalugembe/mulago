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

if (!$drugId) {
    echo json_encode(['error' => 'Missing drug ID parameter']);
    exit;
}

try {
    // Get total stock across all inventory
    $stmt = $db->prepare("SELECT SUM(quantity_in_stock) FROM inventory WHERE drug_id = ?");
    $stmt->execute([$drugId]);
    $stock = $stmt->fetchColumn();
    
    echo json_encode([
        'stock' => $stock !== false ? (int)$stock : 0
    ]);
} catch (Exception $e) {
    error_log('Stock check error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}