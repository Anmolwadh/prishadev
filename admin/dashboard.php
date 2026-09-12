<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$threshold = (int)(get_setting('low_stock_threshold', '10') ?? 10);

$stats = [
  'total_orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
  'pending' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Pending'")->fetchColumn(),
  'processing' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Processing'")->fetchColumn(),
  'shipped' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Shipped'")->fetchColumn(),
  'delivered' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Delivered'")->fetchColumn(),
  'cancelled' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Cancelled'")->fetchColumn(),
  'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
  'low_stock' => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= $threshold")->fetchColumn(),
  'customers' => (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
  'sales' => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE order_status <> 'Cancelled'")->fetchColumn(),
];

$recent = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 10')->fetchAll();
$pageTitle = 'Dashboard';
$adminPage = 'dashboard.php';
include __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Total Orders', $stats['total_orders']],
    ['Pending', $stats['pending']],
    ['Processing', $stats['processing']],
    ['Shipped', $stats['shipped']],
    ['Delivered', $stats['delivered']],
    ['Cancelled', $stats['cancelled']],
    ['Products', $stats['products']],
    ['Low Stock', $stats['low_stock']],
    ['Customers', $stats['customers']],
    ['Total Sales', format_money($stats['sales'])],
  ];
  foreach ($cards as [$label, $value]): ?>
    <div class="col-6 col-md-4 col-xl-3">
      <div class="stat-card">
        <div class="label"><?= e($label) ?></div>
        <div class="value"><?= e((string)$value) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Recent Orders</h2>
    <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-sm btn-outline-success">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Order</th><th>Type</th><th>Customer</th><th>Phone</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $o): ?>
          <tr>
            <td><?= e($o['order_number']) ?></td>
            <td>
              <?php if (($o['order_type'] ?? 'online') === 'manual'): ?>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-store me-1"></i>Manual</span>
              <?php else: ?>
                <span class="badge bg-info text-dark"><i class="fa-solid fa-globe me-1"></i>Online</span>
              <?php endif; ?>
            </td>
            <td><?= e($o['customer_name']) ?></td>
            <td><?= e($o['phone']) ?></td>
            <td><?= e(format_money((float)$o['total'])) ?></td>
            <td><span class="badge text-bg-secondary"><?= e($o['order_status']) ?></span></td>
            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/order-details.php?id=' . (int)$o['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="8" class="text-center text-muted">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
