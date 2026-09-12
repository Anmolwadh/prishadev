<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$q = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
$sqlWhere = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $sqlWhere");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn(), $page, 20);
$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $sqlWhere ORDER BY p.id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Products';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
  <form class="row g-2 flex-grow-1" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search product name or SKU"></div>
    <div class="col-md-4">
      <select name="category_id" class="form-select">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-success w-100">Filter</button></div>
  </form>
  <a href="<?= e(url('admin/add-product.php')) ?>" class="btn btn-success">Add Product</a>
</div>
<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Image</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>GST</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><img src="<?= e(product_image_url($p['image'])) ?>" alt="" width="48" height="48" style="object-fit:cover;border-radius:8px"></td>
            <td><?= e($p['name']) ?></td>
            <td><?= e($p['sku']) ?></td>
            <td><?= e((string)$p['category_name']) ?></td>
            <td><?= e(format_money((float)$p['price'])) ?></td>
            <td><?= e(rtrim(rtrim(number_format((float)($p['gst'] ?? 0), 2), '0'), '.') ?: '0') ?>%</td>
            <td><?= (int)$p['stock'] ?></td>
            <td><?= e($p['status']) ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/edit-product.php?id=' . (int)$p['id'])) ?>">Edit</a>
              <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/delete-product.php?id=' . (int)$p['id'])) ?>" onclick="return confirm('Delete this product?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="9" class="text-center text-muted">No products found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
