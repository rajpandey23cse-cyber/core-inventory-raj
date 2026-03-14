<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$pageTitle = 'My Profile';
$pdo = getPDO();
$msg = ''; $msgType = 'success';
$user = currentUser();

$u = $pdo->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$user['id']]); $u = $u->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!$name) { $msg = 'Name is required.'; $msgType = 'danger'; }
        else {
            $pdo->prepare("UPDATE users SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$user['id']]);
            $_SESSION['user_name'] = $name;
            $msg = 'Profile updated.';
            $u['name'] = $name; $u['phone'] = $phone;
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $u['password'])) { $msg = 'Current password is incorrect.'; $msgType = 'danger'; }
        elseif ($new !== $confirm) { $msg = 'New passwords do not match.'; $msgType = 'danger'; }
        elseif (strlen($new) < 6) { $msg = 'Password must be at least 6 characters.'; $msgType = 'danger'; }
        else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$user['id']]);
            $msg = 'Password changed successfully.';
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?=$msgType?> alert-auto"><i class="fa fa-<?=$msgType==='success'?'check-circle':'circle-exclamation'?>"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class="page-header"><h2><i class="fa fa-user-gear text-primary"></i> My Profile</h2></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
  <!-- Profile Info -->
  <div class="card">
    <div class="card-header"><span class="card-title">Profile Information</span></div>
    <div class="card-body">
      <div style="text-align:center;margin-bottom:24px">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:700;margin:0 auto 12px">
          <?=strtoupper(substr($u['name'],0,1))?>
        </div>
        <h3><?=htmlspecialchars($u['name'])?></h3>
        <p class="text-muted"><?=ucfirst($u['role'])?></p>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?=htmlspecialchars($u['name'])?>" required></div>
        <div class="form-group"><label class="form-label">Email (read-only)</label><input type="email" class="form-control" value="<?=htmlspecialchars($u['email'])?>" disabled></div>
        <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?=htmlspecialchars($u['phone']??'')?>"></div>
        <div class="form-group"><label class="form-label">Role</label><input type="text" class="form-control" value="<?=ucfirst($u['role'])?>" disabled></div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Update Profile</button>
      </form>
    </div>
  </div>
  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><span class="card-title">Change Password</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn btn-warning btn-block"><i class="fa fa-key"></i> Change Password</button>
      </form>
      <hr style="margin:24px 0;border-color:var(--border)">
      <div style="background:var(--bg);border-radius:8px;padding:16px">
        <p style="font-size:.85rem;color:var(--text-muted)"><i class="fa fa-info-circle"></i> Account created: <?=date('M d, Y',strtotime($u['created_at']))?></p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
