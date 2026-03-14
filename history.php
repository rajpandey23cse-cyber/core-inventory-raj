<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Move History';
$pdo = getPDO();

$search      = trim($_GET['search'] ?? '');
$filterType  = $_GET['type'] ?? '';
$filterWH    = intval($_GET['warehouse'] ?? 0);
$filterProd  = intval($_GET['product'] ?? 0);
$dateFrom    = $_GET['date_from'] ?? '';
$dateTo      = $_GET['date_to'] ?? '';
$page        = max(1, intval($_GET['p'] ?? 1));
$perPage     = 20;

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(mh.reference_number LIKE ? OR p.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterType) { $where[] = 'mh.move_type=?'; $params[] = $filterType; }
if ($filterWH)   { $where[] = 'mh.warehouse_id=?'; $params[] = $filterWH; }
if ($filterProd) { $where[] = 'mh.product_id=?'; $params[] = $filterProd; }
if ($dateFrom)   { $where[] = 'DATE(mh.created_at)>=?'; $params[] = $dateFrom; }
if ($dateTo)     { $where[] = 'DATE(mh.created_at)<=?'; $params[] = $dateTo; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM move_history mh JOIN products p ON mh.product_id=p.id JOIN warehouses w ON mh.warehouse_id=w.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total/$perPage); $offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT mh.*,p.name as product_name,p.sku,w.name as warehouse_name,u.name as created_by_name FROM move_history mh JOIN products p ON mh.product_id=p.id JOIN warehouses w ON mh.warehouse_id=w.id LEFT JOIN users u ON mh.created_by=u.id WHERE $whereStr ORDER BY mh.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $history = $stmt->fetchAll();

$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

$moveTypes = ['receipt'=>'Receipt','delivery'=>'Delivery','transfer_in'=>'Transfer In','transfer_out'=>'Transfer Out','adjustment'=>'Adjustment'];

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-clock-rotate-left text-primary"></i> Move History</h2>
  <a href="/inventory/api/history.php?action=export&<?= http_build_query(['search'=>$search,'type'=>$filterType,'warehouse'=>$filterWH,'product'=>$filterProd,'date_from'=>$dateFrom,'date_to'=>$dateTo]) ?>" class="btn btn-outline btn-sm"><i class="fa fa-download"></i> Export CSV</a>
</div>

<!-- Filters -->
<div class="filters-bar" style="flex-wrap:wrap">
  <form method="GET" style="display:contents">
    <div class="search-box"><i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search..." value="<?=htmlspecialchars($search)?>">
    </div>
    <select name="type" class="form-control">
      <option value="">All Types</option>
      <?php foreach ($moveTypes as $k=>$v): ?>
      <option value="<?=$k?>" <?=$filterType===$k?'selected':''?>><?=$v?></option>
      <?php endforeach; ?>
    </select>
    <select name="warehouse" class="form-control">
      <option value="">All Warehouses</option>
      <?php foreach ($warehouses as $w): ?>
      <option value="<?=$w['id']?>" <?=$filterWH==$w['id']?'selected':''?>><?=htmlspecialchars($w['name'])?></option>
      <?php endforeach; ?>
    </select>
    <select name="product" class="form-control">
      <option value="">All Products</option>
      <?php foreach ($products as $p): ?>
      <option value="<?=$p['id']?>" <?=$filterProd==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($dateFrom)?>" style="max-width:150px" placeholder="From date">
    <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($dateTo)?>" style="max-width:150px" placeholder="To date">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/history.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Move History <span class="text-muted">(<?=number_format($total)?> records)</span></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Reference</th>
          <th>Product</th>
          <th>Warehouse</th>
          <th>Change</th>
          <th>Stock After</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($history): foreach ($history as $i => $h): ?>
        <?php
        $typeColors = ['receipt'=>'confirmed','delivery'=>'danger','transfer_in'=>'confirmed','transfer_out'=>'warning','adjustment'=>'warning'];
        $cls = $typeColors[$h['move_type']] ?? 'draft';
        ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td style="white-space:nowrap">
            <strong><?=date('M d, Y', strtotime($h['created_at']))?></strong><br>
            <small class="text-muted"><?=date('H:i:s', strtotime($h['created_at']))?></small>
          </td>
          <td><span class="badge badge-<?=$cls?>"><?=$moveTypes[$h['move_type']]??$h['move_type']?></span></td>
          <td><code><?=htmlspecialchars($h['reference_number']??'—')?></code></td>
          <td>
            <strong><?=htmlspecialchars($h['product_name'])?></strong><br>
            <small class="text-muted"><?=htmlspecialchars($h['sku'])?></small>
          </td>
          <td><?=htmlspecialchars($h['warehouse_name'])?></td>
          <td>
            <?php if ($h['quantity_change'] > 0): ?>
              <span class="text-success fw-bold">+<?=number_format($h['quantity_change'])?></span>
            <?php else: ?>
              <span class="text-danger fw-bold"><?=number_format($h['quantity_change'])?></span>
            <?php endif; ?>
          </td>
          <td><strong><?=number_format($h['quantity_after'])?></strong></td>
          <td class="text-muted"><?=htmlspecialchars($h['created_by_name']??'—')?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fa fa-clock-rotate-left"></i><p>No history found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?p=<?=$i?>&<?=http_build_query(['search'=>$search,'type'=>$filterType,'warehouse'=>$filterWH,'product'=>$filterProd,'date_from'=>$dateFrom,'date_to'=>$dateTo])?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
