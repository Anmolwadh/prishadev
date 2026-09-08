<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (customer_logged_in()) {
    redirect('account.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!validate_email($email)) $errors[] = 'Valid email is required.';
    if (!validate_phone($phone)) $errors[] = 'Valid 10-digit mobile is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $pdo = getDB();
        $check = $pdo->prepare('SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1');
        $check->execute([$email, $phone]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email or phone already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO customers (name, email, phone, password) VALUES (?, ?, ?, ?)');
            $ins->execute([$name, $email, $phone, $hash]);
            $id = (int)$pdo->lastInsertId();
            login_customer(['id' => $id, 'name' => $name]);
            flash('success', 'Account created successfully.');
            redirect('account.php');
        }
    }
}

$pageTitle = 'Register | Prisha Enterprises';
include __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:560px">
    <div class="auth-card">
      <h1 class="h3 mb-3">Create Account</h1>
      <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Mobile</label><input type="text" name="phone" class="form-control" required maxlength="10" value="<?= e($_POST['phone'] ?? '') ?>"></div>
        <div class="mb-3">
          <label class="form-label" for="regPassword">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="regPassword" class="form-control" required autocomplete="new-password">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="regConfirm">Confirm Password</label>
          <div class="input-group">
            <input type="password" name="confirm_password" id="regConfirm" class="form-control" required autocomplete="new-password">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-pe w-100" type="submit">Register</button>
      </form>
      <p class="mt-3 mb-0 text-center">Already have an account? <a href="<?= e(url('login.php')) ?>">Login</a></p>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
