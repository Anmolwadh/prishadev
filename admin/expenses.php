<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();

$currentMonth = date('Y-m');
$month = trim((string)($_GET['month'] ?? $currentMonth));
$category = trim((string)($_GET['category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

// Validate month format (YYYY-MM or 'all')
if ($month !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = $currentMonth;
}

$prevMonth = $month !== 'all' ? date('Y-m', strtotime($month . '-01 -1 month')) : $currentMonth;
$nextMonth = $month !== 'all' ? date('Y-m', strtotime($month . '-01 +1 month')) : $currentMonth;
$monthDisplay = $month !== 'all' ? date('F Y', strtotime($month . '-01')) : 'All Time';

$where = ['1=1'];
$params = [];

if ($month !== 'all') {
    $where[] = "DATE_FORMAT(bill_date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}

if ($status !== '' && in_array($status, ['Paid', 'Pending'], true)) {
    $where[] = 'payment_status = ?';
    $params[] = $status;
}

if ($q !== '') {
    $where[] = '(title LIKE ? OR bill_number LIKE ? OR vendor_name LIKE ? OR notes LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sqlWhere = implode(' AND ', $where);

// Summary statistics for current filter
$statStmt = $pdo->prepare(
    "SELECT
      COUNT(*) AS total_bills,
      COALESCE(SUM(amount), 0) AS total_expenses,
      COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END), 0) AS total_paid,
      COALESCE(SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END), 0) AS total_pending
     FROM expenses
     WHERE $sqlWhere"
);
$statStmt->execute($params);
$stats = $statStmt->fetch() ?: [
    'total_bills' => 0,
    'total_expenses' => 0.0,
    'total_paid' => 0.0,
    'total_pending' => 0.0,
];

// Category breakdown for current filter
$catStmt = $pdo->prepare(
    "SELECT category, COUNT(*) AS count, SUM(amount) AS total
     FROM expenses
     WHERE $sqlWhere
     GROUP BY category
     ORDER BY total DESC"
);
$catStmt->execute($params);
$categoryBreakdown = $catStmt->fetchAll();

// Distinct categories for dropdown
$allCategories = $pdo->query("SELECT DISTINCT category FROM expenses ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$defaultCategories = [
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
$categoryOptions = array_values(array_unique(array_merge($defaultCategories, $allCategories)));

// Fetch filtered expenses list
$stmt = $pdo->prepare(
    "SELECT * FROM expenses
     WHERE $sqlWhere
     ORDER BY bill_date DESC, id DESC"
);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$pageTitle = 'Expenses & Bills';
$adminPage = 'expenses.php';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h4 mb-0"><i class="fa-solid fa-receipt text-success me-2"></i>Monthly Expenses &amp; Bills</h1>
    <div class="text-muted small">
      Viewing: <strong><?= e($monthDisplay) ?></strong>
      <?php if ($month !== 'all'): ?>
        &bull; <?= count($expenses) ?> bill(s) recorded
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <?php if ($month !== 'all'): ?>
      <div class="btn-group btn-group-sm">
        <a href="<?= e(url('admin/expenses.php?month=' . $prevMonth . ($category ? '&category=' . urlencode($category) : '') . ($status ? '&status=' . urlencode($status) : ''))) ?>" class="btn btn-outline-secondary" title="Previous Month">
          <i class="fa-solid fa-chevron-left me-1"></i><?= e(date('M Y', strtotime($prevMonth . '-01'))) ?>
        </a>
        <a href="<?= e(url('admin/expenses.php?month=' . $currentMonth)) ?>" class="btn btn-outline-secondary <?= $month === $currentMonth ? 'active' : '' ?>">
          Current Month
        </a>
        <a href="<?= e(url('admin/expenses.php?month=' . $nextMonth . ($category ? '&category=' . urlencode($category) : '') . ($status ? '&status=' . urlencode($status) : ''))) ?>" class="btn btn-outline-secondary" title="Next Month">
          <?= e(date('M Y', strtotime($nextMonth . '-01'))) ?><i class="fa-solid fa-chevron-right ms-1"></i>
        </a>
      </div>
    <?php endif; ?>
    <a href="<?= e(url('admin/expense-add.php' . ($month !== 'all' ? '?month=' . $month : ''))) ?>" class="btn btn-success btn-sm">
      <i class="fa-solid fa-plus me-1"></i>Add New Bill / Expense
    </a>
  </div>
</div>

<?php if ($successMsg = get_flash('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-circle-check me-2"></i><?= e($successMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($errorMsg = get_flash('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Monthly Financial KPI Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card border-start border-4 border-primary">
      <div class="label text-uppercase small text-muted">Total Expenses (<?= e($monthDisplay) ?>)</div>
      <div class="value fs-4 fw-bold text-dark mt-1"><?= e(format_money((float)$stats['total_expenses'])) ?></div>
      <div class="small text-muted mt-1"><i class="fa-solid fa-file-invoice me-1"></i><?= (int)$stats['total_bills'] ?> recorded bill(s)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card border-start border-4 border-success">
      <div class="label text-uppercase small text-muted">Paid Bills</div>
      <div class="value fs-4 fw-bold text-success mt-1"><?= e(format_money((float)$stats['total_paid'])) ?></div>
      <div class="small text-success mt-1"><i class="fa-solid fa-circle-check me-1"></i>Settled</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card border-start border-4 border-warning">
      <div class="label text-uppercase small text-muted">Pending / Due Bills</div>
      <div class="value fs-4 fw-bold text-warning mt-1"><?= e(format_money((float)$stats['total_pending'])) ?></div>
      <div class="small text-warning mt-1"><i class="fa-solid fa-clock me-1"></i>Awaiting payment</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card border-start border-4 border-info">
      <div class="label text-uppercase small text-muted">Expense Categories</div>
      <div class="value fs-4 fw-bold text-info mt-1"><?= count($categoryBreakdown) ?></div>
      <div class="small text-muted mt-1"><i class="fa-solid fa-tags me-1"></i>Categories active</div>
    </div>
  </div>
</div>

<!-- Filter and Month Switcher Card -->
<div class="admin-card mb-3">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label small fw-bold mb-1">Select Month</label>
      <div class="input-group">
        <input type="month" name="month" class="form-control" value="<?= e($month !== 'all' ? $month : date('Y-m')) ?>">
        <?php if ($month === 'all'): ?>
          <span class="input-group-text small bg-light">All Months</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label small fw-bold mb-1">Category</label>
      <select name="category" class="form-select">
        <option value="">All Categories</option>
        <?php foreach ($categoryOptions as $cat): ?>
          <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-sm-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Payment Status</label>
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
        <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>
    <div class="col-12 col-sm-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Title, bill #, vendor">
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-success flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filter</button>
      <a href="<?= e(url('admin/expenses.php?month=' . date('Y-m'))) ?>" class="btn btn-outline-secondary" title="Reset to Current Month"><i class="fa-solid fa-rotate-left"></i></a>
      <?php if ($month !== 'all'): ?>
        <a href="<?= e(url('admin/expenses.php?month=all')) ?>" class="btn btn-outline-primary" title="View All Months">All</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if (!empty($categoryBreakdown) && (float)$stats['total_expenses'] > 0): ?>
  <!-- Category Spending Breakdown (Visual Progress) -->
  <div class="admin-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-chart-pie text-secondary me-2"></i><?= e($monthDisplay) ?> Category Breakdown</h2>
      <span class="small text-muted">Total: <?= e(format_money((float)$stats['total_expenses'])) ?></span>
    </div>
    <div class="row g-2">
      <?php foreach ($categoryBreakdown as $cb): 
        $pct = round(((float)$cb['total'] / max(1, (float)$stats['total_expenses'])) * 100, 1);
      ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="p-2 border rounded bg-light">
            <div class="d-flex justify-content-between align-items-center mb-1 small">
              <span class="fw-semibold text-truncate me-2"><?= e($cb['category']) ?></span>
              <span class="text-dark fw-bold"><?= e(format_money((float)$cb['total'])) ?> <span class="text-muted fw-normal">(<?= $pct ?>%)</span></span>
            </div>
            <div class="progress" style="height: 6px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Expenses List Table -->
<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Bills &amp; Expenses (<?= count($expenses) ?>)</h2>
    <div class="small text-muted">Sorted by Bill Date (latest first)</div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle table-hover">
      <thead>
        <tr class="table-light">
          <th>Date</th>
          <th>Bill / Invoice #</th>
          <th>Expense Title</th>
          <th>Category</th>
          <th>Vendor / Payee</th>
          <th>Payment Method</th>
          <th class="text-end">Amount</th>
          <th class="text-center">Status</th>
          <th class="text-center">Receipt</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expenses as $exp): ?>
          <tr>
            <td class="text-nowrap">
              <span class="fw-semibold"><?= e(date('d M Y', strtotime($exp['bill_date']))) ?></span>
            </td>
            <td>
              <?php if (!empty($exp['bill_number'])): ?>
                <code><?= e($exp['bill_number']) ?></code>
              <?php else: ?>
                <span class="text-muted small">&mdash;</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-bold text-dark"><?= e($exp['title']) ?></div>
              <?php if (!empty($exp['notes'])): ?>
                <div class="text-muted small text-truncate" style="max-width: 260px;" title="<?= e($exp['notes']) ?>">
                  <i class="fa-regular fa-note-sticky me-1"></i><?= e($exp['notes']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-secondary"><?= e($exp['category']) ?></span>
            </td>
            <td>
              <?= !empty($exp['vendor_name']) ? e($exp['vendor_name']) : '<span class="text-muted small">&mdash;</span>' ?>
            </td>
            <td>
              <span class="small"><i class="fa-solid fa-wallet text-muted me-1"></i><?= e($exp['payment_method']) ?></span>
            </td>
            <td class="text-end fw-bold fs-6">
              <?= e(format_money((float)$exp['amount'])) ?>
            </td>
            <td class="text-center">
              <?php if ($exp['payment_status'] === 'Paid'): ?>
                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if (!empty($exp['receipt_file'])): 
                $isPdf = strtolower(pathinfo($exp['receipt_file'], PATHINFO_EXTENSION)) === 'pdf';
                $receiptUrl = url('uploads/receipts/' . $exp['receipt_file']);
              ?>
                <a href="<?= e($receiptUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View Receipt">
                  <i class="fa-solid <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-image text-primary' ?> me-1"></i>View
                </a>
              <?php else: ?>
                <span class="text-muted small">&mdash;</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= e(url('admin/expense-edit.php?id=' . (int)$exp['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Edit Bill">
                  <i class="fa-solid fa-pen-to-square"></i>
                </a>
                <form method="post" action="<?= e(url('admin/expense-delete.php')) ?>" onsubmit="return confirm('Are you sure you want to delete this bill (<?= e($exp['title']) ?> - <?= e(format_money((float)$exp['amount'])) ?>)?\nThis cannot be undone.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Bill">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?>
          <tr>
            <td colspan="10" class="text-center py-4 text-muted">
              <i class="fa-solid fa-receipt fs-2 mb-2 d-block opacity-50"></i>
              No bills or expenses found for <strong><?= e($monthDisplay) ?></strong>.
              <div class="mt-2">
                <a href="<?= e(url('admin/expense-add.php' . ($month !== 'all' ? '?month=' . $month : ''))) ?>" class="btn btn-sm btn-success">
                  <i class="fa-solid fa-plus me-1"></i>Add Bill for this Month
                </a>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
