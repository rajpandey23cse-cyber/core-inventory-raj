<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Delivery Orders';
$pdo = getPDO();
$msg = ''; $msgType = 'success';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = intval($_POST['id'] ?? 0);
        $customer  = intval($_POST['customer_id'] ?? 0) ?: null;
        $warehouse = intval($_POST['warehouse_id'] ?? 0);
        $status    = $_POST['status'] ?? 'draft';
        $notes     = trim($_POST['notes'] ?? '');
        $date      = $_POST['delivery_date'] ?? date('Y-m-d');
        $products  = $_POST['product_id'] ?? [];
        $qtys      = $_POST['qty'] ?? [];
        $prices    = $_POST['unit_price'] ?? [];

        if (!$warehouse) { $msg = 'Warehouse is required.'; $msgType = 'danger'; }
        else {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare("UPDATE deliveries SET customer_id=?,warehouse_id=?,status=?,notes=?,delivery_date=? WHERE id=?")
                        ->execute([$customer,$warehouse,$status,$notes,$date,$id]);
                    $refNum = $pdo->query("SELECT delivery_number FROM deliveries WHERE id=$id")->fetchColumn();
                    $pdo->prepare("DELETE FROM delivery_items WHERE delivery_id=?")->execute([$id]);
                } else {
                    $refNum = generateRef('DEL');
                    $pdo->prepare("INSERT INTO deliveries (delivery_number,customer_id,warehouse_id,status,notes,delivery_date,created_by) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$refNum,$customer,$warehouse,$status,$notes,$date,$user['id']]);
                    $id = $pdo->lastInsertId();
                }

                foreach ($products as $idx => $pid) {
                    $pid = intval($pid); if (!$pid) continue;
                    $qty = intval($qtys[$idx] ?? 0);
                    $price = floatval($prices[$idx] ?? 0);
                    $pdo->prepare("INSERT INTO delivery_items (delivery_id,product_id,quantity,unit_price) VALUES (?,?,?,?)")
                        ->execute([$id,$pid,$qty,$price]);

                    if (in_array($status, ['shipped','delivered']) && $qty > 0) {
                        $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=GREATEST(0,quantity-VALUES(quantity))")
                            ->execute([$pid,$warehouse,$qty]);
                        $qa = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                        $qa->execute([$pid,$warehouse]);
                        $qaVal = $qa->fetchColumn() ?: 0;
                        $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'delivery',?,?,?,?)")
                            ->execute([$pid,$warehouse,$refNum,-$qty,$qaVal,$user['id']]);
                    }
                }
                $pdo->commit();
                $msg = 'Delivery saved successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error: '.$e->getMessage(); $msgType = 'danger';
            }
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM deliveries WHERE id=?")->execute([$id]);
        $msg = 'Delivery deleted.';
    }
    elseif ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $allowedStatuses = ['draft','confirmed','shipped','delivered','cancelled'];
        if (in_array($newStatus, $allowedStatuses)) {
            $prev = $pdo->prepare("SELECT status,warehouse_id,delivery_number FROM deliveries WHERE id=?");
            $prev->execute([$id]); $del = $prev->fetch();
            // Deduct stock when shipping
            if ($del && $newStatus === 'shipped' && $del['status'] !== 'shipped') {
                $items = $pdo->prepare("SELECT * FROM delivery_items WHERE delivery_id=?");
                $items->execute([$id]); $rows = $items->fetchAll();
                foreach ($rows as $row) {
                    $pdo->prepare("INSERT INTO stock (product_id,warehouse_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=GREATEST(0,quantity-VALUES(quantity))")
                        ->execute([$row['product_id'],$del['warehouse_id'],$row['quantity']]);
                    $qa = $pdo->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                    $qa->execute([$row['product_id'],$del['warehouse_id']]);
                    $qaVal = $qa->fetchColumn() ?: 0;
                    $pdo->prepare("INSERT INTO move_history (product_id,warehouse_id,move_type,reference_number,quantity_change,quantity_after,created_by) VALUES (?,?,'delivery',?,?,?,?)")
                        ->execute([$row['product_id'],$del['warehouse_id'],$del['delivery_number'],-$row['quantity'],$qaVal,$user['id']]);
                }
            }
            $pdo->prepare("UPDATE deliveries SET status=? WHERE id=?")->execute([$newStatus,$id]);
            $msg = 'Status updated.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1)); $perPage = 15;
$where = ['1=1']; $params = [];
if ($search) { $where[] = '(d.delivery_number LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterStatus) { $where[] = 'd.status=?'; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM deliveries d LEFT JOIN customers c ON d.customer_id=c.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();
$pages = ceil($total/$perPage); $offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT d.*,c.name as customer_name,w.name as warehouse_name,u.name as created_by_name FROM deliveries d LEFT JOIN customers c ON d.customer_id=c.id LEFT JOIN warehouses w ON d.warehouse_id=w.id LEFT JOIN users u ON d.created_by=u.id WHERE $whereStr ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $deliveries = $stmt->fetchAll();

$customers  = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT id,sku,name,unit,unit_price FROM products WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto"><i class="fa fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2><i class="fa fa-truck-fast text-primary"></i> Delivery Orders</h2>
  <button class="btn btn-primary" onclick="openModal('deliveryModal');resetForm()"><i class="fa fa-plus"></i> New Delivery</button>
</div>

<div class="filters-bar">
  <form method="GET" style="display:contents">
    <div class="search-box"><i class="fa fa-search"></i>
      <input type="text" name="search" class="form-control" placeholder="Search deliveries..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <?php foreach (['draft','confirmed','shipped','delivered','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
    <a href="/inventory/deliveries.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Delivery Orders (<?= number_format($total) ?>)</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Ref Number</th><th>Customer</th><th>Warehouse</th><th>Delivery Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($deliveries): foreach ($deliveries as $i => $d): ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><strong><?= htmlspecialchars($d['delivery_number']) ?></strong></td>
          <td><?= htmlspecialchars($d['customer_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($d['warehouse_name']) ?></td>
          <td><?= $d['delivery_date'] ? date('M d, Y', strtotime($d['delivery_date'])) : '—' ?></td>
          <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="viewDelivery(<?= $d['id'] ?>)" title="View"><i class="fa fa-eye"></i></button>
              <?php if (!in_array($d['status'],['delivered','cancelled'])): ?>
              <div class="tooltip-wrap">
                <select class="form-control" style="padding:4px 8px;font-size:.8rem;border-radius:6px" onchange="updateStatus(<?= $d['id'] ?>, this.value)">
                  <?php foreach (['draft','confirmed','shipped','delivered','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $d['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <?php if ($d['status'] === 'draft'): ?>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete delivery?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-truck-fast"></i><p>No delivery orders found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delivery Modal -->
<div class="modal-overlay" id="deliveryModal">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title">New Delivery Order</h3>
      <button class="modal-close" onclick="closeModal('deliveryModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="0">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-control">
              <option value="">-- Select Customer --</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Warehouse (From) *</label>
            <select name="warehouse_id" class="form-control" required>
              <option value="">-- Select Warehouse --</option>
              <?php foreach ($warehouses as $w): ?>
              <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Delivery Date</label>
            <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="draft">Draft</option>
              <option value="confirmed">Confirmed</option>
              <option value="shipped">Shipped (deducts stock)</option>
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
            <thead><tr><th>Product</th><th>Quantity</th><th>Unit Price ($)</th><th>Subtotal</th><th></th></tr></thead>
            <tbody id="delItemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline add-item-row" onclick="addDelRow()"><i class="fa fa-plus"></i> Add Item</button>
        <div style="margin-top:10px;text-align:right;font-weight:700">Total: $<span id="delTotal">0.00</span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('deliveryModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Delivery</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Delivery Details</h3>
      <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="viewContent"><p>Loading...</p></div>
  </div>
</div>

<!-- Update Status Form (hidden) -->
<form id="statusForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="update_status">
  <input type="hidden" name="id" id="statusId">
  <input type="hidden" name="new_status" id="statusVal">
</form>

<script>
const products = <?= json_encode($products) ?>;

function resetForm() {
  document.getElementById('delItemsBody').innerHTML='';
  addDelRow();
  calcTotal();
}

function addDelRow() {
  const tbody = document.getElementById('delItemsBody');
  const opts = products.map(p=>`<option value="${p.id}" data-price="${p.unit_price}">${p.name} (${p.sku})</option>`).join('');
  const tr = document.createElement('tr');
  tr.innerHTML=`
    <td><select name="product_id[]" class="form-control" required onchange="autoPrice(this)"><option value="">-- Product --</option>${opts}</select></td>
    <td><input type="number" name="qty[]" class="form-control" min="1" value="1" oninput="calcTotal()"></td>
    <td><input type="number" step="0.01" name="unit_price[]" class="form-control" min="0" value="0" oninput="calcTotal()"></td>
    <td class="sub">$0.00</td>
    <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('tr').remove();calcTotal()"><i class="fa fa-times"></i></button></td>`;
  tbody.appendChild(tr);
}

function autoPrice(sel) {
  const opt = sel.options[sel.selectedIndex];
  const price = opt.dataset.price || 0;
  sel.closest('tr').querySelector('[name="unit_price[]"]').value = price;
  calcTotal();
}

function calcTotal() {
  let t=0;
  document.querySelectorAll('#delItemsBody tr').forEach(row=>{
    const q=parseFloat(row.querySelector('[name="qty[]"]')?.value||0);
    const p=parseFloat(row.querySelector('[name="unit_price[]"]')?.value||0);
    const sub=q*p; t+=sub;
    const subEl=row.querySelector('.sub');
    if(subEl) subEl.textContent='$'+sub.toFixed(2);
  });
  document.getElementById('delTotal').textContent=t.toFixed(2);
}

function updateStatus(id, status) {
  if (!confirm('Update status to ' + status + '?')) return;
  document.getElementById('statusId').value=id;
  document.getElementById('statusVal').value=status;
  document.getElementById('statusForm').submit();
}

async function viewDelivery(id) {
  openModal('viewModal');
  const data = await apiGet(`/inventory/api/deliveries.php?action=view&id=${id}`);
  const d=data.delivery, items=data.items;
  let total=0;
  items.forEach(i=>total+=i.quantity*i.unit_price);
  let html=`
    <div class="form-grid">
      <div><strong>Ref:</strong> ${d.delivery_number}</div>
      <div><strong>Customer:</strong> ${d.customer_name||'—'}</div>
      <div><strong>Warehouse:</strong> ${d.warehouse_name}</div>
      <div><strong>Date:</strong> ${d.delivery_date||'—'}</div>
      <div><strong>Status:</strong> <span class="badge badge-${d.status}">${d.status}</span></div>
      <div><strong>Notes:</strong> ${d.notes||'—'}</div>
    </div>
    <h4 style="margin:16px 0 10px;font-size:.9rem;font-weight:700">Items</h4>
    <div class="items-table-wrap">
    <table><thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>
    ${items.map(i=>`<tr><td>${i.product_name}</td><td><code>${i.sku}</code></td><td>${i.quantity}</td><td>$${parseFloat(i.unit_price).toFixed(2)}</td><td>$${(i.quantity*i.unit_price).toFixed(2)}</td></tr>`).join('')}
    </tbody><tfoot><tr><td colspan="4" style="text-align:right;font-weight:700">Grand Total</td><td><strong>$${total.toFixed(2)}</strong></td></tr></tfoot></table></div>`;
  document.getElementById('viewContent').innerHTML=html;
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
