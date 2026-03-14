<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Dashboard';

$pdo = getPDO();

// KPIs
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalStock    = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock")->fetchColumn();
$lowStock      = $pdo->query("SELECT COUNT(DISTINCT p.id) FROM products p JOIN stock s ON p.id=s.product_id WHERE s.quantity <= p.reorder_level AND p.status='active'")->fetchColumn();
$receiptsToday = $pdo->query("SELECT COUNT(*) FROM receipts WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$deliveriesToday=$pdo->query("SELECT COUNT(*) FROM deliveries WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$pendingTransfers=$pdo->query("SELECT COUNT(*) FROM transfers WHERE status IN ('draft','in_transit')")->fetchColumn();

// Warehouses for filter
$warehouses = $pdo->query("SELECT id, name FROM warehouses ORDER BY name")->fetchAll();

// Stock per warehouse
$whStock = $pdo->query("SELECT w.name, COALESCE(SUM(s.quantity),0) as total FROM warehouses w LEFT JOIN stock s ON w.id=s.warehouse_id GROUP BY w.id ORDER BY total DESC")->fetchAll();

// Category breakdown
$catBreakdown = $pdo->query("SELECT c.name, COALESCE(SUM(s.quantity),0) as total FROM categories c LEFT JOIN products p ON p.category_id=c.id LEFT JOIN stock s ON s.product_id=p.id GROUP BY c.id ORDER BY total DESC LIMIT 5")->fetchAll();

// Low stock products
$lowStockItems = $pdo->query("SELECT p.name, p.sku, p.reorder_level, COALESCE(SUM(s.quantity),0) as qty FROM products p LEFT JOIN stock s ON p.id=s.product_id WHERE p.status='active' GROUP BY p.id HAVING qty <= p.reorder_level ORDER BY qty ASC LIMIT 10")->fetchAll();

// Recent activity
$recentActivity = $pdo->query("SELECT mh.move_type, mh.reference_number, mh.quantity_change, mh.created_at, p.name as product_name, w.name as warehouse_name FROM move_history mh JOIN products p ON mh.product_id=p.id JOIN warehouses w ON mh.warehouse_id=w.id ORDER BY mh.created_at DESC LIMIT 10")->fetchAll();

// Monthly receipts/deliveries (last 6 months)

// $monthly = $pdo->query("
//     SELECT m, SUM(r) as receipts, SUM(d) as deliveries FROM (
//         SELECT DATE_FORMAT(created_at,'%b') as m, COUNT(*) as r, 0 as d FROM receipts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m')
//         UNION ALL
//         SELECT DATE_FORMAT(created_at,'%b') as m, 0 as r, COUNT(*) as d FROM deliveries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m')
//     ) t GROUP BY m ORDER BY MIN(created_at)
// ")->fetchAll();


// Monthly receipts/deliveries (last 6 months) - CORRECTED
$monthly = $pdo->query("
    SELECT m, SUM(r) as receipts, SUM(d) as deliveries 
    FROM (
        SELECT 
            DATE_FORMAT(created_at,'%b') as m, 
            COUNT(*) as r, 
            0 as d, 
            MIN(created_at) as sort_date 
        FROM receipts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(created_at,'%b') as m, 
            0 as r, 
            COUNT(*) as d, 
            MIN(created_at) as sort_date 
        FROM deliveries 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ) t 
    GROUP BY m 
    ORDER BY MIN(sort_date)
")->fetchAll();


include __DIR__ . '/includes/header.php';
?>

<!-- KPI Cards -->
 <!-- inserted CSS  -->
<link rel="stylesheet" href="assets/css/main.css">

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon blue"><i class="fa fa-box"></i></div>
    <div>
      <div class="kpi-label">Total Products</div>
      <div class="kpi-value"><?= number_format($totalProducts) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green"><i class="fa fa-cubes"></i></div>
    <div>
      <div class="kpi-label">Total Stock (units)</div>
      <div class="kpi-value"><?= number_format($totalStock) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon red"><i class="fa fa-triangle-exclamation"></i></div>
    <div>
      <div class="kpi-label">Low Stock Items</div>
      <div class="kpi-value"><?= number_format($lowStock) ?></div>
      <?php if ($lowStock > 0): ?>
      <div class="kpi-change down"><i class="fa fa-arrow-down"></i> Needs reorder</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon cyan"><i class="fa fa-truck-ramp-box"></i></div>
    <div>
      <div class="kpi-label">Receipts Today</div>
      <div class="kpi-value"><?= number_format($receiptsToday) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon yellow"><i class="fa fa-truck-fast"></i></div>
    <div>
      <div class="kpi-label">Deliveries Today</div>
      <div class="kpi-value"><?= number_format($deliveriesToday) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon blue"><i class="fa fa-right-left"></i></div>
    <div>
      <div class="kpi-label">Pending Transfers</div>
      <div class="kpi-value"><?= number_format($pendingTransfers) ?></div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
  <div class="chart-card">
    <h3><i class="fa fa-chart-bar" style="color:var(--primary)"></i> Stock by Warehouse</h3>
    <?php
    $maxWH = $whStock ? max(array_column($whStock, 'total')) : 1;
    if ($maxWH == 0) $maxWH = 1;
    ?>
    <div class="simple-bar-chart">
      <?php foreach ($whStock as $row): ?>
      <div class="bar-row">
        <span class="bar-label"><?= htmlspecialchars($row['name']) ?></span>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= round($row['total']/$maxWH*100) ?>%"></div>
        </div>
        <span class="bar-val"><?= number_format($row['total']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$whStock): ?>
      <p class="text-muted" style="font-size:.85rem">No warehouse data yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="chart-card">
    <h3><i class="fa fa-chart-pie" style="color:var(--primary)"></i> Stock by Category</h3>
    <?php
    $colors = ['#2563eb','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6'];
    $catTotal = array_sum(array_column($catBreakdown,'total')) ?: 1;
    // SVG pie
    $offset = 0; $pieSegs = '';
    foreach ($catBreakdown as $i => $cat) {
        $pct = $cat['total'] / $catTotal;
        $large = $pct > 0.5 ? 1 : 0;
        $startX = cos(deg2rad($offset * 360 - 90)) * 70 + 80;
        $startY = sin(deg2rad($offset * 360 - 90)) * 70 + 80;
        $endX   = cos(deg2rad(($offset + $pct) * 360 - 90)) * 70 + 80;
        $endY   = sin(deg2rad(($offset + $pct) * 360 - 90)) * 70 + 80;
        if ($pct >= 1) { $endX = $startX - 0.001; $large = 1; }
        $col = $colors[$i % count($colors)];
        $pieSegs .= "<path d='M80,80 L{$startX},{$startY} A70,70 0 {$large},1 {$endX},{$endY} Z' fill='{$col}'/>";
        $offset += $pct;
    }
    ?>
    <div class="pie-chart-wrap">
      <svg class="pie-chart-svg" viewBox="0 0 160 160">
        <?php if (!$catBreakdown): ?>
        <circle cx="80" cy="80" r="70" fill="#e2e8f0"/>
        <?php else: echo $pieSegs; endif; ?>
        <circle cx="80" cy="80" r="35" fill="white"/>
      </svg>
      <div class="pie-legend" style="max-width:200px">
        <?php foreach ($catBreakdown as $i => $cat): ?>
        <div class="pie-legend-item">
          <div class="pie-dot" style="background:<?= $colors[$i % count($colors)] ?>"></div>
          <span><?= htmlspecialchars($cat['name']) ?> (<?= number_format($cat['total']) ?>)</span>
        </div>
        <?php endforeach; ?>
        <?php if (!$catBreakdown): ?>
        <p class="text-muted">No category data yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;flex-wrap:wrap">
  <!-- Low Stock Alerts -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-triangle-exclamation text-warning"></i> Low Stock Alerts</span>
      <a href="/inventory/products.php?filter=low" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>SKU</th><th>Stock</th><th>Min Level</th></tr></thead>
        <tbody>
          <?php if ($lowStockItems): foreach ($lowStockItems as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
            <td><span class="badge badge-warning"><?= $item['qty'] ?></span></td>
            <td><?= $item['reorder_level'] ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4"><div class="empty-state"><i class="fa fa-check-circle"></i><p>All stock levels are healthy!</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Move History -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-clock-rotate-left text-primary"></i> Recent Activity</span>
      <a href="/inventory/history.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Product</th><th>Qty</th><th>Date</th></tr></thead>
        <tbody>
          <?php if ($recentActivity): foreach ($recentActivity as $act): ?>
          <?php
          $typeMap = ['receipt'=>['in','success'],'delivery'=>['out','danger'],'transfer_in'=>['in','cyan'],'transfer_out'=>['out','cyan'],'adjustment'=>['adj','warning']];
          [$label,$cls] = $typeMap[$act['move_type']] ?? ['?','draft'];
          ?>
          <tr>
            <td><span class="badge badge-<?= $cls ?>"><?= ucfirst(str_replace('_',' ',$act['move_type'])) ?></span></td>
            <td title="<?= htmlspecialchars($act['product_name']) ?>"><?= htmlspecialchars(substr($act['product_name'],0,20)) ?><?= strlen($act['product_name'])>20?'...':''?></td>
            <td><?= $act['quantity_change'] > 0 ? '<span class="text-success">+' : '<span class="text-danger">' ?><?= $act['quantity_change'] ?></span></td>
            <td class="text-muted" style="font-size:.8rem"><?= date('M d, H:i', strtotime($act['created_at'])) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4"><div class="empty-state"><i class="fa fa-history"></i><p>No activity yet.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
