<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Customers';
$pdo = getPDO();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? ''); $contact = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
        if (!$name) { $msg = 'Name is required.'; $msgType = 'danger'; }
        else {
            if ($id) { $pdo->prepare("UPDATE customers SET name=?,contact_person=?,email=?,phone=?,address=? WHERE id=?")->execute([$name,$contact,$email,$phone,$address,$id]); $msg='Customer updated.'; }
            else { $pdo->prepare("INSERT INTO customers (name,contact_person,email,phone,address) VALUES (?,?,?,?,?)")->execute([$name,$contact,$email,$phone,$address]); $msg='Customer added.'; }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]); $msg='Customer deleted.';
    }
}

$customers = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM deliveries d WHERE d.customer_id=c.id) as order_count FROM customers c ORDER BY c.name")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msgType?> alert-auto"><i class="fa fa-<?=$msgType==='success'?'check-circle':'circle-exclamation'?>"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class="page-header">
  <h2><i class="fa fa-users text-primary"></i> Customers</h2>
  <button class="btn btn-primary" onclick="openModal('cusModal');clearCus()"><i class="fa fa-plus"></i> Add Customer</button>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Orders</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if($customers): foreach($customers as $i=>$c): ?>
    <tr>
      <td><?=$i+1?></td>
      <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
      <td><?=htmlspecialchars($c['contact_person']??'—')?></td>
      <td><?=htmlspecialchars($c['email']??'—')?></td>
      <td><?=htmlspecialchars($c['phone']??'—')?></td>
      <td><span class="badge badge-confirmed"><?=$c['order_count']?></span></td>
      <td><div class="table-actions">
        <button class="btn btn-sm btn-outline" onclick="editCus(<?=htmlspecialchars(json_encode($c))?>)"><i class="fa fa-edit"></i></button>
        <form method="POST" style="display:inline" onsubmit="return confirmDelete()">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>">
          <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="7"><div class="empty-state"><i class="fa fa-users"></i><p>No customers found.</p></div></td></tr>
    <?php endif; ?>
  </tbody>
</table></div></div>
<div class="modal-overlay" id="cusModal"><div class="modal">
  <div class="modal-header"><h3 class="modal-title" id="cusTitle">Add Customer</h3><button class="modal-close" onclick="closeModal('cusModal')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="cus_id" value="0">
    <div class="form-grid">
      <div class="form-group span-2"><label class="form-label">Company / Customer Name *</label><input type="text" name="name" id="cus_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="cus_contact" class="form-control"></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="cus_phone" class="form-control"></div>
      <div class="form-group span-2"><label class="form-label">Email</label><input type="email" name="email" id="cus_email" class="form-control"></div>
      <div class="form-group span-2"><label class="form-label">Address</label><textarea name="address" id="cus_addr" class="form-control" rows="2"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('cusModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button></div>
  </form>
</div></div>
<script>
function clearCus(){ document.getElementById('cusTitle').textContent='Add Customer'; ['cus_id','cus_name','cus_contact','cus_phone','cus_email','cus_addr'].forEach(id=>document.getElementById(id).value=''); document.getElementById('cus_id').value=0; }
function editCus(c){ document.getElementById('cusTitle').textContent='Edit Customer'; document.getElementById('cus_id').value=c.id; document.getElementById('cus_name').value=c.name; document.getElementById('cus_contact').value=c.contact_person||''; document.getElementById('cus_phone').value=c.phone||''; document.getElementById('cus_email').value=c.email||''; document.getElementById('cus_addr').value=c.address||''; openModal('cusModal'); }
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
