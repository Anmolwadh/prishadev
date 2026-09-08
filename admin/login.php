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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
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
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus autocomplete="username"></div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="adminPassword">Password</label>
            <a href="<?= e(url('forgot-password.php?type=admin')) ?>" class="small text-decoration-none text-success">Forgot Password?</a>
          </div>
          <div class="input-group">
            <input type="password" name="password" id="adminPassword" class="form-control" required autocomplete="current-password">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-success w-100" type="submit">Login</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Default: <code>admin</code> / <code>password</code></p>
    </div>
  </div>
<script>
function togglePasswordVisibility(btn, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  if (!btn) return;
  var group = btn.closest('.input-group') || btn.parentElement;
  if (!group) return;
  var input = group.querySelector('input');
  if (!input) return;
  var isPass = input.type === 'password';
  var nextType = isPass ? 'text' : 'password';
  input.type = nextType;
  input.setAttribute('type', nextType);
  var icon = btn.querySelector('i');
  if (icon) {
    if (isPass) {
      icon.className = 'fa-regular fa-eye-slash';
      btn.setAttribute('aria-label', 'Hide password');
    } else {
      icon.className = 'fa-regular fa-eye';
      btn.setAttribute('aria-label', 'Show password');
    }
  }
  try { input.focus(); } catch (err) {}
}

document.addEventListener('click', function (e) {
  var toggleBtn = e.target.closest('[data-toggle-password]');
  if (toggleBtn && !toggleBtn.getAttribute('onclick')) {
    e.preventDefault();
    togglePasswordVisibility(toggleBtn, e);
  }
});
</script>
</body>
</html>
