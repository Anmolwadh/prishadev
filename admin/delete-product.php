<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if ($product) {
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
        @unlink(UPLOAD_DIR . $product['image']);
    }
    flash('success', 'Product deleted.');
} else {
    flash('error', 'Product not found.');
}
redirect('admin/products.php');
