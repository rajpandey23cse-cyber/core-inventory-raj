<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CoreInventory Setup</title>
<link rel="stylesheet" href="/inventory/assets/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
<div class="auth-card" style="max-width:560px">
  <div class="auth-logo">
    <i class="fa-solid fa-warehouse"></i>
    <h1>CoreInventory Setup</h1>
    <p>Database Installation Wizard</p>
  </div>

<?php
$step = $_GET['step'] ?? 'check';

if ($step === 'install') {
    $host = $_POST['host'] ?? 'localhost';
    $user = $_POST['user'] ?? 'root';
    $pass = $_POST['password'] ?? '';
    $db   = $_POST['dbname'] ?? 'coreinventory';

    // Update db.php
    $dbContent = "<?php\ndefine('DB_HOST', '$host');\ndefine('DB_USER', '$user');\ndefine('DB_PASS', '$pass');\ndefine('DB_NAME', '$db');\n\nfunction getDB() {\n    static \$conn = null;\n    if (\$conn === null) {\n        \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);\n        if (\$conn->connect_error) {\n            http_response_code(500);\n            die(json_encode(['error' => 'Database connection failed: ' . \$conn->connect_error]));\n        }\n        \$conn->set_charset('utf8mb4');\n    }\n    return \$conn;\n}\n\nfunction getPDO() {\n    static \$pdo = null;\n    if (\$pdo === null) {\n        try {\n            \$pdo = new PDO(\n                \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\",\n                DB_USER, DB_PASS,\n                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]\n            );\n        } catch (PDOException \$e) {\n            http_response_code(500);\n            die(json_encode(['error' => 'DB Error: ' . \$e->getMessage()]));\n        }\n    }\n    return \$pdo;\n}\n";
    file_put_contents(__DIR__ . '/includes/db.php', $dbContent);

    // Create database and run schema
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");
        $sql = file_get_contents(__DIR__ . '/db/schema.sql');
        // Split and execute statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $errors = [];
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try { $pdo->exec($stmt); } catch (PDOException $e) { $errors[] = $e->getMessage(); }
            }
        }
        echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Database installed successfully!</strong></div>';
        if ($errors) {
            echo '<div class="alert alert-warning"><i class="fa fa-triangle-exclamation"></i> Some warnings (non-critical): '.implode('<br>', array_slice($errors,0,3)).'</div>';
        }
        echo '<div style="background:var(--bg);border-radius:8px;padding:16px;margin-bottom:16px;font-size:.875rem">
            <p><strong>Default Admin Login:</strong></p>
            <p>Email: <code>admin@coreinventory.com</code></p>
            <p>Password: <code>password</code></p>
        </div>
        <a href="/inventory/index.php" class="btn btn-primary btn-block btn-lg"><i class="fa fa-right-to-bracket"></i> Go to Login</a>';
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="fa fa-circle-exclamation"></i> Connection failed: '.htmlspecialchars($e->getMessage()).'</div>';
        echo '<a href="/inventory/setup.php" class="btn btn-outline btn-block">Try Again</a>';
    }
} else {
?>
  <div class="alert alert-info"><i class="fa fa-info-circle"></i> Fill in your MySQL database credentials to set up CoreInventory.</div>
  <form method="POST" action="/inventory/setup.php?step=install">
    <div class="form-group">
      <label class="form-label">Database Host</label>
      <input type="text" name="host" class="form-control" value="localhost" required>
    </div>
    <div class="form-group">
      <label class="form-label">Database User</label>
      <input type="text" name="user" class="form-control" value="root" required>
    </div>
    <div class="form-group">
      <label class="form-label">Database Password</label>
      <input type="password" name="password" class="form-control" placeholder="Leave empty if no password">
    </div>
    <div class="form-group">
      <label class="form-label">Database Name</label>
      <input type="text" name="dbname" class="form-control" value="coreinventory" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa fa-database"></i> Install Database</button>
  </form>
  <p style="text-align:center;margin-top:14px;font-size:.8rem;color:var(--text-muted)">
    This will create the database and seed sample data.<br>Delete this file after setup for security.
  </p>
<?php } ?>
</div>
</div>
</body>
</html>
