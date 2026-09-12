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
    $links = [
      'dashboard.php' => ['Dashboard', 'fa-gauge'],
      'orders.php' => ['Orders', 'fa-bag-shopping'],
      'products.php' => ['Products', 'fa-box'],
      'categories.php' => ['Categories', 'fa-tags'],
      'customers.php' => ['Customers', 'fa-users'],
      'clients.php' => ['Clients', 'fa-handshake'],
      'inventory.php' => ['Inventory', 'fa-warehouse'],
      'bulk-enquiries.php' => ['Bulk Enquiries', 'fa-clipboard-list'],
      'reports.php' => ['Reports', 'fa-chart-line'],
      'settings.php' => ['Settings', 'fa-gear'],
      'change-password.php' => ['Change Password', 'fa-key'],
      'logout.php' => ['Logout', 'fa-right-from-bracket'],
    ];
    foreach ($links as $file => [$label, $icon]):
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
          <div class="small text-muted">Logged in as <?= e($admin['name'] ?? 'Admin') ?></div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= e(url('admin/change-password.php')) ?>" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
          <i class="fa-solid fa-key"></i><span>Change Password</span>
        </a>
      </div>
    </div>
    <?php if ($msg = get_flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($msg = get_flash('error')): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
