<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $sku = trim((string)($_POST['sku'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $short = trim((string)($_POST['short_description'] ?? ''));
    $mrp = (float)($_POST['mrp'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $discount = max(0, min(100, (float)($_POST['discount'] ?? 0)));
    $stock = (int)($_POST['stock'] ?? 0);
    $pack = trim((string)($_POST['pack_size'] ?? ''));
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    $featured = !empty($_POST['featured']) ? 1 : 0;
    $gst = max(0, min(100, (float)($_POST['gst'] ?? 18)));
    
    if ($discount > 0 && $mrp > 0 && $price <= 0) {
        $price = round($mrp * (1 - ($discount / 100)), 2);
    } elseif ($discount <= 0 && $mrp > 0 && $price > 0 && $price < $mrp) {
        $discount = calc_discount($mrp, $price);
    }
    
    $imageName = $product['image'];

    if ($name === '' || $sku === '' || $categoryId <= 0) $errors[] = 'Name, SKU and category are required.';

    if (!empty($_FILES['image']['name'])) {
        $up = upload_product_image($_FILES['image']);
        if (!$up['success']) $errors[] = $up['message'];
        else {
            if ($imageName && str_starts_with((string)$imageName, 'prod_')) {
                foreach ([UPLOAD_DIR . $imageName, BASE_PATH . '/assets/images/' . $imageName] as $old) {
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
            }
            $imageName = $up['filename'];
        }
    }

    if (!$errors) {
        try {
            $upd = $pdo->prepare(
                'UPDATE products SET category_id=?, name=?, sku=?, description=?, short_description=?, price=?, mrp=?, discount=?, gst=?, stock=?, pack_size=?, image=?, status=?, featured=? WHERE id=?'
            );
            $upd->execute([$categoryId, $name, $sku, $description, $short, $price, $mrp, $discount, $gst, $stock, $pack, $imageName, $status, $featured, $id]);
            flash('success', 'Product updated.');
            redirect('admin/edit-product.php?id=' . $id);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Could not update product. SKU may already exist.';
        }
    }
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

$pageTitle = 'Edit Product';
include __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-8"><label class="form-label">Name *</label><input name="name" class="form-control" required value="<?= e($product['name']) ?>"></div>
    <div class="col-md-4"><label class="form-label">SKU *</label><input name="sku" class="form-control" required value="<?= e($product['sku']) ?>"></div>
    <div class="col-md-6">
      <label class="form-label">Category *</label>
      <select name="category_id" class="form-select" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">MRP</label><input type="number" step="0.01" name="mrp" id="mrp" class="form-control" value="<?= e((string)$product['mrp']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="price" id="price" class="form-control" value="<?= e((string)$product['price']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Discount %</label><input type="number" step="0.01" min="0" max="100" name="discount" id="discount" class="form-control" value="<?= e((string)$product['discount']) ?>"></div>
    <div class="col-md-3"><label class="form-label">GST %</label><input type="number" step="0.01" min="0" max="100" name="gst" class="form-control" value="<?= e((string)($product['gst'] ?? '18')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Stock</label><input type="number" name="stock" class="form-control" value="<?= e((string)$product['stock']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Pack Size</label><input name="pack_size" class="form-control" value="<?= e((string)$product['pack_size']) ?>"></div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="Active" <?= $product['status']==='Active'?'selected':'' ?>>Active</option>
        <option value="Inactive" <?= $product['status']==='Inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="featured" id="featured" <?= !empty($product['featured'])?'checked':'' ?>><label class="form-check-label" for="featured">Featured</label></div></div>
    <div class="col-12"><label class="form-label">Short Description</label><input name="short_description" class="form-control" value="<?= e((string)$product['short_description']) ?>"></div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"><?= e((string)$product['description']) ?></textarea></div>
    <div class="col-md-6">
      <label class="form-label">Image (JPG, PNG or WEBP, max 5MB)</label>
      <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <img class="mt-2 rounded" src="<?= e(product_image_url($product['image'])) ?>" width="120" alt="" style="max-height:120px;object-fit:contain;background:#f3faf4">
    </div>
    <div class="col-12"><button class="btn btn-success" type="submit">Update Product</button> <a href="<?= e(url('admin/products.php')) ?>" class="btn btn-outline-secondary">Cancel</a></div>
  </form>
</div>
<script>
const mrpInput = document.getElementById('mrp');
const priceInput = document.getElementById('price');
const discountInput = document.getElementById('discount');

discountInput.addEventListener('input', function() {
  const m = parseFloat(mrpInput.value) || 0;
  const d = parseFloat(this.value);
  if (m > 0 && !isNaN(d) && d >= 0 && d <= 100) {
    priceInput.value = (m - (m * d / 100)).toFixed(2);
  }
});

priceInput.addEventListener('input', function() {
  const m = parseFloat(mrpInput.value) || 0;
  const p = parseFloat(this.value) || 0;
  if (m > 0 && p >= 0) {
    if (p < m) {
      discountInput.value = (((m - p) / m) * 100).toFixed(2);
    } else {
      discountInput.value = '0';
    }
  }
});

mrpInput.addEventListener('input', function() {
  const m = parseFloat(this.value) || 0;
  const d = parseFloat(discountInput.value);
  const p = parseFloat(priceInput.value) || 0;
  if (m > 0 && !isNaN(d) && d > 0 && d <= 100) {
    priceInput.value = (m - (m * d / 100)).toFixed(2);
  } else if (m > 0 && p > 0 && p < m) {
    discountInput.value = (((m - p) / m) * 100).toFixed(2);
  }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
