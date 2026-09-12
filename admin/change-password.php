<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$adminId = (int)$_SESSION['admin_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }
    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirm password do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, (string)$row['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $update->execute([$newHash, $adminId]);

            flash('success', 'Admin password changed successfully.');
            redirect('admin/change-password.php');
        }
    }
}

$pageTitle = 'Change Password';
$adminPage = 'change-password.php';
include __DIR__ . '/includes/header.php';
?>
<div class="row">
  <div class="col-lg-6 col-md-8">
    <div class="admin-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0"><i class="fa-solid fa-key text-success me-2"></i>Change Admin Password</h2>
        <a href="<?= e(url('admin/settings.php')) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="fa-solid fa-gear me-1"></i>Settings
        </a>
      </div>
      <p class="text-muted small mb-4">Update your administrator account password. For security, please enter your current password before setting a new one.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label" for="currentPassword">Current Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" name="current_password" id="currentPassword" class="form-control" required autocomplete="current-password" placeholder="Enter current password">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="newPassword">New Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
          <div class="form-text">Must be at least 6 characters long.</div>
        </div>

        <div class="mb-4">
          <label class="form-label" for="confirmPassword">Confirm New Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Re-type new password">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-success" type="submit">
            <i class="fa-solid fa-check me-1"></i>Update Password
          </button>
          <a href="<?= e(url('admin/dashboard.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
