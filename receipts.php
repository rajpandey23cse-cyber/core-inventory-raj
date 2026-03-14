<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Receipts – Incoming Stock';
$pdo = getPDO();
$msg = ''; $msgType = 'success';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = intval($_POST['id'] ?? 0);
        $supplier   = intval($_POST['supplier_id'] ?? 0) ?: null;
        $warehouse  = intval($_POST['warehouse_id'] ?? 0);
        $status     = $_POST['status'] ?? 'draft';
        $notes      = trim($_POST['notes'] ?? '');
        $date       = $_POST['receipt_date'] ?? date('Y-m-d');
        $products   = $_POST['product_id'] ?? [];
        $qtyOrdered = $_POST['qty_ordered'] ?? [];
        $qtyReceived= $_POST['qty_received'] ?? [];
        $unitCosts  = $_POST['unit_cost'] ?? [];

        if (!$warehouse) { $msg = 'Warehouse is required.'; $msgType = 'danger'; }
        else {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare("UPDATE receipts SET supplier_id=?,warehouse_id=?,status=?,notes=?,receipt_date=? WHERE id=?")
                        ->execute([$supplier,$warehouse,$status,$notes,$date,$id]);
                    $refNum = $pdo->query("SELECT receipt_number FROM receipts WHERE id=$id")->fetchColumn();
                    $pdo->prepare("DELETE FROM receipt_items WHERE receipt_id=?")->execute([$id]);
                    // Reverse previous stock if confirmed
                } else {
                    $refNum = generateRef('RCP');
                    $pdo->prepare("INSERT INTO receipts (receipt_number,supplier_id,warehouse_id,status,notes,receipt_date,created_by) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$refNum,$supplier,$warehouse,$status,$notes,$date,$user['id']]);
                    $id = $pdo->lastInsertId();
                }

                foreach ($products as $idx => $pid) {
                    $pid = intval($pid);
                    if (!$pid) continue;
                    $qo = intval($qtyOrdered[$idx] ?? 0);
                    $qr = intval($qtyReceived[$idx] ?? 0);
                    $uc = floatval($unitCosts[$idx] ?? 0);
                    $pdo->prepare("INSERT INTO receipt_items (receipt_id,product_id,quantity_ordered,quantity_received,unit_cost) VALUES (?,?,?,?,?)")
                        ->execute([$id,$pid,$qo,$qr,$uc]);

                    if ($status === 'received' && $qr > 0) {
                        // Update stock
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                            ->execute([$pid,$warehouse,$qr]);
                        // Log history
                        $qAfter = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qAfter->execute([$pid,$warehouse]);
                        $qa = $qAfter->fetchColumn();
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'receipt',?,?,?,?)")
                            ->execute([$pid,$warehouse,$refNum,$qr,$qa,$user['id']]);
                    }
                }
                $pdo->commit();
                $msg = 'Receipt saved successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error: '.$e->getMessage(); $msgType = 'danger';
            }
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM receipts WHERE id=?")->execute([$id]);
        $msg = 'Receipt deleted.';
    }
    elseif ($action === 'confirm') {
        $id = intval($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'confirmed';
        $r = $pdo->prepare("SELECT * FROM receipts WHERE id=?");
        $r->execute([$id]); $receipt = $r->fetch();
        if ($receipt && $newStatus === 'received' && $receipt['status'] !== 'received') {
            $items = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
            $items->execute([$id]); $rows = $items->fetchAll();
            foreach ($rows as $row) {
                if ($row['quantity_received'] > 0) {
                    $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                        ->execute([$row['product_id'],$receipt['warehouse_id'],$row['quantity_received']]);
                    $qa = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                    $qa->execute([$row['product_id'],$receipt['warehouse_id']]);
                    $qaVal = $qa->fetchColumn();
                    $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'receipt',?,?,?,?)")
                        ->execute([$row['product_id'],$receipt['warehouse_id'],$receipt['receipt_number'],$row['quantity_received'],$qaVal,$user['id']]);
                }
            }
        }
        $pdo->prepare("UPDATE receipts SET status=? WHERE id=?")->execute([$newStatus,$id]);
        $msg = 'Receipt status updated.';
    }
}

// List
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1)); $perPage = 15;

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(r.receipt_number LIKE ? OR s.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterStatus) { $where[] = 'r.status=?'; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM receipts r LEFT JOIN suppliers s ON r.supplier_id=s.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total/$perPage); $offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT r.*, s.name as supplier_name, w.name as warehouse_name, u.name as created_by_name FROM receipts r LEFT JOIN suppliers s ON r.supplier_id=s.id LEFT JOIN warehouses w ON r.warehouse_id=w.id LEFT JOIN users u ON r.created_by=u.id WHERE $whereStr ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $receipts = $stmt->fetchAll();

$suppliers  = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id, sku, name, unit FROM products WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto"><i class="fa fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><i class="fa fa-truck-ramp-box text-primary"></i> Receipts</h2>
  <button class="btn btn-primary" onclick="openModal('receiptModal');resetReceiptForm()"><i class="fa fa-plus"></i> New Receipt</button>
</div>

