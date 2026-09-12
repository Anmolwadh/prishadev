<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        flash('error', 'Customer not found.');
        redirect('admin/customers.php');
    }
    $orders = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC');
    $orders->execute([$id]);
    $orderRows = $orders->fetchAll();
    $pageTitle = 'Customer Details';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="admin-card mb-3">
      <h2 class="h5"><?= e($customer['name']) ?></h2>
      <p class="mb-1"><?= e($customer['email']) ?> · <?= e($customer['phone']) ?></p>
      <p class="mb-0 text-muted">Registered: <?= e(date('d M Y', strtotime($customer['created_at']))) ?></p>
    </div>
    <div class="admin-card">
      <h3 class="h6">Order History</h3>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orderRows as $o): ?>
              <tr>
                <td><?= e($o['order_number']) ?></td>
                <td><?= e(format_money((float)$o['total'])) ?></td>
                <td><?= e($o['order_status']) ?></td>
                <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                <td><a href="<?= e(url('admin/order-details.php?id=' . (int)$o['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$orderRows): ?><tr><td colspan="5" class="text-muted">No orders.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <a href="<?= e(url('admin/customers.php')) ?>" class="btn btn-outline-secondary">Back</a>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
$params = [];
if ($q !== '') {
    $where = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$stmt = $pdo->prepare(
    "SELECT c.*,
      (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS total_orders,
      (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.order_status <> 'Cancelled') AS total_spending
     FROM customers c WHERE $where ORDER BY c.created_at DESC"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card mb-3">
  <form method="get" class="row g-2">
    <div class="col-md-8"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone"></div>
    <div class="col-md-4"><button class="btn btn-success w-100">Search</button></div>
  </form>
</div>
<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th>Orders</th><th>Spending</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td><?= e($c['email']) ?></td>
            <td><?= e($c['phone']) ?></td>
            <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
            <td><?= (int)$c['total_orders'] ?></td>
            <td><?= e(format_money((float)$c['total_spending'])) ?></td>
            <td><a class="btn btn-sm btn-outline-success" href="?id=<?= (int)$c['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
