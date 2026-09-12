<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

$statuses = ['Pending','Confirmed','Processing','Shipped','Out for Delivery','Delivered','Cancelled'];
$payments = ['Pending','Paid','Failed','Refunded'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'update');
    if ($action === 'cancel') {
        $upd = $pdo->prepare("UPDATE orders SET order_status='Cancelled', updated_at=NOW() WHERE id=?");
        $upd->execute([$id]);
        flash('success', 'Order cancelled.');
        redirect('admin/order-details.php?id=' . $id);
    }
    $orderStatus = (string)($_POST['order_status'] ?? $order['order_status']);
    $paymentStatus = (string)($_POST['payment_status'] ?? $order['payment_status']);
    if (!in_array($orderStatus, $statuses, true) || !in_array($paymentStatus, $payments, true)) {
        flash('error', 'Invalid status.');
    } else {
        $upd = $pdo->prepare('UPDATE orders SET order_status=?, payment_status=?, updated_at=NOW() WHERE id=?');
        $upd->execute([$orderStatus, $paymentStatus, $id]);
        flash('success', 'Order updated.');
        redirect('admin/order-details.php?id=' . $id);
    }
    $stmt->execute([$id]);
    $order = $stmt->fetch();
}

$items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$orderItems = $items->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="admin-card mb-3">
      <h2 class="h5">Products Ordered</h2>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($orderItems as $it): ?>
              <tr>
                <td><?= e($it['product_name']) ?></td>
                <td><?= e($it['sku']) ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td><?= e(format_money((float)$it['price'])) ?></td>
                <td><?= e(format_money((float)$it['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-end">
        <div>Subtotal: <?= e(format_money((float)$order['subtotal'])) ?></div>
        <div>Shipping: <?= e(format_money((float)$order['shipping'])) ?></div>
        <div>GST: <?= e(format_money((float)($order['tax'] ?? 0))) ?></div>
        <div>Discount: <?= e(format_money((float)$order['discount'])) ?></div>
        <div class="fw-bold fs-5">Total: <?= e(format_money((float)$order['total'])) ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="admin-card mb-3">
      <h2 class="h5">Customer</h2>
      <p class="mb-1"><strong><?= e($order['customer_name']) ?></strong></p>
      <p class="mb-1"><?= e($order['phone']) ?></p>
      <p class="mb-1"><?= e((string)$order['email']) ?></p>
      <p class="mb-0"><?= e($order['address']) ?>, <?= e($order['city']) ?>, <?= e($order['state']) ?> - <?= e($order['pincode']) ?><?= $order['landmark'] ? ' (' . e($order['landmark']) . ')' : '' ?></p>
    </div>
    <div class="admin-card mb-3">
      <h2 class="h5">Update Status</h2>
      <form method="post" class="vstack gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <label class="form-label mb-0">Order Status</label>
        <select name="order_status" class="form-select">
          <?php foreach ($statuses as $st): ?><option value="<?= e($st) ?>" <?= $order['order_status']===$st?'selected':'' ?>><?= e($st) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label mb-0">Payment Status</label>
        <select name="payment_status" class="form-select">
          <?php foreach ($payments as $st): ?><option value="<?= e($st) ?>" <?= $order['payment_status']===$st?'selected':'' ?>><?= e($st) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-success" type="submit">Update Status</button>
      </form>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/invoice.php?id=' . $id)) ?>" target="_blank"><i class="fa-solid fa-print me-1"></i>Print Invoice</a>
        <?php if ($order['order_status'] !== 'Cancelled'): ?>
          <form method="post" onsubmit="return confirm('Cancel this order?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button class="btn btn-outline-warning text-dark" type="submit"><i class="fa-solid fa-ban me-1"></i>Cancel Order</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/delete-order.php')) ?>" onsubmit="return confirm('Are you sure you want to permanently delete order <?= e($order['order_number']) ?>?\nThis action cannot be undone.');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$id ?>">
          <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-trash me-1"></i>Delete Order</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
