<?php
/**
 * Demo Data Seeder – Run once to populate sample products and stock
 * Access: /inventory/seed_demo.php
 * DELETE THIS FILE AFTER USE
 */
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

// Products
$demoProducts = [
    ['PRD-001','Laptop Dell XPS 15',1,'Piece','pcs',999.99,1299.99,5],
    ['PRD-002','Wireless Mouse',1,'Unit','pcs',12.50,24.99,20],
    ['PRD-003','Mechanical Keyboard',1,'Unit','pcs',45.00,89.99,15],
    ['PRD-004','USB-C Hub 7-port',1,'Unit','pcs',22.00,44.99,10],
    ['PRD-005','A4 Paper Ream 500sheets',2,'Ream','pcs',3.50,7.99,50],
    ['PRD-006','Ballpen Box Blue',2,'Box','box',1.20,2.99,100],
    ['PRD-007','Stapler Heavy Duty',2,'Unit','pcs',8.00,15.99,25],
    ['PRD-008','Office Chair Ergonomic',3,'Unit','pcs',150.00,299.99,8],
    ['PRD-009','Standing Desk 140cm',3,'Unit','pcs',220.00,449.99,3],
    ['PRD-010','Drill Machine 18V',4,'Unit','pcs',65.00,129.99,12],
    ['PRD-011','Screwdriver Set 32pcs',4,'Set','set',15.00,32.99,20],
    ['PRD-012','Safety Helmet',4,'Unit','pcs',8.00,18.99,30],
];

$added = 0;
foreach ($demoProducts as [$sku,$name,$cat,$unitName,$unit,$cost,$price,$reorder]) {
    $check = $pdo->prepare("SELECT id FROM products WHERE sku=?");
    $check->execute([$sku]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO products (sku,name,category_id,unit,cost_price,unit_price,reorder_level,status) VALUES (?,?,?,?,?,?,?,'active')")
            ->execute([$sku,$name,$cat,$unit,$cost,$price,$reorder]);
        $added++;
    }
}

// Stock for WH-001
$warehouses = $pdo->query("SELECT id FROM warehouses LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
$products   = $pdo->query("SELECT id FROM products")->fetchAll(PDO::FETCH_COLUMN);

$qty1 = [50,200,80,120,500,300,60,20,8,40,80,100];
$qty2 = [10,50,20,30,100,100,20,5,2,15,20,30];

foreach ($products as $i => $pid) {
    $q1 = $qty1[$i] ?? rand(10,100);
    $q2 = $qty2[$i] ?? rand(0,30);
    if ($warehouses[0]) $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")->execute([$pid,$warehouses[0],$q1]);
    if ($warehouses[1] ?? null) $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")->execute([$pid,$warehouses[1],$q2]);
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><link rel='stylesheet' href='/inventory/assets/css/main.css'><link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'></head><body style='display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0f172a'>";
echo "<div style='background:#fff;padding:40px;border-radius:16px;max-width:400px;text-align:center'>";
echo "<i class='fa fa-check-circle' style='font-size:3rem;color:#22c55e;margin-bottom:16px;display:block'></i>";
echo "<h2>Demo Data Seeded!</h2>";
echo "<p style='color:#64748b;margin:12px 0'>Added $added products with stock levels.</p>";
echo "<a href='/inventory/dashboard.php' class='btn btn-primary btn-block btn-lg' style='margin-top:16px'><i class='fa fa-gauge-high'></i> Go to Dashboard</a>";
echo "<p style='margin-top:16px;font-size:.8rem;color:#ef4444'>⚠ Delete seed_demo.php after use!</p>";
echo "</div></body></html>";
