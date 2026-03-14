<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$pdo = getPDO();
$action = $_GET['action'] ?? '';

if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $r = $pdo->prepare("SELECT r.*,s.name as supplier_name,w.name as warehouse_name FROM receipts r LEFT JOIN suppliers s ON r.supplier_id=s.id LEFT JOIN warehouses w ON r.warehouse_id=w.id WHERE r.id=?");
    $r->execute([$id]); $receipt = $r->fetch();
    $i = $pdo->prepare("SELECT ri.*,p.name as product_name,p.sku FROM receipt_items ri JOIN products p ON ri.product_id=p.id WHERE ri.receipt_id=?");
    $i->execute([$id]); $items = $i->fetchAll();
    echo json_encode(['receipt'=>$receipt,'items'=>$items]);
}
