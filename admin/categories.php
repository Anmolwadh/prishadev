<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE categories SET status = IF(status='Active','Inactive','Active') WHERE id=?")->execute([$tid]);
    flash('success', 'Category status updated.');
    redirect('admin/categories.php');
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $cnt->execute([$did]);
    if ((int)$cnt->fetchColumn() > 0) {
        flash('error', 'Cannot delete category with products. Move or delete products first.');
    } else {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$did]);
        flash('success', 'Category deleted.');
    }
    redirect('admin/categories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    $slug = slugify($name);
    if ($name === '') $errors[] = 'Name is required.';
    $imageName = $edit['image'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $up = upload_product_image($_FILES['image']);
        if (!$up['success']) {
            $errors[] = $up['message'];
        } else {
            $imageName = $up['filename'];
        }
    }
    if (!$errors) {
        if ($id > 0) {
            $pdo->prepare('UPDATE categories SET name=?, slug=?, description=?, image=?, status=? WHERE id=?')
                ->execute([$name, $slug, $description, $imageName, $status, $id]);
            flash('success', 'Category updated.');
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $slug, $description, $imageName, $status]);
            flash('success', 'Category added.');
        }
        redirect('admin/categories.php');
    }
}

$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count FROM categories c ORDER BY c.name')->fetchAll();
$pageTitle = 'Categories';
include __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="admin-card">
      <h2 class="h5 mb-3"><?= $edit ? 'Edit' : 'Add' ?> Category</h2>
      <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="vstack gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <input name="name" class="form-control" placeholder="Name" required value="<?= e($edit['name'] ?? $_POST['name'] ?? '') ?>">
        <textarea name="description" class="form-control" rows="3" placeholder="Description"><?= e($edit['description'] ?? $_POST['description'] ?? '') ?></textarea>
        <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <?php if (!empty($edit['image'])): ?>
          <img src="<?= e(product_image_url($edit['image'])) ?>" alt="" class="rounded border" style="max-height:90px;object-fit:contain;background:#f3faf4">
        <?php endif; ?>
        <select name="status" class="form-select">
          <option value="Active" <?= (($edit['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= (($edit['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="btn btn-success" type="submit">Save</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="admin-card">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Image</th><th>Name</th><th>Products</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td><img src="<?= e(product_image_url($c['image'] ?? null)) ?>" alt="" width="56" height="56" style="object-fit:contain;background:#f3faf4;border-radius:8px"></td>
                <td><?= e($c['name']) ?></td>
                <td><?= (int)$c['product_count'] ?></td>
                <td><?= e($c['status']) ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-success" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="?toggle=<?= (int)$c['id'] ?>">Toggle</a>
                  <a class="btn btn-sm btn-outline-danger" href="?delete=<?= (int)$c['id'] ?>" onclick="return confirm('Delete category?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