<div class="filters-bar">
  <form method="GET" style="display:contents">
    <div class="search-box"><i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search receipts..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <option value="draft" <?= $filterStatus==='draft'?'selected':'' ?>>Draft</option>
      <option value="confirmed" <?= $filterStatus==='confirmed'?'selected':'' ?>>Confirmed</option>
      <option value="received" <?= $filterStatus==='received'?'selected':'' ?>>Received</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/receipts.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Receipts (<?= number_format($total) ?>)</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Ref Number</th><th>Supplier</th><th>Warehouse</th><th>Date</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($receipts): foreach ($receipts as $i => $r): ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><strong><?= htmlspecialchars($r['receipt_number']) ?></strong></td>
          <td><?= htmlspecialchars($r['supplier_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['warehouse_name']) ?></td>
          <td><?= $r['receipt_date'] ? date('M d, Y', strtotime($r['receipt_date'])) : '—' ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          <td class="text-muted"><?= htmlspecialchars($r['created_by_name'] ?? '—') ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="viewReceipt(<?= $r['id'] ?>)" title="View"><i class="fa fa-eye"></i></button>
              <?php if ($r['status'] === 'draft'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="new_status" value="confirmed">
                <button type="submit" class="btn btn-sm btn-primary" title="Confirm"><i class="fa fa-check"></i></button>
              </form>
              <?php endif; ?>
              <?php if ($r['status'] === 'confirmed'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="new_status" value="received">
                <button type="submit" class="btn btn-sm btn-success" title="Mark Received"><i class="fa fa-box-open"></i></button>
              </form>
              <?php endif; ?>
              <?php if ($r['status'] === 'draft'): ?>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete receipt?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fa fa-truck-ramp-box"></i><p>No receipts found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?p=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($filterStatus)?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Receipt Form Modal -->
<div class="modal-overlay" id="receiptModal">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title">New Receipt</h3>
      <button class="modal-close" onclick="closeModal('receiptModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">-- Select Supplier --</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Warehouse *</label>
            <select name="warehouse_id" class="form-control" required>
              <option value="">-- Select Warehouse --</option>
              <?php foreach ($warehouses as $w): ?>
              <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Receipt Date</label>
            <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="draft">Draft</option>
              <option value="confirmed">Confirmed</option>
              <option value="received">Received (updates stock)</option>
            </select>
          </div>
          <div class="form-group span-2">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <h4 style="margin:16px 0 10px;font-size:.9rem;font-weight:700">Items</h4>
        <div class="items-table-wrap">
          <table id="itemsTable">
            <thead><tr><th>Product</th><th>Qty Ordered</th><th>Qty Received</th><th>Unit Cost ($)</th><th></th></tr></thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline add-item-row" onclick="addItemRow()"><i class="fa fa-plus"></i> Add Item</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('receiptModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Receipt</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Receipt Details</h3>
      <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="viewContent"><p class="text-muted">Loading...</p></div>
  </div>
</div>

<script>
const productsData = <?= json_encode($products) ?>;

function resetReceiptForm() {
  document.getElementById('itemsBody').innerHTML = '';
  addItemRow();
}

function addItemRow() {
  const tbody = document.getElementById('itemsBody');
  const idx = tbody.children.length;
  const opts = productsData.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="product_id[]" class="form-control" required><option value="">-- Product --</option>${opts}</select></td>
    <td><input type="number" name="qty_ordered[]" class="form-control" min="0" value="1"></td>
    <td><input type="number" name="qty_received[]" class="form-control" min="0" value="1"></td>
    <td><input type="number" step="0.01" name="unit_cost[]" class="form-control" min="0" value="0"></td>
    <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('tr').remove()"><i class="fa fa-times"></i></button></td>
  `;
  tbody.appendChild(tr);
}

async function viewReceipt(id) {
  openModal('viewModal');
  const data = await apiGet(`/inventory/api/receipts.php?action=view&id=${id}`);
  const r = data.receipt;
  const items = data.items;
  let html = `
    <div class="form-grid">
      <div><strong>Ref:</strong> ${r.receipt_number}</div>
      <div><strong>Supplier:</strong> ${r.supplier_name||'—'}</div>
      <div><strong>Warehouse:</strong> ${r.warehouse_name}</div>
      <div><strong>Date:</strong> ${r.receipt_date||'—'}</div>
      <div><strong>Status:</strong> <span class="badge badge-${r.status}">${r.status}</span></div>
      <div><strong>Notes:</strong> ${r.notes||'—'}</div>
    </div>
    <h4 style="margin:16px 0 10px;font-size:.9rem;font-weight:700">Items</h4>
    <div class="items-table-wrap">
    <table><thead><tr><th>Product</th><th>SKU</th><th>Ordered</th><th>Received</th><th>Unit Cost</th><th>Total</th></tr></thead><tbody>
    ${items.map(i=>`<tr>
      <td>${i.product_name}</td><td><code>${i.sku}</code></td>
      <td>${i.quantity_ordered}</td><td>${i.quantity_received}</td>
      <td>$${parseFloat(i.unit_cost).toFixed(2)}</td>
      <td>$${(i.quantity_received*i.unit_cost).toFixed(2)}</td>
    </tr>`).join('')}
    </tbody></table></div>`;
  document.getElementById('viewContent').innerHTML = html;
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
