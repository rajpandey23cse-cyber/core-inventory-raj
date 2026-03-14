<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$pdo = getPDO();
$action = $_GET['action'] ?? '';

if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $t = $pdo->prepare("SELECT t.*,wf.name as from_wh,wt.name as to_wh FROM transfers t LEFT JOIN warehouses wf ON t.from_warehouse_id=wf.id LEFT JOIN warehouses wt ON t.to_warehouse_id=wt.id WHERE t.id=?");
    $t->execute([$id]); $transfer = $t->fetch();
    $i = $pdo->prepare("SELECT ti.*,p.name as product_name,p.sku FROM transfer_items ti JOIN products p ON ti.product_id=p.id WHERE ti.transfer_id=?");
    $i->execute([$id]); $items = $i->fetchAll();
    echo json_encode(['transfer'=>$transfer,'items'=>$items]);
}
