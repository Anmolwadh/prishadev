<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$token = trim((string)($_GET['token'] ?? ($_POST['token'] ?? '')));
$email = trim((string)($_GET['email'] ?? ($_POST['email'] ?? '')));
$userType = (isset($_GET['type']) && $_GET['type'] === 'admin') || (isset($_POST['type']) && $_POST['type'] === 'admin')
    ? 'admin'
    : 'customer';

$loginUrl = ($userType === 'admin') ? url('admin/login.php') : url('login.php');
$forgotUrl = ($userType === 'admin') ? url('forgot-password.php?type=admin') : url('forgot-password.php');

$isValid = verify_password_reset_token($email, $token, $userType);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!$isValid) {
        $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $resetOk = reset_user_password($email, $token, $password, $userType);
        if ($resetOk) {
            flash('success', 'Your password has been reset successfully. Please log in with your new password.');
            redirect($loginUrl);
        } else {
            $errors[] = 'Failed to reset password. The link may have expired or already been used.';
        }
    }
}

$pageTitle = 'Reset Password | Prisha Enterprises';
include __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:480px">
    <div class="auth-card">
      <div class="text-center mb-4">
        <div class="brand-mark mx-auto mb-2" style="width:52px;height:52px;font-size:1.2rem"><i class="fa-solid fa-lock"></i></div>
        <h1 class="h3 mb-1">Reset Password</h1>
        <p class="text-muted small mb-0">Create a new secure password for your account.</p>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$isValid): ?>
        <div class="alert alert-warning">
          <h5 class="alert-heading h6 mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Link Invalid or Expired</h5>
          <p class="small mb-0">The password reset link is either invalid, already used, or expired (links are valid for 60 minutes).</p>
        </div>
        <div class="d-flex flex-column gap-2 mt-3">
          <a href="<?= e($forgotUrl) ?>" class="btn btn-pe w-100">Request New Reset Link</a>
          <a href="<?= e($loginUrl) ?>" class="btn btn-outline-secondary w-100">Back to Login</a>
        </div>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="email" value="<?= e($email) ?>">
          <input type="hidden" name="type" value="<?= e($userType) ?>">

          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" value="<?= e($email) ?>" readonly disabled>
          </div>

          <div class="mb-3">
            <label class="form-label" for="resetPassword">New Password</label>
            <div class="input-group">
              <input type="password" name="password" id="resetPassword" class="form-control" required minlength="6" autofocus placeholder="At least 6 characters" autocomplete="new-password">
              <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="resetConfirm">Confirm New Password</label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="resetConfirm" class="form-control" required minlength="6" placeholder="Repeat your new password" autocomplete="new-password">
              <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <button class="btn btn-pe w-100" type="submit">Update Password</button>
        </form>

        <p class="mt-3 mb-0 text-center">
          <a href="<?= e($loginUrl) ?>" class="text-muted small">Cancel and Return to Login</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
