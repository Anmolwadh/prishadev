<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
$admin = current_admin();
$adminPage = $adminPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Admin') ?> | Prisha Enterprises</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('images/favicon-32x32.png')) ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('images/favicon-16x16.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/admin.css')) ?>" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-wrap">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">
      <strong>Prisha Admin</strong>
      <div class="small opacity-75">Disposable Ecommerce</div>
    </div>
    <?php
    $rawLinks = [
      'dashboard.php' => ['Dashboard', 'fa-gauge', null],
      'orders.php' => ['Orders', 'fa-bag-shopping', 'orders_manage'],
      'products.php' => ['Products', 'fa-box', 'products_manage'],
      'categories.php' => ['Categories', 'fa-tags', 'products_manage'],
      'customers.php' => ['Customers', 'fa-users', 'customers_manage'],
      'clients.php' => ['Clients', 'fa-handshake', 'customers_manage'],
      'inventory.php' => ['Inventory', 'fa-warehouse', 'inventory_manage'],
      'bulk-enquiries.php' => ['Bulk Enquiries', 'fa-clipboard-list', 'customers_manage'],
      'reports.php' => ['Reports', 'fa-chart-line', 'reports_view'],
      'settings.php' => ['Settings', 'fa-gear', 'settings_manage'],
      'admins.php' => ['Admins & Roles', 'fa-user-shield', 'super_admin_only'],
      'change-password.php' => ['Change Password', 'fa-key', null],
      'logout.php' => ['Logout', 'fa-right-from-bracket', null],
    ];
    foreach ($rawLinks as $file => [$label, $icon, $requiredPerm]):
      if ($requiredPerm === 'super_admin_only' && !is_super_admin($admin)) {
        continue;
      }
      if ($requiredPerm !== null && $requiredPerm !== 'super_admin_only' && !has_permission($requiredPerm, $admin)) {
        continue;
      }
      $active = ($adminPage === $file || basename($_SERVER['PHP_SELF']) === $file) ? 'active' : '';
    ?>
      <a class="<?= $active ?>" href="<?= e(url('admin/' . $file)) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?></a>
    <?php endforeach; ?>
  </aside>
  <div class="admin-content">
    <div class="admin-top d-flex justify-content-between align-items-center gap-2">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-success d-lg-none" type="button" onclick="document.getElementById('adminSidebar').classList.toggle('show')"><i class="fa-solid fa-bars"></i></button>
        <div>
          <strong><?= e($pageTitle ?? 'Admin') ?></strong>
          <div class="small text-muted">
            Logged in as <?= e($admin['name'] ?? 'Admin') ?>
            <?php if (is_super_admin($admin)): ?>
              <span class="badge bg-primary ms-1" style="font-size: 0.72rem;"><i class="fa-solid fa-crown me-1"></i>Super Admin</span>
            <?php else: ?>
              <span class="badge bg-secondary ms-1" style="font-size: 0.72rem;">Admin</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if (is_super_admin($admin)): ?>
          <a href="<?= e(url('admin/admins.php')) ?>" class="btn btn-sm btn-outline-primary d-none d-md-inline-flex align-items-center gap-1">
            <i class="fa-solid fa-user-shield"></i><span>Admins & Roles</span>
          </a>
        <?php endif; ?>
        <a href="<?= e(url('admin/change-password.php')) ?>" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
          <i class="fa-solid fa-key"></i><span>Change Password</span>
        </a>
      </div>
    </div>
    <?php if ($msg = get_flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($msg = get_flash('error')): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
