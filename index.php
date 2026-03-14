<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    // header('Location: /inventory/dashboard.php');
    // header('Location: /inventory/dashboard.php');
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_avatar']= $user['avatar'];
                header('Location: /inventory/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    }

    elseif ($action === 'register') {
        $activeTab = 'register';
        $name     = trim($_POST['reg_name'] ?? '');
        $email    = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirm  = $_POST['reg_confirm'] ?? '';

        if (!$name || !$email || !$password) {
            $error = 'All fields are required.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'staff')");
                $stmt->execute([$name, $email, $hash]);
                $success = 'Account created! You can now login.';
                $activeTab = 'login';
            }
        }
    }

    elseif ($action === 'forgot') {
        $activeTab = 'forgot';
        $email = trim($_POST['forgot_email'] ?? '');
        if ($email) {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $otp = rand(100000, 999999);
                $exp = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $pdo->prepare("UPDATE users SET otp_code=?, otp_expires_at=? WHERE email=?")->execute([$otp, $exp, $email]);
                // In real app: send email. We show it for demo.
                $success = "OTP sent (demo): <strong>$otp</strong> — expires in 10 minutes.";
                $activeTab = 'reset';
                $_SESSION['reset_email'] = $email;
            } else {
                $error = 'Email not found.';
            }
        }
    }

    elseif ($action === 'reset') {
        $activeTab = 'reset';
        $otp         = trim($_POST['otp'] ?? '');
        $newPass     = $_POST['new_password'] ?? '';
        $resetEmail  = $_SESSION['reset_email'] ?? '';

        if ($resetEmail && $otp && $newPass) {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND otp_code=? AND otp_expires_at > NOW()");
            $stmt->execute([$resetEmail, $otp]);
            if ($stmt->fetch()) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=?, otp_code=NULL, otp_expires_at=NULL WHERE email=?")->execute([$hash, $resetEmail]);
                unset($_SESSION['reset_email']);
                $success = 'Password reset successful. You can now login.';
                $activeTab = 'login';
            } else {
                $error = 'Invalid or expired OTP.';
            }
        } else {
            $error = 'Please fill all fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CoreInventory – Login</title>
<!-- <link rel="stylesheet" href="/inventory/assets/css/main.css"> -->
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <i class="fa-solid fa-warehouse"></i>
      <h1>CoreInventory</h1>
      <p>Smart Inventory Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-circle-exclamation"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-circle-check"></i><?= $success ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <?php if ($activeTab === 'login'): ?>
    <div class="auth-tabs">
      <div class="auth-tab active">Sign In</div>
      <div class="auth-tab" onclick="showRegister()">Register</div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-icon-wrap">
          <i class="fa fa-envelope"></i>
          <input type="email" name="email" class="form-control" placeholder="admin@coreinventory.com" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-icon-wrap">
          <i class="fa fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>
      <div style="text-align:right;margin-bottom:16px">
        <a href="#" onclick="showForgot()" style="color:var(--primary);font-size:.85rem">Forgot password?</a>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa fa-right-to-bracket"></i> Sign In</button>
      <p style="text-align:center;margin-top:14px;font-size:.85rem;color:var(--text-muted)">
        Demo: admin@coreinventory.com / password
      </p>
    </form>

    <!-- Register Form -->
    <?php elseif ($activeTab === 'register'): ?>
    <div class="auth-tabs">
      <div class="auth-tab" onclick="showLogin()">Sign In</div>
      <div class="auth-tab active">Register</div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="register">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <div class="input-icon-wrap">
          <i class="fa fa-user"></i>
          <input type="text" name="reg_name" class="form-control" placeholder="Your full name" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-icon-wrap">
          <i class="fa fa-envelope"></i>
          <input type="email" name="reg_email" class="form-control" placeholder="your@email.com" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-icon-wrap">
          <i class="fa fa-lock"></i>
          <input type="password" name="reg_password" class="form-control" placeholder="Min 6 characters" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <div class="input-icon-wrap">
          <i class="fa fa-lock"></i>
          <input type="password" name="reg_confirm" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa fa-user-plus"></i> Create Account</button>
    </form>

    <!-- Forgot Password -->
    <?php elseif ($activeTab === 'forgot'): ?>
    <h3 style="margin-bottom:16px;font-size:1rem">Reset Password</h3>
    <form method="POST">
      <input type="hidden" name="action" value="forgot">
      <div class="form-group">
        <label class="form-label">Your Email</label>
        <div class="input-icon-wrap">
          <i class="fa fa-envelope"></i>
          <input type="email" name="forgot_email" class="form-control" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Send OTP</button>
    </form>
    <p style="text-align:center;margin-top:12px"><a href="?" style="color:var(--primary);font-size:.85rem">Back to login</a></p>

    <!-- OTP Reset -->
    <?php elseif ($activeTab === 'reset'): ?>
    <h3 style="margin-bottom:16px;font-size:1rem">Enter OTP & New Password</h3>
    <form method="POST">
      <input type="hidden" name="action" value="reset">
      <div class="form-group">
        <label class="form-label">OTP Code</label>
        <input type="text" name="otp" class="form-control" placeholder="6-digit code" required>
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
    </form>
    <p style="text-align:center;margin-top:12px"><a href="?" style="color:var(--primary);font-size:.85rem">Back to login</a></p>
    <?php endif; ?>
  </div>
</div>
<script>
function showLogin() { document.querySelector('form input[name=action]').value='login'; location.reload(); }
function showRegister() {
  const f = document.createElement('form');
  f.method = 'POST';
  const inp = document.createElement('input');
  inp.type='hidden'; inp.name='action'; inp.value='register';
  f.appendChild(inp); document.body.appendChild(f); f.submit();
}
function showForgot() {
  const f = document.createElement('form');
  f.method = 'POST';
  const inp = document.createElement('input');
  inp.type='hidden'; inp.name='action'; inp.value='forgot';
  f.appendChild(inp); document.body.appendChild(f); f.submit();
}
</script>
</body>
</html>
