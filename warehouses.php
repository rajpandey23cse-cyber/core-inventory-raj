<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Warehouses';
$pdo = getPDO();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = intval($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $code    = trim($_POST['code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$name || !$code) { $msg = 'Name and Code are required.'; $msgType = 'danger'; }
        else {
            if ($id) {
                $pdo->prepare("UPDATE warehouses SET name=?,code=?,address=? WHERE id=?")->execute([$name,$code,$address,$id]);
                $msg = 'Warehouse updated.';
            } else {
                $pdo->prepare("INSERT INTO warehouses (name,code,address) VALUES (?,?,?)")->execute([$name,$code,$address]);
                $msg = 'Warehouse added.';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM warehouses WHERE id=?")->execute([$id]);
        $msg = 'Warehouse deleted.';
    }
}

$warehouses = $pdo->query("SELECT w.*, (SELECT COUNT(*) FROM stock s WHERE s.warehouse_id=w.id) as product_count, (SELECT COALESCE(SUM(quantity),0) FROM stock s WHERE s.warehouse_id=w.id) as total_stock FROM warehouses w ORDER BY w.name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?> alert-auto"><i class="fa fa-<?=$msgType==='success'?'check-circle':'circle-exclamation'?>"></i> <?=htmlspecialchars($msg)?></div>
<?php endif; ?>
<div class="page-header">
  <h2><i class="fa fa-building text-primary"></i> Warehouses</h2>
  <button class="btn btn-primary" onclick="openModal('whModal');clearForm()"><i class="fa fa-plus"></i> Add Warehouse</button>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Address</th><th>Products</th><th>Total Stock</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($warehouses): foreach ($warehouses as $i => $w): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?=htmlspecialchars($w['name'])?></strong></td>
          <td><span class="badge badge-confirmed"><?=htmlspecialchars($w['code'])?></span></td>
          <td><?=htmlspecialchars($w['address']??'—')?></td>
          <td><?=$w['product_count']?></td>
          <td><strong><?=number_format($w['total_stock'])?></strong></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline" onclick="editWH(<?=htmlspecialchars(json_encode($w))?>)"><i class="fa fa-edit"></i></button>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete warehouse?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=$w['id']?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-building"></i><p>No warehouses found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="modal-overlay" id="whModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="whTitle">Add Warehouse</h3>
      <button class="modal-close" onclick="closeModal('whModal')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="wh_id" value="0">
        <div class="form-group"><label class="form-label">Warehouse Name *</label><input type="text" name="name" id="wh_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Code *</label><input type="text" name="code" id="wh_code" class="form-control" required placeholder="e.g. WH-001"></div>
        <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="wh_addr" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('whModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>
<script>
function clearForm(){ document.getElementById('whTitle').textContent='Add Warehouse'; document.getElementById('wh_id').value=0; document.getElementById('wh_name').value=''; document.getElementById('wh_code').value=''; document.getElementById('wh_addr').value=''; }
function editWH(w){ document.getElementById('whTitle').textContent='Edit Warehouse'; document.getElementById('wh_id').value=w.id; document.getElementById('wh_name').value=w.name; document.getElementById('wh_code').value=w.code; document.getElementById('wh_addr').value=w.address||''; openModal('whModal'); }
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
