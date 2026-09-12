<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    flash('error', 'Expense record not found.');
    redirect('admin/expenses.php');
}

$standardCategories = [
    'Rent & Warehouse',
    'Electricity & Utilities',
    'Raw Materials / Stock',
    'Packaging & Supplies',
    'Transportation & Courier',
    'Staff Salaries & Wages',
    'Maintenance & Repairs',
    'Marketing & Ads',
    'Office & Stationery',
    'Taxes & Legal',
    'Other / Miscellaneous'
];
$existingCategories = $pdo->query("SELECT DISTINCT category FROM expenses ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$allCategories = array_values(array_unique(array_merge($standardCategories, $existingCategories)));

$errors = [];
$title = (string)$expense['title'];
$category = (string)$expense['category'];
$customCategory = '';
$amount = (string)$expense['amount'];
$billDate = (string)$expense['bill_date'];
$billNumber = (string)($expense['bill_number'] ?? '');
$vendorName = (string)($expense['vendor_name'] ?? '');
$paymentMethod = (string)($expense['payment_method'] ?? 'Cash');
$paymentStatus = (string)($expense['payment_status'] ?? 'Paid');
$notes = (string)($expense['notes'] ?? '');
$receiptFile = (string)($expense['receipt_file'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $selectedCategory = trim((string)($_POST['category'] ?? ''));
    $customCategory = trim((string)($_POST['custom_category'] ?? ''));
    $amountInput = trim((string)($_POST['amount'] ?? ''));
    $billDate = trim((string)($_POST['bill_date'] ?? ''));
    $billNumber = trim((string)($_POST['bill_number'] ?? ''));
    $vendorName = trim((string)($_POST['vendor_name'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
    $paymentStatus = trim((string)($_POST['payment_status'] ?? 'Paid'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $removeReceipt = !empty($_POST['remove_receipt']);

    // Category determination
    $finalCategory = $selectedCategory === '__custom__' ? $customCategory : $selectedCategory;
    if ($finalCategory === '') {
        $finalCategory = 'Other / Miscellaneous';
    }

    // Validation
    if ($title === '') {
        $errors[] = 'Expense title is required.';
    }
    if ($amountInput === '' || !is_numeric($amountInput) || (float)$amountInput <= 0) {
        $errors[] = 'Please enter a valid expense amount greater than 0.';
    }
    if ($billDate === '' || !strtotime($billDate)) {
        $errors[] = 'Valid bill date is required.';
    }
    if (!in_array($paymentStatus, ['Paid', 'Pending'], true)) {
        $paymentStatus = 'Paid';
    }

    $newReceipt = $receiptFile;

    // Handle removal of receipt
    if ($removeReceipt && $receiptFile !== '') {
        $oldPath = BASE_PATH . '/uploads/receipts/' . $receiptFile;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
        $newReceipt = null;
    }

    // Handle new upload
    if (!empty($_FILES['receipt']['name'])) {
        $uploadResult = upload_expense_receipt($_FILES['receipt']);
        if (!$uploadResult['success']) {
            $errors[] = $uploadResult['message'];
        } else {
            // Delete old file if present
            if ($receiptFile !== '') {
                $oldPath = BASE_PATH . '/uploads/receipts/' . $receiptFile;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $newReceipt = $uploadResult['filename'];
        }
    }

    if (!$errors) {
        $numAmount = round((float)$amountInput, 2);
        try {
            $stmt = $pdo->prepare(
                'UPDATE expenses
                 SET bill_number = ?,
                     title = ?,
                     category = ?,
                     amount = ?,
                     bill_date = ?,
                     payment_method = ?,
                     payment_status = ?,
                     vendor_name = ?,
                     receipt_file = ?,
                     notes = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $billNumber !== '' ? $billNumber : null,
                $title,
                $finalCategory,
                $numAmount,
                $billDate,
                $paymentMethod,
                $paymentStatus,
                $vendorName !== '' ? $vendorName : null,
                $newReceipt,
                $notes !== '' ? $notes : null,
                $id,
            ]);

            $targetMonth = substr($billDate, 0, 7);
            flash('success', 'Expense bill updated successfully.');
            redirect('admin/expenses.php?month=' . $targetMonth);
        } catch (PDOException $e) {
            error_log('Error updating expense: ' . $e->getMessage());
            $errors[] = 'Database error while updating expense.';
        }
    }
}

$pageTitle = 'Edit Expense / Bill';
$adminPage = 'expenses.php';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0"><i class="fa-solid fa-pen-to-square text-secondary me-2"></i>Edit Expense / Bill</h1>
    <div class="text-muted small">Update bill details, payment status, or receipt</div>
  </div>
  <a href="<?= e(url('admin/expenses.php?month=' . substr($billDate, 0, 7))) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fa-solid fa-arrow-left me-1"></i>Back to Expenses
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please fix the following:</div>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-card">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Basic Details -->
    <div class="col-12 col-md-6">
      <label class="form-label fw-bold">Expense Title / Description <span class="text-danger">*</span></label>
      <input type="text" name="title" class="form-control" value="<?= e($title) ?>" required autofocus>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label fw-bold">Bill Date <span class="text-danger">*</span></label>
      <input type="date" name="bill_date" class="form-control" value="<?= e($billDate) ?>" required>
      <div class="form-text">Month: <?= e(date('F Y', strtotime($billDate))) ?></div>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label fw-bold">Amount (₹) <span class="text-danger">*</span></label>
      <div class="input-group">
        <span class="input-group-text">₹</span>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control fw-bold" value="<?= e((string)$amount) ?>" required>
      </div>
    </div>

    <!-- Category -->
    <div class="col-12 col-md-6">
      <label class="form-label fw-bold">Expense Category <span class="text-danger">*</span></label>
      <select name="category" id="categorySelect" class="form-select" onchange="toggleCustomCat(this.value)">
        <?php foreach ($allCategories as $cat): ?>
          <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
        <option value="__custom__">+ Add New / Custom Category...</option>
      </select>
      <div id="customCatWrapper" class="mt-2" style="display: none;">
        <input type="text" name="custom_category" class="form-control" placeholder="Enter custom category name">
      </div>
    </div>

    <!-- Vendor / Payee -->
    <div class="col-12 col-md-6">
      <label class="form-label fw-bold">Vendor / Supplier / Payee Name</label>
      <input type="text" name="vendor_name" class="form-control" value="<?= e($vendorName) ?>" placeholder="e.g. Landlord Name, Power Corp, Supplier">
    </div>

    <!-- Bill / Invoice Number -->
    <div class="col-12 col-md-4">
      <label class="form-label fw-bold">Bill / Invoice Number</label>
      <input type="text" name="bill_number" class="form-control" value="<?= e($billNumber) ?>" placeholder="e.g. INV-2026-091">
    </div>

    <!-- Payment Method -->
    <div class="col-12 col-md-4">
      <label class="form-label fw-bold">Payment Method</label>
      <select name="payment_method" class="form-select">
        <?php foreach (['Cash', 'Bank Transfer (NEFT/RTGS/IMPS)', 'UPI (GPay / PhonePe / Paytm)', 'Cheque', 'Credit / Debit Card', 'Other'] as $pm): ?>
          <option value="<?= e($pm) ?>" <?= $paymentMethod === $pm ? 'selected' : '' ?>><?= e($pm) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Payment Status -->
    <div class="col-12 col-md-4">
      <label class="form-label fw-bold">Payment Status</label>
      <select name="payment_status" class="form-select">
        <option value="Paid" <?= $paymentStatus === 'Paid' ? 'selected' : '' ?>>Paid (Settled)</option>
        <option value="Pending" <?= $paymentStatus === 'Pending' ? 'selected' : '' ?>>Pending (Due / Unpaid)</option>
      </select>
    </div>

    <!-- Receipt Upload -->
    <div class="col-12 col-md-6">
      <label class="form-label fw-bold">Attach or Replace Receipt</label>
      <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
      <div class="form-text">Max 5MB. Supports JPG, PNG, WEBP, and PDF.</div>
      <?php if (!empty($receiptFile)): 
        $isPdf = strtolower(pathinfo($receiptFile, PATHINFO_EXTENSION)) === 'pdf';
        $receiptUrl = url('uploads/receipts/' . $receiptFile);
      ?>
        <div class="mt-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between">
          <div>
            <i class="fa-solid <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-image text-primary' ?> me-1"></i>
            <a href="<?= e($receiptUrl) ?>" target="_blank" class="fw-semibold small">View Current Receipt</a>
          </div>
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="remove_receipt" id="removeReceipt" value="1">
            <label class="form-check-label text-danger small" for="removeReceipt">Remove</label>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Notes -->
    <div class="col-12 col-md-6">
      <label class="form-label fw-bold">Notes / Remarks</label>
      <textarea name="notes" rows="2" class="form-control"><?= e($notes) ?></textarea>
    </div>
  </div>

  <hr class="my-4">

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url('admin/expenses.php?month=' . substr($billDate, 0, 7))) ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-success px-4">
      <i class="fa-solid fa-save me-1"></i>Update Bill / Expense
    </button>
  </div>
</form>

<script>
function toggleCustomCat(val) {
  var wrapper = document.getElementById('customCatWrapper');
  if (val === '__custom__') {
    wrapper.style.display = 'block';
  } else {
    wrapper.style.display = 'none';
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
