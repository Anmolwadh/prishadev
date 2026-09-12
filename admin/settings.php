<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$keys = [
    'business_name' => 'Business Name',
    'phone' => 'Phone',
    'email' => 'Email',
    'address' => 'Address',
    'shipping_charge' => 'Shipping Charge',
    'free_shipping_minimum' => 'Free Shipping Minimum',
    'whatsapp_number' => 'WhatsApp Number',
    'low_stock_threshold' => 'Low Stock Threshold',
    'razorpay_enabled' => 'Razorpay Status',
    'razorpay_mode' => 'Razorpay Mode',
    'razorpay_key_id' => 'Razorpay Key ID',
    'razorpay_key_secret' => 'Razorpay Key Secret',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($keys as $key => $label) {
        if (isset($_POST[$key])) {
            set_setting($key, trim((string)$_POST[$key]));
        }
    }
    flash('success', 'Settings saved successfully.');
    redirect('admin/settings.php');
}

$values = [];
foreach ($keys as $key => $label) {
    $values[$key] = get_setting($key, '');
}

$pageTitle = 'Settings';
include __DIR__ . '/includes/header.php';
?>
<?php if ($success = get_flash('success')): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fa-solid fa-circle-check me-2"></i><?= e($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <!-- General Store Settings -->
  <div class="admin-card mb-4">
    <h2 class="h5 mb-3 border-bottom pb-2"><i class="fa-solid fa-store text-success me-2"></i>Store Configuration</h2>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Business Name</label>
        <input name="business_name" class="form-control" value="<?= e((string)$values['business_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Phone</label>
        <input name="phone" class="form-control" value="<?= e((string)$values['phone']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Email</label>
        <input name="email" class="form-control" value="<?= e((string)$values['email']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">WhatsApp Number</label>
        <input name="whatsapp_number" class="form-control" value="<?= e((string)$values['whatsapp_number']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= e((string)$values['address']) ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Standard Shipping Charge (₹)</label>
        <input type="number" step="0.01" name="shipping_charge" class="form-control" value="<?= e((string)$values['shipping_charge']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Free Shipping Minimum (₹)</label>
        <input type="number" step="0.01" name="free_shipping_minimum" class="form-control" value="<?= e((string)$values['free_shipping_minimum']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Low Stock Threshold</label>
        <input type="number" name="low_stock_threshold" class="form-control" value="<?= e((string)$values['low_stock_threshold']) ?>">
      </div>
    </div>
  </div>

  <!-- Razorpay Payment Gateway Settings -->
  <div class="admin-card mb-4 border-start border-4 border-primary">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-0"><i class="fa-solid fa-credit-card text-primary me-2"></i>Razorpay Payment Gateway</h2>
        <div class="text-muted small">Accept UPI (Google Pay, PhonePe, Paytm), Debit/Credit Cards, and Net Banking online.</div>
      </div>
      <span class="badge <?= ($values['razorpay_enabled'] ?? '1') === '1' ? 'bg-success' : 'bg-secondary' ?> fs-6">
        <?= ($values['razorpay_enabled'] ?? '1') === '1' ? '<i class="fa-solid fa-check me-1"></i>Active' : 'Disabled' ?>
      </span>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Razorpay Online Payments</label>
        <select name="razorpay_enabled" class="form-select">
          <option value="1" <?= ($values['razorpay_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Enabled (Allow Online Payments)</option>
          <option value="0" <?= ($values['razorpay_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Disabled (COD Only)</option>
        </select>
        <div class="form-text">When disabled, customers can only choose Cash on Delivery (COD).</div>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">Gateway Mode</label>
        <select name="razorpay_mode" class="form-select">
          <option value="test" <?= ($values['razorpay_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test Mode (Sandbox / Testing)</option>
          <option value="live" <?= ($values['razorpay_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live Mode (Real Customer Payments)</option>
        </select>
        <div class="form-text">Use Test Mode to test payments with dummy UPI/card details.</div>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">Razorpay Key ID <span class="text-danger">*</span></label>
        <input type="text" name="razorpay_key_id" class="form-control font-monospace" value="<?= e((string)($values['razorpay_key_id'] ?: 'rzp_test_TbDuFqvITs1a92')) ?>" placeholder="rzp_test_... or rzp_live_...">
        <div class="form-text">Your API Key ID from Razorpay Dashboard.</div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Razorpay Key Secret <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="password" id="rzpSecret" name="razorpay_key_secret" class="form-control font-monospace" value="<?= e((string)($values['razorpay_key_secret'] ?: 'Q5g6n8xT05olD7kAM2GNEUEA')) ?>" placeholder="Key Secret">
          <button type="button" class="btn btn-outline-secondary" onclick="toggleRzpSecret()"><i id="rzpSecretEye" class="fa-solid fa-eye"></i></button>
        </div>
        <div class="form-text">Secret key used to cryptographically verify payment signatures.</div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Razorpay Webhooks / Verification URL</label>
        <input type="text" readonly class="form-control bg-light" value="<?= e(url('ajax/razorpay-verify.php')) ?>">
        <div class="form-text">Webhook / Verification endpoint handled automatically by the platform.</div>
      </div>
    </div>
  </div>

  <!-- Admin Security Card -->
  <div class="admin-card mb-4">
    <h2 class="h5 mb-3 border-bottom pb-2"><i class="fa-solid fa-shield-halved text-secondary me-2"></i>Admin Security</h2>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <div class="fw-semibold">Change Administrator Password</div>
        <div class="text-muted small">Update your admin login password securely with current password verification.</div>
      </div>
      <a href="<?= e(url('admin/change-password.php')) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-key me-1"></i>Change Password
      </a>
    </div>
  </div>

  <button class="btn btn-success px-4 py-2" type="submit"><i class="fa-solid fa-check me-1"></i>Save All Settings</button>
</form>

<script>
function toggleRzpSecret() {
  const input = document.getElementById('rzpSecret');
  const icon = document.getElementById('rzpSecretEye');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
