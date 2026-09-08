<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = getDB()->prepare("SELECT * FROM admins WHERE username = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password'])) {
        $error = 'Invalid username or password.';
    } else {
        login_admin($admin);
        redirect('admin/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Prisha Enterprises</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/admin.css')) ?>" rel="stylesheet">
</head>
<body class="admin-body d-flex align-items-center" style="min-height:100vh">
  <div class="container" style="max-width:420px">
    <div class="admin-card shadow-sm">
      <h1 class="h4 mb-1">Admin Login</h1>
      <p class="text-muted mb-3">Prisha Enterprises Portal</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus></div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Password</label>
            <a href="<?= e(url('forgot-password.php?type=admin')) ?>" class="small text-decoration-none text-success">Forgot Password?</a>
          </div>
          <input type="password" name="password" class="form-control mt-1" required>
        </div>
        <button class="btn btn-success w-100" type="submit">Login</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Default: <code>admin</code> / <code>password</code></p>
    </div>
  </div>
</body>
</html>
