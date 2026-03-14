
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$pdo = getPDO();
$action = $_GET['action'] ?? '';

if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $d = $pdo->prepare("SELECT d.*,c.name as customer_name,w.name as warehouse_name FROM deliveries d LEFT JOIN customers c ON d.customer_id=c.id LEFT JOIN warehouses w ON d.warehouse_id=w.id WHERE d.id=?");
    $d->execute([$id]); $delivery = $d->fetch();
    $i = $pdo->prepare("SELECT di.*,p.name as product_name,p.sku FROM delivery_items di JOIN products p ON di.product_id=p.id WHERE di.delivery_id=?");
    $i->execute([$id]); $items = $i->fetchAll();
    echo json_encode(['delivery'=>$delivery,'items'=>$items]);
}
