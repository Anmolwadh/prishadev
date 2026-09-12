<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$range = (string)($_GET['range'] ?? '30');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');

$today = date('Y-m-d');
switch ($range) {
    case 'today':
        $start = $today;
        $end = $today;
        break;
    case 'yesterday':
        $start = date('Y-m-d', strtotime('-1 day'));
        $end = $start;
        break;
    case '7':
        $start = date('Y-m-d', strtotime('-6 days'));
        $end = $today;
        break;
    case 'month':
        $start = date('Y-m-01');
        $end = $today;
        break;
    case 'custom':
        $start = $from !== '' ? $from : date('Y-m-d', strtotime('-29 days'));
        $end = $to !== '' ? $to : $today;
        break;
    default:
        $start = date('Y-m-d', strtotime('-29 days'));
        $end = $today;
}

if (strtotime($start) > strtotime($end)) {
    [$start, $end] = [$end, $start];
}

$stmt = $pdo->prepare(
    "SELECT
      COUNT(*) AS total_orders,
      COALESCE(SUM(CASE WHEN order_status <> 'Cancelled' THEN total ELSE 0 END), 0) AS total_sales,
      COALESCE(SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END), 0) AS delivered_orders,
      COALESCE(SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
      COALESCE(SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_orders
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$start, $end]);
$summary = $stmt->fetch() ?: [
    'total_orders' => 0,
    'total_sales' => 0,
    'delivered_orders' => 0,
    'cancelled_orders' => 0,
    'pending_orders' => 0,
];

$salesRows = $pdo->prepare(
    "SELECT DATE(created_at) AS d,
            COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN order_status <> 'Cancelled' THEN total ELSE 0 END), 0) AS sales
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);
$salesRows->execute([$start, $end]);
$chart = $salesRows->fetchAll() ?: [];

$chartLabels = [];
$chartSales = [];
foreach ($chart as $row) {
    $chartLabels[] = (string)$row['d'];
    $chartSales[] = (float)$row['sales'];
}

$pageTitle = 'Reports';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card mb-3">
  <form class="row g-2" method="get">
    <div class="col-md-3">
      <select name="range" class="form-select">
        <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="yesterday" <?= $range === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
        <option value="7" <?= $range === '7' ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30" <?= $range === '30' ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
      </select>
    </div>
    <div class="col-md-3"><input type="date" name="from" class="form-control" value="<?= e($start) ?>"></div>
    <div class="col-md-3"><input type="date" name="to" class="form-control" value="<?= e($end) ?>"></div>
    <div class="col-md-3"><button class="btn btn-success w-100" type="submit">Apply</button></div>
  </form>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([
    ['Total Orders', (int)$summary['total_orders']],
    ['Total Sales', format_money((float)$summary['total_sales'])],
    ['Delivered', (int)$summary['delivered_orders']],
    ['Cancelled', (int)$summary['cancelled_orders']],
    ['Pending', (int)$summary['pending_orders']],
  ] as [$label, $val]): ?>
    <div class="col-6 col-md">
      <div class="stat-card">
        <div class="label"><?= e($label) ?></div>
        <div class="value"><?= e((string)$val) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="admin-card mb-3">
  <h2 class="h5">Sales Chart</h2>
  <canvas id="salesChart" height="100"></canvas>
</div>
<div class="admin-card">
  <h2 class="h5">Sales Table</h2>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Date</th><th>Orders</th><th>Sales</th></tr></thead>
      <tbody>
        <?php foreach ($chart as $row): ?>
          <tr>
            <td><?= e(date('d M Y', strtotime((string)$row['d']))) ?></td>
            <td><?= (int)$row['orders'] ?></td>
            <td><?= e(format_money((float)$row['sales'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$chart): ?>
          <tr><td colspan="3" class="text-muted">No data for selected range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var canvas = document.getElementById('salesChart');
  if (!canvas || typeof Chart === 'undefined') return;
  new Chart(canvas, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        label: 'Sales (INR)',
        data: <?= json_encode($chartSales) ?>,
        borderColor: '#2e7d32',
        backgroundColor: 'rgba(46,125,50,0.15)',
        fill: true,
        tension: 0.3
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: true } }
    }
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
