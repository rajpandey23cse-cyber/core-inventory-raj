<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getPDO();
$action = $_GET['action'] ?? '';

if ($action === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['SKU','Name','Category','Unit','Cost Price','Selling Price','Stock','Status']);
    $rows = $pdo->query("SELECT p.sku,p.name,c.name as cat,p.unit,p.cost_price,p.unit_price,COALESCE(s.qty,0) as stock,p.status FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN (SELECT product_id,SUM(quantity) as qty FROM stock GROUP BY product_id) s ON p.id=s.product_id ORDER BY p.name")->fetchAll();
    foreach ($rows as $row) fputcsv($fp, [$row['sku'],$row['name'],$row['cat']??'',$row['unit'],$row['cost_price'],$row['unit_price'],$row['stock'],$row['status']]);
    fclose($fp);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Unknown action']);
