<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_customer();

$customer = current_customer();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $pincode = trim((string)($_POST['pincode'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!validate_phone($phone)) $errors[] = 'Valid phone is required.';
    if ($pincode !== '' && !validate_pincode($pincode)) $errors[] = 'Invalid pincode.';

    if (!$errors) {
        $pdo = getDB();
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE customers SET name=?, phone=?, address=?, city=?, state=?, pincode=?, password=? WHERE id=?');
            $stmt->execute([$name, $phone, $address, $city, $state, $pincode, password_hash($password, PASSWORD_DEFAULT), $customer['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE customers SET name=?, phone=?, address=?, city=?, state=?, pincode=? WHERE id=?');
            $stmt->execute([$name, $phone, $address, $city, $state, $pincode, $customer['id']]);
        }
        $_SESSION['customer_name'] = $name;
        $success = 'Profile updated successfully.';
        $customer = current_customer();
        // refresh full row
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([(int)$_SESSION['customer_id']]);
        $customer = $stmt->fetch();
    }
} else {
    $stmt = getDB()->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([(int)$_SESSION['customer_id']]);
    $customer = $stmt->fetch();
}

$pageTitle = 'My Account | Prisha Enterprises';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><h1 class="mb-0">My Account</h1></div></section>
<section class="section-pad">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-3">
        <div class="list-group">
          <a class="list-group-item list-group-item-action active" href="<?= e(url('account.php')) ?>">Profile</a>
          <a class="list-group-item list-group-item-action" href="<?= e(url('my-orders.php')) ?>">My Orders</a>
          <a class="list-group-item list-group-item-action" href="<?= e(url('track-order.php')) ?>">Track Order</a>
          <a class="list-group-item list-group-item-action" href="<?= e(url('logout.php')) ?>">Logout</a>
        </div>
      </div>
      <div class="col-md-9">
        <div class="account-card">
          <h2 class="h5 mb-3">Update Profile</h2>
          <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
          <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>
          <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?= e($customer['name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?= e($customer['email'] ?? '') ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required value="<?= e($customer['phone'] ?? '') ?>"></div>
            <div class="col-md-6">
              <label class="form-label" for="accountPassword">New Password (optional)</label>
              <div class="input-group">
                <input type="password" name="password" id="accountPassword" class="form-control" autocomplete="new-password">
                <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this)" data-toggle-password aria-label="Show password" tabindex="-1">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($customer['address'] ?? '') ?></textarea></div>
            <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($customer['city'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control" value="<?= e($customer['state'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Pincode</label><input name="pincode" class="form-control" value="<?= e($customer['pincode'] ?? '') ?>"></div>
            <div class="col-12"><button class="btn btn-pe" type="submit">Save Changes</button></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
