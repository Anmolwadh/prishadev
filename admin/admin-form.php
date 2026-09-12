<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$isEdit = ($id > 0);
$allPerms = get_available_permissions();

$errors = [];
$adminUser = [
    'username' => '',
    'name' => '',
    'email' => '',
    'role' => 'admin',
    'permissions' => [],
    'status' => 'Active',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, username, name, email, role, permissions, status FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('error', 'Administrator account not found.');
        redirect('admin/admins.php');
    }
    $adminUser = $found;
    $raw = $adminUser['permissions'] ?? '';
    $adminUser['permissions'] = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'admin');
    $status = (string)($_POST['status'] ?? 'Active');
    $selectedPerms = (array)($_POST['permissions'] ?? []);

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters containing only letters, numbers, dots, dashes, or underscores.';
    }

    if ($email === '' || !validate_email($email)) {
        $errors[] = 'Valid email address is required.';
    }

    if (!$isEdit && strlen($password) < 6) {
        $errors[] = 'Password is required and must be at least 6 characters.';
    } elseif ($isEdit && $password !== '' && strlen($password) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }

    // Check unique username
    if ($isEdit) {
        $chk = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id != ? LIMIT 1');
        $chk->execute([$username, $id]);
    } else {
        $chk = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $chk->execute([$username]);
    }
    if ($chk->fetch()) {
        $errors[] = 'This username is already taken.';
    }

    // Protect primary admin from demotion or deactivation
    if ($isEdit && $id === 1) {
        $role = 'super_admin';
        $status = 'Active';
    }

    if (!in_array($role, ['super_admin', 'admin'], true)) {
        $role = 'admin';
    }

    if (!$errors) {
        // Sanitize permissions
        $validKeys = array_keys($allPerms);
        $cleanPerms = array_values(array_intersect($selectedPerms, $validKeys));
        $permsJson = ($role === 'super_admin') ? null : json_encode($cleanPerms);

        if ($isEdit) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE admins SET name = ?, username = ?, email = ?, password = ?, role = ?, permissions = ?, status = ? WHERE id = ?');
                $upd->execute([$name, $username, $email, $hash, $role, $permsJson, $status, $id]);
            } else {
                $upd = $pdo->prepare('UPDATE admins SET name = ?, username = ?, email = ?, role = ?, permissions = ?, status = ? WHERE id = ?');
                $upd->execute([$name, $username, $email, $role, $permsJson, $status, $id]);
            }
            flash('success', "Administrator '{$name}' updated successfully.");
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO admins (name, username, email, password, role, permissions, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$name, $username, $email, $hash, $role, $permsJson, $status]);
            flash('success', "New administrator '{$name}' created successfully.");
        }
        redirect('admin/admins.php');
    }

    // Keep form inputs on error
    $adminUser['name'] = $name;
    $adminUser['username'] = $username;
    $adminUser['email'] = $email;
    $adminUser['role'] = $role;
    $adminUser['status'] = $status;
    $adminUser['permissions'] = $selectedPerms;
}

// Group permissions
$groupedPerms = [];
foreach ($allPerms as $k => $info) {
    $g = $info['group'];
    $groupedPerms[$g][$k] = $info;
}

