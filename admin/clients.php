<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE clients SET status = IF(status='Active','Inactive','Active') WHERE id=?")->execute([$tid]);
    flash('success', 'Client status updated.');
    redirect('admin/clients.php');
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$did]);
    flash('success', 'Client deleted.');
    redirect('admin/clients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    if ($name === '') {
        $errors[] = 'Client name is required.';
    }
    if (!$errors) {
        if ($id > 0) {
            $pdo->prepare('UPDATE clients SET name=?, description=?, sort_order=?, status=? WHERE id=?')
                ->execute([$name, $description, $sortOrder, $status, $id]);
            flash('success', 'Client updated.');
        } else {
            $pdo->prepare('INSERT INTO clients (name, description, sort_order, status) VALUES (?, ?, ?, ?)')
                ->execute([$name, $description, $sortOrder, $status]);
            flash('success', 'Client added.');
        }
        redirect('admin/clients.php');
    }
}

$clients = $pdo->query('SELECT * FROM clients ORDER BY sort_order ASC, name ASC')->fetchAll();
$pageTitle = 'Clients';
$adminPage = 'clients.php';
include __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="admin-card">
      <h2 class="h5 mb-3"><?= $edit ? 'Edit' : 'Add' ?> Client</h2>
      <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post" class="vstack gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <input name="name" class="form-control" placeholder="Client name" required value="<?= e($edit['name'] ?? $_POST['name'] ?? '') ?>">
        <textarea name="description" class="form-control" rows="3" placeholder="Short description"><?= e($edit['description'] ?? $_POST['description'] ?? '') ?></textarea>
        <input type="number" name="sort_order" class="form-control" placeholder="Display order" value="<?= e((string)($edit['sort_order'] ?? $_POST['sort_order'] ?? '0')) ?>">
        <select name="status" class="form-select">
          <option value="Active" <?= (($edit['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= (($edit['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="btn btn-success" type="submit">Save Client</button>
        <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('admin/clients.php')) ?>">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="admin-card">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Client</th><th>Order</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($clients as $c): ?>
              <tr>
                <td>
                  <strong><?= e((string)$c['name']) ?></strong>
                  <?php if (!empty($c['description'])): ?>
                    <div class="small text-muted"><?= e((string)$c['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= (int)$c['sort_order'] ?></td>
                <td><?= e((string)$c['status']) ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-success" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="?toggle=<?= (int)$c['id'] ?>">Toggle</a>
                  <a class="btn btn-sm btn-outline-danger" href="?delete=<?= (int)$c['id'] ?>" onclick="return confirm('Delete this client?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
              <tr><td colspan="4" class="text-muted">No clients yet. Add one using the form.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
