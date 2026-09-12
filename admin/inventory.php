<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$threshold = (int)(get_setting('low_stock_threshold', '10') ?? 10);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['product_id'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$stock, $id]);
    flash('success', 'Stock updated.');
    redirect('admin/inventory.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['stock_status'] ?? '');
$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($filter === 'out') $sql .= ' AND p.stock <= 0';
elseif ($filter === 'low') $sql .= " AND p.stock > 0 AND p.stock <= $threshold";
elseif ($filter === 'in') $sql .= " AND p.stock > $threshold";
$sql .= ' ORDER BY p.stock ASC, p.name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'Inventory';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card mb-3">
  <form class="row g-2" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search product / SKU"></div>
    <div class="col-md-4">
      <select name="stock_status" class="form-select">
        <option value="">All</option>
        <option value="in" <?= $filter==='in'?'selected':'' ?>>In Stock</option>
        <option value="low" <?= $filter==='low'?'selected':'' ?>>Low Stock</option>
        <option value="out" <?= $filter==='out'?'selected':'' ?>>Out of Stock</option>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-success w-100">Filter</button></div>
  </form>
  <p class="small text-muted mt-2 mb-0">Low stock threshold: <?= $threshold ?> units</p>
</div>
<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Product</th><th>SKU</th><th>Current Stock</th><th>Status</th><th>Update Stock</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p):
          $info = stock_label((int)$p['stock'], $threshold); ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td><?= e($p['sku']) ?></td>
            <td><?= (int)$p['stock'] ?></td>
            <td><span class="badge text-bg-<?= e($info['class']) ?>"><?= e($info['label']) ?></span></td>
            <td>
              <form method="post" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="number" name="stock" class="form-control form-control-sm" style="width:100px" value="<?= (int)$p['stock'] ?>" min="0">
                <button class="btn btn-sm btn-outline-success">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
