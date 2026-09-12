<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$pdo = getDB();
$currentAdminId = (int)$_SESSION['admin_id'];

// Handle action (toggle status / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $targetId = (int)($_POST['target_id'] ?? 0);

    if ($targetId > 0 && $targetId !== $currentAdminId) {
        if ($action === 'toggle_status') {
            $st = $pdo->prepare('SELECT status, username FROM admins WHERE id = ?');
            $st->execute([$targetId]);
            $target = $st->fetch();
            if ($target) {
                $newStatus = ($target['status'] === 'Active') ? 'Inactive' : 'Active';
                $upd = $pdo->prepare('UPDATE admins SET status = ? WHERE id = ?');
                $upd->execute([$newStatus, $targetId]);
                flash('success', "Status for '{$target['username']}' updated to {$newStatus}.");
            }
        } elseif ($action === 'delete') {
            // Prevent deleting root admin
            if ($targetId === 1) {
                flash('error', 'The primary administrator account cannot be deleted.');
            } else {
                $del = $pdo->prepare('DELETE FROM admins WHERE id = ?');
                $del->execute([$targetId]);
                flash('success', 'Administrator account deleted successfully.');
            }
        }
    } else {
        flash('error', 'Invalid action or you cannot modify your own active account status.');
    }
    redirect('admin/admins.php');
}

$allPerms = get_available_permissions();

$stmt = $pdo->query('SELECT id, username, name, email, role, permissions, status, created_at FROM admins ORDER BY id ASC');
$admins = $stmt->fetchAll();

$pageTitle = 'Admins & Permissions';
$adminPage = 'admins.php';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h5 mb-1"><i class="fa-solid fa-user-shield text-success me-2"></i>Administrator Accounts & Permissions</h2>
    <div class="text-muted small">Manage administrative users, roles, and granular module access.</div>
  </div>
  <a href="<?= e(url('admin/admin-form.php')) ?>" class="btn btn-success">
    <i class="fa-solid fa-plus me-1"></i>Add New Admin
  </a>
</div>

<div class="admin-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>Granted Permissions</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $u): ?>
          <?php
            $isSuper = ($u['role'] === 'super_admin');
            $rawPerms = $u['permissions'] ?? '';
            $perms = is_array($rawPerms) ? $rawPerms : (json_decode((string)$rawPerms, true) ?: []);
          ?>
          <tr>
            <td>
              <div class="fw-bold"><?= e($u['name']) ?></div>
              <small class="text-muted">@<?= e($u['username']) ?></small>
            </td>
            <td><?= e((string)$u['email']) ?></td>
            <td>
              <?php if ($isSuper): ?>
                <span class="badge bg-primary"><i class="fa-solid fa-crown me-1"></i>Super Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary">Admin</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($isSuper): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                  <i class="fa-solid fa-check-double me-1"></i>Full Access (All Modules)
                </span>
              <?php else: ?>
                <?php if (empty($perms)): ?>
                  <span class="text-muted small">No permissions granted</span>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-1" style="max-width: 320px;">
                    <?php foreach ($perms as $p): ?>
                      <?php if (isset($allPerms[$p])): ?>
                        <span class="badge bg-light text-dark border small" title="<?= e($allPerms[$p]['desc']) ?>">
                          <?= e($allPerms[$p]['label']) ?>
                        </span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['status'] === 'Active'): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-danger">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap small text-muted">
              <?= e(date('d M Y', strtotime((string)$u['created_at']))) ?>
            </td>
            <td class="text-end text-nowrap">
              <div class="d-inline-flex gap-1">
                <a href="<?= e(url('admin/admin-form.php?id=' . (int)$u['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit Admin & Permissions">
                  <i class="fa-solid fa-pen-to-square"></i>
                </a>
                <?php if ((int)$u['id'] !== $currentAdminId && (int)$u['id'] !== 1): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Change status for this administrator?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm <?= $u['status'] === 'Active' ? 'btn-outline-warning text-dark' : 'btn-outline-success' ?>" type="submit" title="<?= $u['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>">
                      <i class="fa-solid <?= $u['status'] === 'Active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                    </button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this administrator? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete Admin">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
