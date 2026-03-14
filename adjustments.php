<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Stock Adjustments';
$pdo = getPDO();
$msg = ''; $msgType = 'success';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = intval($_POST['id'] ?? 0);
        $warehouse = intval($_POST['warehouse_id'] ?? 0);
        $reason    = trim($_POST['reason'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $status    = $_POST['status'] ?? 'draft';
        $products  = $_POST['product_id'] ?? [];
        $qtyAfter  = $_POST['qty_after'] ?? [];

        if (!$warehouse) { $msg = 'Warehouse is required.'; $msgType = 'danger'; }
        else {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare("UPDATE adjustments SET warehouse_id=?,reason=?,notes=?,status=? WHERE id=?")
                        ->execute([$warehouse,$reason,$notes,$status,$id]);
                    $refNum = $pdo->query("SELECT adjustment_number FROM adjustments WHERE id=$id")->fetchColumn();
                    $pdo->prepare("DELETE FROM adjustment_items WHERE adjustment_id=?")->execute([$id]);
                } else {
                    $refNum = generateRef('ADJ');
                    $pdo->prepare("INSERT INTO adjustments (adjustment_number,warehouse_id,reason,notes,status,created_by) VALUES (?,?,?,?,?,?)")
                        ->execute([$refNum,$warehouse,$reason,$notes,$status,$user['id']]);
                    $id = $pdo->lastInsertId();
                }

                foreach ($products as $idx => $pid) {
                    $pid = intval($pid); if (!$pid) continue;
                    $qa = intval($qtyAfter[$idx] ?? 0);
                    // Get current qty before
                    $qb = $pdo->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?");
                    $qb->execute([$pid,$warehouse]); $qBefore = $qb->fetchColumn() ?: 0;

                    $pdo->prepare("INSERT INTO adjustment_items (adjustment_id,product_id,qty_before,qty_after) VALUES (?,?,?,?)")
                        ->execute([$id,$pid,$qBefore,$qa]);

                    if ($status === 'confirmed') {
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")
                            ->execute([$pid,$warehouse,$qa]);
                        $diff = $qa - $qBefore;
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,notes,created_by) VALUES (?,?,'adjustment',?,?,?,?,?)")
                            ->execute([$pid,$warehouse,$refNum,$diff,$qa,$reason,$user['id']]);
                    }
                }
                $pdo->commit();
                $msg = 'Adjustment saved.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error: '.$e->getMessage(); $msgType = 'danger';
            }
        }
    }
    elseif ($action === 'confirm') {
        $id = intval($_POST['id'] ?? 0);
        $adj = $pdo->prepare("SELECT * FROM adjustments WHERE id=?");
        $adj->execute([$id]); $a = $adj->fetch();
        if ($a && $a['status'] !== 'confirmed') {
            $items = $pdo->prepare("SELECT * FROM adjustment_items WHERE adjustment_id=?");
            $items->execute([$id]); $rows = $items->fetchAll();
            foreach ($rows as $row) {
                $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")
                    ->execute([$row['product_id'],$a['warehouse_id'],$row['qty_after']]);
                $diff = $row['qty_after'] - $row['qty_before'];
                $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,notes,created_by) VALUES (?,?,'adjustment',?,?,?,?,?)")
                    ->execute([$row['product_id'],$a['warehouse_id'],$a['adjustment_number'],$diff,$row['qty_after'],$a['reason'],$user['id']]);
            }
            $pdo->prepare("UPDATE adjustments SET status='confirmed' WHERE id=?")->execute([$id]);
            $msg = 'Adjustment confirmed and stock updated.';
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM adjustments WHERE id=?")->execute([$id]);
        $msg = 'Adjustment deleted.';
    }
}

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1)); $perPage = 15;
$where = ['1=1']; $params = [];
if ($search) { $where[] = '(a.adjustment_number LIKE ? OR w.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterStatus) { $where[] = 'a.status=?'; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM adjustments a LEFT JOIN warehouses w ON a.warehouse_id=w.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total/$perPage); $offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT a.*,w.name as warehouse_name,u.name as created_by_name FROM adjustments a LEFT JOIN warehouses w ON a.warehouse_id=w.id LEFT JOIN users u ON a.created_by=u.id WHERE $whereStr ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $adjustments = $stmt->fetchAll();

$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id,sku,name,unit FROM products WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto"><i class="fa fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><i class="fa fa-sliders text-primary"></i> Stock Adjustments</h2>
  <button class="btn btn-primary" onclick="openModal('adjModal');resetForm()"><i class="fa fa-plus"></i> New Adjustment</button>
</div>

<div class="filters-bar">
  <form method="GET" style="display:contents">
    <div class="search-box"><i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search adjustments..." value="<?=htmlspecialchars($search)?>">
    </div>
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <option value="draft" <?=$filterStatus==='draft'?'selected':''?>>Draft</option>
      <option value="confirmed" <?=$filterStatus==='confirmed'?'selected':''?>>Confirmed</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/adjustments.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Adjustments (<?=number_format($total)?>)</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Ref Number</th><th>Warehouse</th><th>Reason</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($adjustments): foreach ($adjustments as $i => $a): ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td><strong><?=htmlspecialchars($a['adjustment_number'])?></strong></td>
          <td><?=htmlspecialchars($a['warehouse_name'])?></td>
          <td><?=htmlspecialchars($a['reason']??'—')?></td>
          <td><span class="badge badge-<?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
          <td class="text-muted"><?=date('M d, Y',strtotime($a['created_at']))?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="viewAdj(<?=$a['id']?>)"><i class="fa fa-eye"></i></button>
              <?php if ($a['status']==='draft'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="id" value="<?=$a['id']?>">
                <button type="submit" class="btn btn-sm btn-success" title="Confirm & apply"><i class="fa fa-check"></i> Confirm</button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete adjustment?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=$a['id']?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-sliders"></i><p>No adjustments found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="adjModal">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title">New Stock Adjustment</h3>
      <button class="modal-close" onclick="closeModal('adjModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Warehouse *</label>
            <select name="warehouse_id" id="adjWarehouse" class="form-control" required onchange="loadStockLevels()">
              <option value="">-- Select Warehouse --</option>
              <?php foreach ($warehouses as $w): ?>
              <option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Damaged goods, Count discrepancy...">
          </div>
          <div class="form-group span-2">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <h4 style="margin:16px 0 10px;font-size:.9rem;font-weight:700">Items to Adjust</h4>
        <div class="items-table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Current Stock</th><th>New Quantity</th><th>Difference</th><th></th></tr></thead>
            <tbody id="adjItemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addAdjRow()"><i class="fa fa-plus"></i> Add Item</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('adjModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save as Draft</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Adjustment Details</h3>
      <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="viewContent"><p>Loading...</p></div>
  </div>
</div>

<script>
const products = <?= json_encode($products) ?>;
let stockLevels = {};

function resetForm(){ document.getElementById('adjItemsBody').innerHTML=''; addAdjRow(); }

async function loadStockLevels() {
  const wid = document.getElementById('adjWarehouse').value;
  if (!wid) return;
  const data = await apiGet(`/inventory/api/adjustments.php?action=stock&warehouse_id=${wid}`);
  stockLevels = {};
  data.forEach(s => stockLevels[s.product_id] = s.quantity);
  // Update existing rows
  document.querySelectorAll('#adjItemsBody tr').forEach(row => {
    const sel = row.querySelector('[name="product_id[]"]');
    if (sel && sel.value) updateCurrentStock(sel);
  });
}

function addAdjRow(){
  const tbody=document.getElementById('adjItemsBody');
  const opts=products.map(p=>`<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td><select name="product_id[]" class="form-control" required onchange="updateCurrentStock(this)"><option value="">-- Product --</option>${opts}</select></td>
    <td class="curr-stock text-muted">—</td>
    <td><input type="number" name="qty_after[]" class="form-control" min="0" value="0" oninput="updateDiff(this)"></td>
    <td class="diff-col">—</td>
    <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('tr').remove()"><i class="fa fa-times"></i></button></td>`;
  tbody.appendChild(tr);
}

function updateCurrentStock(sel){
  const pid=sel.value;
  const row=sel.closest('tr');
  const curr=stockLevels[pid]||0;
  row.querySelector('.curr-stock').textContent=curr;
  row.querySelector('[name="qty_after[]"]').value=curr;
  updateDiff(row.querySelector('[name="qty_after[]"]'));
}

function updateDiff(input){
  const row=input.closest('tr');
  const curr=parseFloat(row.querySelector('.curr-stock').textContent)||0;
  const after=parseFloat(input.value)||0;
  const diff=after-curr;
  const diffEl=row.querySelector('.diff-col');
  if(diff>0) diffEl.innerHTML=`<span class="text-success">+${diff}</span>`;
  else if(diff<0) diffEl.innerHTML=`<span class="text-danger">${diff}</span>`;
  else diffEl.textContent='0';
}

async function viewAdj(id){
  openModal('viewModal');
  const data=await apiGet(`/inventory/api/adjustments.php?action=view&id=${id}`);
  const a=data.adjustment,items=data.items;
  let html=`<div class="form-grid">
    <div><strong>Ref:</strong> ${a.adjustment_number}</div>
    <div><strong>Warehouse:</strong> ${a.warehouse_name}</div>
    <div><strong>Reason:</strong> ${a.reason||'—'}</div>
    <div><strong>Status:</strong> <span class="badge badge-${a.status}">${a.status}</span></div>
    <div class="span-2"><strong>Notes:</strong> ${a.notes||'—'}</div>
  </div>
  <h4 style="margin:16px 0 10px">Items</h4>
  <div class="items-table-wrap"><table><thead><tr><th>Product</th><th>Before</th><th>After</th><th>Difference</th></tr></thead><tbody>
  ${items.map(i=>{const d=i.qty_after-i.qty_before;return`<tr><td>${i.product_name}</td><td>${i.qty_before}</td><td>${i.qty_after}</td><td>${d>0?`<span class="text-success">+${d}</span>`:d<0?`<span class="text-danger">${d}</span>`:'0'}</td></tr>`}).join('')}
  </tbody></table></div>`;
  document.getElementById('viewContent').innerHTML=html;
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
