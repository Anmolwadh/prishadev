<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$userType = (isset($_GET['type']) && $_GET['type'] === 'admin') || (isset($_POST['type']) && $_POST['type'] === 'admin')
    ? 'admin'
    : 'customer';

if ($userType === 'customer' && customer_logged_in()) {
    redirect('account.php');
}
if ($userType === 'admin' && admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$errors = [];
$submitted = false;
$devResetUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !validate_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $token = create_password_reset_token($email, $userType);
        if ($token) {
            $resetUrl = absolute_url('reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . ($userType === 'admin' ? '&type=admin' : ''));
            $siteName = get_setting('business_name', SITE_NAME) ?? SITE_NAME;
            $subject = 'Password Reset Request - ' . $siteName;

            $htmlMessage = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($subject) . '</title></head>'
                . '<body style="margin:0;padding:20px;background-color:#f8fafc;font-family:\'Segoe UI\',system-ui,sans-serif;color:#1e293b;">'
                . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">'
                . '<div style="background:#15803d;padding:24px;text-align:center;color:#ffffff;">'
                . '<h1 style="margin:0;font-size:22px;font-weight:700;">' . e($siteName) . '</h1>'
                . '</div>'
                . '<div style="padding:32px 24px;">'
                . '<h2 style="margin-top:0;font-size:18px;color:#0f172a;">Password Reset Request</h2>'
                . '<p style="font-size:14px;line-height:1.6;color:#475569;">Hello,</p>'
                . '<p style="font-size:14px;line-height:1.6;color:#475569;">We received a request to reset the password for your ' . ($userType === 'admin' ? 'administrator' : 'customer') . ' account. Click the button below to set a new password:</p>'
                . '<div style="text-align:center;margin:28px 0;">'
                . '<a href="' . e($resetUrl) . '" style="background:#15803d;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:15px;display:inline-block;">Reset Password</a>'
                . '</div>'
                . '<p style="font-size:13px;line-height:1.6;color:#64748b;">This link will expire in <strong>60 minutes</strong>. If you did not make this request, you can safely ignore this email; your account remains secure.</p>'
                . '<hr style="border:none;border-top:1px solid #f1f5f9;margin:24px 0;">'
                . '<p style="font-size:12px;color:#94a3b8;word-break:break-all;">If the button does not work, copy and paste this link into your browser:<br>'
                . '<a href="' . e($resetUrl) . '" style="color:#15803d;">' . e($resetUrl) . '</a></p>'
                . '</div>'
                . '</div>'
                . '</body></html>';

            $sent = send_email($email, $subject, $htmlMessage);

            // In local/development environments or when mail is unconfigured, expose the link for instant testing
            $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
                || (isset($_SERVER['HTTP_HOST']) && str_starts_with($_SERVER['HTTP_HOST'], 'localhost'));
            if ($isLocal || !$sent) {
                $devResetUrl = $resetUrl;
            }
        }

        $submitted = true;
    }
}

$pageTitle = 'Forgot Password | Prisha Enterprises';
$backUrl = ($userType === 'admin') ? url('admin/login.php') : url('login.php');
include __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:480px">
    <div class="auth-card">
      <div class="text-center mb-4">
        <div class="brand-mark mx-auto mb-2" style="width:52px;height:52px;font-size:1.2rem"><i class="fa-solid fa-key"></i></div>
        <h1 class="h3 mb-1">Forgot Password</h1>
        <p class="text-muted small mb-0">
          <?= $userType === 'admin' ? 'Administrator Password Recovery' : 'Enter your email to receive password reset instructions.' ?>
        </p>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($submitted): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check me-1"></i>
          If an account exists with that email address, password reset instructions have been sent. Please check your inbox and spam folder.
        </div>

        <?php if ($devResetUrl): ?>
          <div class="alert alert-info small mt-3">
            <strong><i class="fa-solid fa-flask me-1"></i> Development Mode Notice:</strong><br>
            Mail server not active on local host. Use the generated link below to reset your password directly:<br>
            <div class="mt-2 text-center">
              <a href="<?= e($devResetUrl) ?>" class="btn btn-sm btn-success">Open Reset Password Page</a>
            </div>
          </div>
        <?php endif; ?>

        <div class="text-center mt-3">
          <a href="<?= e($backUrl) ?>" class="btn btn-outline-success w-100">Back to Login</a>
        </div>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="type" value="<?= e($userType) ?>">
          <div class="mb-3">
            <label class="form-label">Registered Email</label>
            <input type="email" name="email" class="form-control" required autofocus placeholder="name@example.com" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <button class="btn btn-pe w-100" type="submit">Send Reset Link</button>
        </form>

        <p class="mt-3 mb-0 text-center">
          Remembered your password? <a href="<?= e($backUrl) ?>">Back to Login</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
