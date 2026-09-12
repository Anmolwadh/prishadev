<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_permission('orders_delete');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$pdo = getDB();

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
} else {
    $token = (string)($_GET['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        flash('error', 'Security token invalid or expired. Please try again.');
        redirect('admin/orders.php');
    }
}

if ($id <= 0) {
    flash('error', 'Invalid order ID.');
    redirect('admin/orders.php');
}

$stmt = $pdo->prepare('SELECT id, order_number FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

try {
    $pdo->beginTransaction();

    // Delete child items first to satisfy foreign keys
    $delItems = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
    $delItems->execute([$id]);

    // Delete parent order
    $delOrder = $pdo->prepare('DELETE FROM orders WHERE id = ?');
    $delOrder->execute([$id]);

    $pdo->commit();

    flash('success', 'Order #' . $order['order_number'] . ' has been permanently deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Failed to delete order: ' . $e->getMessage());
    flash('error', 'Failed to delete order. Please try again.');
}

redirect('admin/orders.php');
