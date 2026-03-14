<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getPDO();
$action = $_GET['action'] ?? '';

if ($action === 'export') {
    $search   = trim($_GET['search'] ?? '');
    $type     = $_GET['type'] ?? '';
    $wh       = intval($_GET['warehouse'] ?? 0);
    $prod     = intval($_GET['product'] ?? 0);
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to'] ?? '';

    $where = ['1=1']; $params = [];
    if ($search) { $where[] = '(mh.reference_number LIKE ? OR p.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($type)     { $where[] = 'mh.move_type=?'; $params[] = $type; }
    if ($wh)       { $where[] = 'mh.warehouse_id=?'; $params[] = $wh; }
    if ($prod)     { $where[] = 'mh.product_id=?'; $params[] = $prod; }
    if ($dateFrom) { $where[] = 'DATE(mh.created_at)>=?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where[] = 'DATE(mh.created_at)<=?'; $params[] = $dateTo; }
    $whereStr = implode(' AND ', $where);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="move_history_' . date('Y-m-d') . '.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Date','Type','Reference','Product','SKU','Warehouse','Change','Stock After']);
    $stmt = $pdo->prepare("SELECT mh.created_at,mh.move_type,mh.reference_number,p.name as product_name,p.sku,w.name as warehouse_name,mh.quantity_change,mh.quantity_after FROM move_history mh JOIN products p ON mh.product_id=p.id JOIN warehouses w ON mh.warehouse_id=w.id WHERE $whereStr ORDER BY mh.created_at DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        fputcsv($fp, [$row['created_at'],$row['move_type'],$row['reference_number'],$row['product_name'],$row['sku'],$row['warehouse_name'],$row['quantity_change'],$row['quantity_after']]);
    }
    fclose($fp);
    exit;
}
