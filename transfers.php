<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Internal Transfers';
$pdo = getPDO();
$msg = ''; $msgType = 'success';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = intval($_POST['id'] ?? 0);
        $fromWH   = intval($_POST['from_warehouse_id'] ?? 0);
        $toWH     = intval($_POST['to_warehouse_id'] ?? 0);
        $status   = $_POST['status'] ?? 'draft';
        $notes    = trim($_POST['notes'] ?? '');
        $date     = $_POST['transfer_date'] ?? date('Y-m-d');
        $products = $_POST['product_id'] ?? [];
        $qtys     = $_POST['qty'] ?? [];

        if (!$fromWH || !$toWH) { $msg = 'Both warehouses are required.'; $msgType = 'danger'; }
        elseif ($fromWH === $toWH) { $msg = 'Source and destination cannot be the same.'; $msgType = 'danger'; }
        else {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare("UPDATE transfers SET from_warehouse_id=?,to_warehouse_id=?,status=?,notes=?,transfer_date=? WHERE id=?")
                        ->execute([$fromWH,$toWH,$status,$notes,$date,$id]);
                    $refNum = $pdo->query("SELECT transfer_number FROM transfers WHERE id=$id")->fetchColumn();
                    $pdo->prepare("DELETE FROM transfer_items WHERE transfer_id=?")->execute([$id]);
                } else {
                    $refNum = generateRef('TRF');
                    $pdo->prepare("INSERT INTO transfers (transfer_number,from_warehouse_id,to_warehouse_id,status,notes,transfer_date,created_by) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$refNum,$fromWH,$toWH,$status,$notes,$date,$user['id']]);
                    $id = $pdo->lastInsertId();
                }

                foreach ($products as $idx => $pid) {
                    $pid = intval($pid); if (!$pid) continue;
                    $qty = intval($qtys[$idx] ?? 0);
                    $pdo->prepare("INSERT INTO transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)")->execute([$id,$pid,$qty]);

                    if ($status === 'completed' && $qty > 0) {
                        // Deduct from source
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=GREATEST(0,quantity-VALUES(quantity))")
                            ->execute([$pid,$fromWH,$qty]);
                        $qa1 = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qa1->execute([$pid,$fromWH]); $q1 = $qa1->fetchColumn() ?: 0;
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_out',?,?,?,?)")
                            ->execute([$pid,$fromWH,$refNum,-$qty,$q1,$user['id']]);
                        // Add to destination
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                            ->execute([$pid,$toWH,$qty]);
                        $qa2 = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qa2->execute([$pid,$toWH]); $q2 = $qa2->fetchColumn() ?: 0;
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_in',?,?,?,?)")
                            ->execute([$pid,$toWH,$refNum,$qty,$q2,$user['id']]);
                    }
                }
                $pdo->commit();
                $msg = 'Transfer saved.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error: '.$e->getMessage(); $msgType = 'danger';
            }
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM transfers WHERE id=?")->execute([$id]);
        $msg = 'Transfer deleted.';
    }
    elseif ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['draft','in_transit','completed','cancelled'])) {
            // Apply stock movement when completing
            if ($newStatus === 'completed') {
                $t = $pdo->prepare("SELECT * FROM transfers WHERE id=?");
                $t->execute([$id]); $tr = $t->fetch();
                if ($tr && $tr['status'] !== 'completed') {
                    $items = $pdo->prepare("SELECT * FROM transfer_items WHERE transfer_id=?");
                    $items->execute([$id]); $rows = $items->fetchAll();
                    foreach ($rows as $row) {
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=GREATEST(0,quantity-VALUES(quantity))")
                            ->execute([$row['product_id'],$tr['from_warehouse_id'],$row['quantity']]);
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                            ->execute([$row['product_id'],$tr['to_warehouse_id'],$row['quantity']]);
                        $qa1=$pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qa1->execute([$row['product_id'],$tr['from_warehouse_id']]); $q1=$qa1->fetchColumn()?:0;
                        $qa2=$pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qa2->execute([$row['product_id'],$tr['to_warehouse_id']]); $q2=$qa2->fetchColumn()?:0;
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_out',?,?,?,?)")
                            ->execute([$row['product_id'],$tr['from_warehouse_id'],$tr['transfer_number'],-$row['quantity'],$q1,$user['id']]);
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_in',?,?,?,?)")
                            ->execute([$row['product_id'],$tr['to_warehouse_id'],$tr['transfer_number'],$row['quantity'],$q2,$user['id']]);
                    }
                }
            }
            $pdo->prepare("UPDATE transfers SET status=? WHERE id=?")->execute([$newStatus,$id]);
            $msg = 'Status updated.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1)); $perPage = 15;
$where = ['1=1']; $params = [];
if ($search) { $where[] = '(t.transfer_number LIKE ? OR wf.name LIKE ? OR wt.name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($filterStatus) { $where[] = 't.status=?'; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM transfers t LEFT JOIN warehouses wf ON t.from_warehouse_id=wf.id LEFT JOIN warehouses wt ON t.to_warehouse_id=wt.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total/$perPage); $offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT t.*,wf.name as from_wh,wt.name as to_wh,u.name as created_by_name FROM transfers t LEFT JOIN warehouses wf ON t.from_warehouse_id=wf.id LEFT JOIN warehouses wt ON t.to_warehouse_id=wt.id LEFT JOIN users u ON t.created_by=u.id WHERE $whereStr ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $transfers = $stmt->fetchAll();

$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id,sku,name,unit FROM products WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto"><i class="fa fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><i class="fa fa-right-left text-primary"></i> Internal Transfers</h2>
  <button class="btn btn-primary" onclick="openModal('transferModal');resetForm()"><i class="fa fa-plus"></i> New Transfer</button>
</div>

<div class="filters-bar">
  <form method="GET" style="display:contents">
    <div class="search-box"><i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search transfers..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <?php foreach(['draft','in_transit','completed','cancelled'] as $s): ?>
      <option value="<?=$s?>" <?=$filterStatus===$s?'selected':''?>><?=ucfirst(str_replace('_',' ',$s))?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/transfers.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Transfers (<?= number_format($total) ?>)</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Ref Number</th><th>From</th><th>To</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($transfers): foreach ($transfers as $i => $t): ?>
        <tr>
          <td><?=$offset+$i+1?></td>
          <td><strong><?=htmlspecialchars($t['transfer_number'])?></strong></td>
          <td><?=htmlspecialchars($t['from_wh'])?></td>
          <td><?=htmlspecialchars($t['to_wh'])?></td>
          <td><?=$t['transfer_date']?date('M d, Y',strtotime($t['transfer_date'])):'—'?></td>
          <td><span class="badge badge-<?=$t['status']?>"><?=ucfirst(str_replace('_',' ',$t['status']))?></span></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="viewTransfer(<?=$t['id']?>)"><i class="fa fa-eye"></i></button>
              <?php if (!in_array($t['status'],['completed','cancelled'])): ?>
              <select class="form-control" style="padding:4px 8px;font-size:.8rem;border-radius:6px" onchange="updateStatus(<?=$t['id']?>,this.value)">
                <?php foreach(['draft','in_transit','completed','cancelled'] as $s): ?>
                <option value="<?=$s?>" <?=$t['status']===$s?'selected':''?>><?=ucfirst(str_replace('_',' ',$s))?></option>
                <?php endforeach; ?>
              </select>
              <?php endif; ?>
              <?php if ($t['status']==='draft'): ?>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete transfer?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=$t['id']?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-right-left"></i><p>No transfers found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Transfer Modal -->
<div class="modal-overlay" id="transferModal">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title">New Internal Transfer</h3>
      <button class="modal-close" onclick="closeModal('transferModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">From Warehouse *</label>
            <select name="from_warehouse_id" class="form-control" required>
              <option value="">-- Source --</option>
              <?php foreach ($warehouses as $w): ?>
              <option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">To Warehouse *</label>
            <select name="to_warehouse_id" class="form-control" required>
              <option value="">-- Destination --</option>
              <?php foreach ($warehouses as $w): ?>
              <option value="<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Transfer Date</label>
            <input type="date" name="transfer_date" class="form-control" value="<?=date('Y-m-d')?>">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="draft">Draft</option>
              <option value="in_transit">In Transit</option>
              <option value="completed">Completed (moves stock)</option>
            </select>
          </div>
          <div class="form-group span-2">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <h4 style="margin:16px 0 10px;font-size:.9rem;font-weight:700">Items</h4>
        <div class="items-table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Quantity</th><th></th></tr></thead>
            <tbody id="tItemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addTRow()"><i class="fa fa-plus"></i> Add Item</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('transferModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Transfer</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Transfer Details</h3>
      <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="viewContent"><p>Loading...</p></div>
  </div>
</div>

<form id="statusForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="update_status">
  <input type="hidden" name="id" id="sId">
  <input type="hidden" name="new_status" id="sVal">
</form>

<script>
const products = <?= json_encode($products) ?>;
function resetForm(){ document.getElementById('tItemsBody').innerHTML=''; addTRow(); }
function addTRow(){
  const tbody=document.getElementById('tItemsBody');
  const opts=products.map(p=>`<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><select name="product_id[]" class="form-control" required><option value="">-- Product --</option>${opts}</select></td><td><input type="number" name="qty[]" class="form-control" min="1" value="1"></td><td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('tr').remove()"><i class="fa fa-times"></i></button></td>`;
  tbody.appendChild(tr);
}
function updateStatus(id,status){
  if(!confirm('Update status to '+status+'?'))return;
  document.getElementById('sId').value=id;
  document.getElementById('sVal').value=status;
  document.getElementById('statusForm').submit();
}
async function viewTransfer(id){
  openModal('viewModal');
  const data=await apiGet(`/inventory/api/transfers.php?action=view&id=${id}`);
  const t=data.transfer,items=data.items;
  let html=`<div class="form-grid">
    <div><strong>Ref:</strong> ${t.transfer_number}</div>
    <div><strong>From:</strong> ${t.from_wh}</div>
    <div><strong>To:</strong> ${t.to_wh}</div>
    <div><strong>Date:</strong> ${t.transfer_date||'—'}</div>
    <div><strong>Status:</strong> <span class="badge badge-${t.status}">${t.status}</span></div>
    <div><strong>Notes:</strong> ${t.notes||'—'}</div>
  </div>
  <h4 style="margin:16px 0 10px">Items</h4>
  <div class="items-table-wrap"><table><thead><tr><th>Product</th><th>SKU</th><th>Quantity</th></tr></thead><tbody>
  ${items.map(i=>`<tr><td>${i.product_name}</td><td><code>${i.sku}</code></td><td>${i.quantity}</td></tr>`).join('')}
  </tbody></table></div>`;
  document.getElementById('viewContent').innerHTML=html;
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
