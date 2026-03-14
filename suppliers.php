<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'Suppliers';
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
            if ($id) { $pdo->prepare("UPDATE suppliers SET name=?,contact_person=?,email=?,phone=?,address=? WHERE id=?")->execute([$name,$contact,$email,$phone,$address,$id]); $msg='Supplier updated.'; }
            else { $pdo->prepare("INSERT INTO suppliers (name,contact_person,email,phone,address) VALUES (?,?,?,?,?)")->execute([$name,$contact,$email,$phone,$address]); $msg='Supplier added.'; }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]); $msg='Supplier deleted.';
    }
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msgType?> alert-auto"><i class="fa fa-<?=$msgType==='success'?'check-circle':'circle-exclamation'?>"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class="page-header">
  <h2><i class="fa fa-handshake text-primary"></i> Suppliers</h2>
  <button class="btn btn-primary" onclick="openModal('supModal');clearSup()"><i class="fa fa-plus"></i> Add Supplier</button>
</div>
<div class="card"><div class="table-wrap"><table>
  <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if($suppliers): foreach($suppliers as $i=>$s): ?>
    <tr>
      <td><?=$i+1?></td>
      <td><strong><?=htmlspecialchars($s['name'])?></strong></td>
      <td><?=htmlspecialchars($s['contact_person']??'—')?></td>
      <td><?=htmlspecialchars($s['email']??'—')?></td>
      <td><?=htmlspecialchars($s['phone']??'—')?></td>
      <td><div class="table-actions">
        <button class="btn btn-sm btn-outline" onclick="editSup(<?=htmlspecialchars(json_encode($s))?>)"><i class="fa fa-edit"></i></button>
        <form method="POST" style="display:inline" onsubmit="return confirmDelete()">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>">
          <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="6"><div class="empty-state"><i class="fa fa-handshake"></i><p>No suppliers found.</p></div></td></tr>
    <?php endif; ?>
  </tbody>
</table></div></div>
<div class="modal-overlay" id="supModal"><div class="modal">
  <div class="modal-header"><h3 class="modal-title" id="supTitle">Add Supplier</h3><button class="modal-close" onclick="closeModal('supModal')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="sup_id" value="0">
    <div class="form-grid">
      <div class="form-group span-2"><label class="form-label">Company Name *</label><input type="text" name="name" id="sup_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="sup_contact" class="form-control"></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="sup_phone" class="form-control"></div>
      <div class="form-group span-2"><label class="form-label">Email</label><input type="email" name="email" id="sup_email" class="form-control"></div>
      <div class="form-group span-2"><label class="form-label">Address</label><textarea name="address" id="sup_addr" class="form-control" rows="2"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('supModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button></div>
  </form>
</div></div>
<script>
function clearSup(){ document.getElementById('supTitle').textContent='Add Supplier'; ['sup_id','sup_name','sup_contact','sup_phone','sup_email','sup_addr'].forEach(id=>document.getElementById(id).value=''); document.getElementById('sup_id').value=0; }
function editSup(s){ document.getElementById('supTitle').textContent='Edit Supplier'; document.getElementById('sup_id').value=s.id; document.getElementById('sup_name').value=s.name; document.getElementById('sup_contact').value=s.contact_person||''; document.getElementById('sup_phone').value=s.phone||''; document.getElementById('sup_email').value=s.email||''; document.getElementById('sup_addr').value=s.address||''; openModal('supModal'); }
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
