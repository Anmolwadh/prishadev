<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (customer_logged_in()) {
    redirect('account.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = getDB()->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !password_verify($password, $customer['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            login_customer($customer);
            $redirect = $_SESSION['redirect_after_login'] ?? 'account.php';
            unset($_SESSION['redirect_after_login']);
            flash('success', 'Welcome back, ' . $customer['name'] . '!');
            redirect(ltrim(str_replace(BASE_URL, '', $redirect), '/') ?: 'account.php');
        }
    }
}

$pageTitle = 'Customer Login | Prisha Enterprises';
include __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:480px">
    <div class="auth-card">
      <h1 class="h3 mb-3">Customer Login</h1>
      <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Password</label>
            <a href="<?= e(url('forgot-password.php')) ?>" class="small text-decoration-none text-success">Forgot Password?</a>
          </div>
          <input type="password" name="password" class="form-control mt-1" required>
        </div>
        <button class="btn btn-pe w-100" type="submit">Login</button>
      </form>
      <p class="mt-3 mb-0 text-center">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
