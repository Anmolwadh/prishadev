<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_permission('orders_manage');

$pdo = getDB();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'order_status = ?';
    $params[] = $status;
}
$sqlWhere = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $sqlWhere");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn(), $page, 20);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE $sqlWhere ORDER BY created_at DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card mb-3">
  <form class="row g-2" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search order, customer, phone"></div>
    <div class="col-md-4">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <?php foreach (['Pending','Confirmed','Processing','Shipped','Out for Delivery','Delivered','Cancelled'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-success w-100">Filter</button></div>
  </form>
</div>
<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Order Number</th><th>Customer</th><th>Phone</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= e($o['order_number']) ?></td>
            <td><?= e($o['customer_name']) ?></td>
            <td><?= e($o['phone']) ?></td>
            <td><?= e(format_money((float)$o['total'])) ?></td>
            <td><?= e($o['payment_status']) ?></td>
            <td><?= e($o['order_status']) ?></td>
            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= e(url('admin/order-details.php?id=' . (int)$o['id'])) ?>" class="btn btn-sm btn-outline-success" title="View Order">View</a>
                <?php if (has_permission('orders_delete')): ?>
                  <a href="<?= e(url('admin/delete-order.php?id=' . (int)$o['id'] . '&csrf_token=' . csrf_token())) ?>" class="btn btn-sm btn-outline-danger" title="Delete Order" onclick="return confirm('Are you sure you want to permanently delete order <?= e($o['order_number']) ?>?\nThis action cannot be undone.');">
                    <i class="fa-solid fa-trash"></i>
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
