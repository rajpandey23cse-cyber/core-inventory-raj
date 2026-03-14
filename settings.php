<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAdmin();
$pageTitle = 'Settings';
$pdo = getPDO();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_user') {
        $name = trim($_POST['name']??''); $email = trim($_POST['email']??'');
        $pass = $_POST['password']??''; $role = $_POST['role']??'staff';
        if (!$name||!$email||!$pass) { $msg='All fields required.'; $msgType='danger'; }
        else {
            $check=$pdo->prepare("SELECT id FROM users WHERE email=?"); $check->execute([$email]);
            if ($check->fetch()) { $msg='Email already exists.'; $msgType='danger'; }
            else {
                $hash=password_hash($pass,PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")->execute([$name,$email,$hash,$role]);
                $msg='User added.';
            }
        }
    } elseif ($action === 'delete_user') {
        $id=intval($_POST['id']??0);
        $user=currentUser();
        if ($id==$user['id']) { $msg='Cannot delete yourself.'; $msgType='danger'; }
        else { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); $msg='User deleted.'; }
    } elseif ($action === 'change_role') {
        $id=intval($_POST['id']??0); $role=$_POST['role']??'staff';
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$id]);
        $msg='Role updated.';
    } elseif ($action === 'add_category') {
        $name=trim($_POST['cat_name']??''); $desc=trim($_POST['cat_desc']??'');
        if (!$name) { $msg='Category name required.'; $msgType='danger'; }
        else { $pdo->prepare("INSERT INTO categories (name,description) VALUES (?,?)")->execute([$name,$desc]); $msg='Category added.'; }
    } elseif ($action === 'delete_category') {
        $id=intval($_POST['id']??0);
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); $msg='Category deleted.';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as prod_count FROM categories c ORDER BY c.name")->fetchAll();
$currentUser = currentUser();

include __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msgType?> alert-auto"><i class="fa fa-<?=$msgType==='success'?'check-circle':'circle-exclamation'?>"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class="page-header"><h2><i class="fa fa-gear text-primary"></i> Settings</h2></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
  <!-- User Management -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-users"></i> User Management</span>
      <button class="btn btn-sm btn-primary" onclick="openModal('userModal')"><i class="fa fa-plus"></i> Add User</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td><?=htmlspecialchars($u['name'])?> <?=$u['id']==$currentUser['id']?'<span class="badge badge-confirmed">You</span>':''?></td>
            <td style="font-size:.8rem"><?=htmlspecialchars($u['email'])?></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="id" value="<?=$u['id']?>">
                <select name="role" class="form-control" style="padding:4px 8px;font-size:.8rem" onchange="this.form.submit()" <?=$u['id']==$currentUser['id']?'disabled':''?>>
                  <option value="admin" <?=$u['role']==='admin'?'selected':''?>>Admin</option>
                  <option value="staff" <?=$u['role']==='staff'?'selected':''?>>Staff</option>
                </select>
              </form>
            </td>
            <td>
              <?php if ($u['id']!=$currentUser['id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete user?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?=$u['id']?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Categories -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa fa-tags"></i> Product Categories</span>
      <button class="btn btn-sm btn-primary" onclick="openModal('catModal')"><i class="fa fa-plus"></i> Add Category</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Products</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($categories as $c): ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($c['description']??'')?></small></td>
            <td><span class="badge badge-confirmed"><?=$c['prod_count']?></span></td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirmDelete('Delete category?')">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="<?=$c['id']?>">
                <button type="submit" class="btn btn-sm btn-danger" <?=$c['prod_count']>0?'disabled title="Has products"':''?>><i class="fa fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="userModal"><div class="modal">
  <div class="modal-header"><h3 class="modal-title">Add New User</h3><button class="modal-close" onclick="closeModal('userModal')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="action" value="add_user">
    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
    <div class="form-group"><label class="form-label">Role</label>
      <select name="role" class="form-control"><option value="staff">Staff</option><option value="admin">Admin</option></select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add User</button></div>
  </form>
</div></div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="catModal"><div class="modal">
  <div class="modal-header"><h3 class="modal-title">Add Category</h3><button class="modal-close" onclick="closeModal('catModal')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="action" value="add_category">
    <div class="form-group"><label class="form-label">Category Name *</label><input type="text" name="cat_name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Description</label><textarea name="cat_desc" class="form-control" rows="2"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('catModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add</button></div>
  </form>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