$pageTitle = $isEdit ? 'Edit Administrator' : 'Add New Administrator';
$adminPage = 'admins.php';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h5 mb-1"><i class="fa-solid fa-user-shield text-success me-2"></i><?= e($pageTitle) ?></h2>
    <div class="text-muted small"><?= $isEdit ? 'Update account details and customize module access permissions.' : 'Create a new administrative user and configure their role and permissions.' ?></div>
  </div>
  <a href="<?= e(url('admin/admins.php')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fa-solid fa-arrow-left me-1"></i>Back to Admins
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" autocomplete="off">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Account Details Card -->
    <div class="col-lg-5">
      <div class="admin-card">
        <h3 class="h6 fw-bold mb-3 border-bottom pb-2">Account Details</h3>

        <div class="mb-3">
          <label class="form-label" for="adminName">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="adminName" class="form-control" required value="<?= e($adminUser['name']) ?>" placeholder="e.g. John Doe">
        </div>

        <div class="mb-3">
          <label class="form-label" for="adminUsername">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" id="adminUsername" class="form-control" required value="<?= e($adminUser['username']) ?>" placeholder="e.g. jdoe" <?= ($isEdit && $id === 1) ? 'readonly' : '' ?>>
          <div class="form-text">Unique username for logging into the admin portal.</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="adminEmail">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" id="adminEmail" class="form-control" required value="<?= e($adminUser['email']) ?>" placeholder="e.g. jdoe@prishaenterprises.com">
        </div>

        <div class="mb-3">
          <label class="form-label" for="adminPassword">
            Password <?= $isEdit ? '<span class="text-muted fw-normal">(leave blank to keep current)</span>' : '<span class="text-danger">*</span>' ?>
          </label>
          <div class="input-group">
            <input type="password" name="password" id="adminPassword" class="form-control" <?= $isEdit ? '' : 'required' ?> minlength="6" autocomplete="new-password" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Minimum 6 characters' ?>">
            <button class="btn btn-outline-secondary password-toggle-btn" type="button" onclick="togglePasswordVisibility(this, event)" data-toggle-password aria-label="Show password" tabindex="-1">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <select name="role" id="roleSelector" class="form-select" <?= ($isEdit && $id === 1) ? 'disabled' : '' ?>>
            <option value="admin" <?= $adminUser['role'] === 'admin' ? 'selected' : '' ?>>Admin (Custom Permissions)</option>
            <option value="super_admin" <?= $adminUser['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin (Unrestricted Access)</option>
          </select>
          <?php if ($isEdit && $id === 1): ?>
            <input type="hidden" name="role" value="super_admin">
            <div class="form-text text-muted">Primary Super Admin cannot be changed.</div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Status <span class="text-danger">*</span></label>
          <select name="status" class="form-select" <?= ($isEdit && $id === 1) ? 'disabled' : '' ?>>
            <option value="Active" <?= $adminUser['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $adminUser['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
          <?php if ($isEdit && $id === 1): ?>
            <input type="hidden" name="status" value="Active">
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Permissions Card -->
    <div class="col-lg-7">
      <div class="admin-card">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
          <div>
            <h3 class="h6 fw-bold mb-0">Module Permissions</h3>
            <small class="text-muted">Specify which sections this admin can access.</small>
          </div>
          <div id="quickPermButtons" class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllPerms(true)">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllPerms(false)">Clear All</button>
          </div>
        </div>

        <div id="superAdminNote" class="alert alert-info d-none">
          <i class="fa-solid fa-crown me-2"></i><strong>Super Admin</strong> automatically has unrestricted access to all modules, actions, and settings. No individual permissions needed.
        </div>

        <div id="permissionsContainer">
          <?php foreach ($groupedPerms as $groupName => $perms): ?>
            <div class="mb-4">
              <h4 class="h6 text-uppercase fw-bold text-muted small mb-2"><?= e($groupName) ?></h4>
              <div class="vstack gap-2">
                <?php foreach ($perms as $key => $info): ?>
                  <?php $checked = in_array($key, $adminUser['permissions'], true); ?>
                  <div class="p-2 border rounded bg-light-subtle d-flex align-items-start gap-2">
                    <div class="form-check mt-1">
                      <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="perm_<?= e($key) ?>" <?= $checked ? 'checked' : '' ?>>
                    </div>
                    <label class="form-check-label w-100" for="perm_<?= e($key) ?>" style="cursor: pointer;">
                      <div class="fw-semibold text-dark"><?= e($info['label']) ?></div>
                      <div class="small text-muted"><?= e($info['desc']) ?></div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <hr class="my-4">

        <div class="d-flex gap-2">
          <button class="btn btn-success" type="submit">
            <i class="fa-solid fa-floppy-disk me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Administrator' ?>
          </button>
          <a href="<?= e(url('admin/admins.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function updateRoleUI() {
  const roleSelect = document.getElementById('roleSelector');
  const isSuper = roleSelect.value === 'super_admin';
  const superNote = document.getElementById('superAdminNote');
  const permsCont = document.getElementById('permissionsContainer');
  const quickBtns = document.getElementById('quickPermButtons');

  if (isSuper) {
    superNote.classList.remove('d-none');
    permsCont.classList.add('opacity-50');
    quickBtns.classList.add('d-none');
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.disabled = true);
  } else {
    superNote.classList.add('d-none');
    permsCont.classList.remove('opacity-50');
    quickBtns.classList.remove('d-none');
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.disabled = false);
  }
}

function toggleAllPerms(check) {
  document.querySelectorAll('.perm-checkbox:not(:disabled)').forEach(cb => cb.checked = check);
}

document.getElementById('roleSelector').addEventListener('change', updateRoleUI);
document.addEventListener('DOMContentLoaded', updateRoleUI);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
