<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? (get_setting('business_name', SITE_NAME) . ' | Disposable Products');
$metaDescription = $metaDescription ?? 'Buy quality disposable meal trays, containers, glasses, eco-friendly plates and food packaging products from Prisha Enterprises.';
$ogImage = $ogImage ?? asset('images/hero-banner.jpg');
$bodyClass = $bodyClass ?? '';
$whatsapp = get_setting('whatsapp_number', '918054798966');
$businessPhone = get_setting('phone', '+918054798966');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($metaDescription) ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($metaDescription) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:type" content="website">
  <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('images/favicon-32x32.png')) ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('images/favicon-16x16.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
  <script>
    window.PE = { baseUrl: <?= json_encode(BASE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?> };
    function togglePasswordVisibility(btn, event) {
      if (event) {
        if (typeof event.preventDefault === 'function') event.preventDefault();
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
      }
      if (!btn) return;
      var group = btn.closest('.input-group') || btn.parentElement;
      if (!group) return;
      var input = group.querySelector('input');
      if (!input) return;

      var isPass = input.type === 'password';
      var nextType = isPass ? 'text' : 'password';
      input.type = nextType;
      input.setAttribute('type', nextType);

      var icon = btn.querySelector('i');
      if (icon) {
        if (isPass) {
          icon.className = 'fa-regular fa-eye-slash';
          btn.setAttribute('aria-label', 'Hide password');
        } else {
          icon.className = 'fa-regular fa-eye';
          btn.setAttribute('aria-label', 'Show password');
        }
      }
      try { input.focus(); } catch (err) {}
    }
    window.togglePasswordVisibility = togglePasswordVisibility;
  </script>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header sticky-top">
  <div class="top-bar d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
      <div><i class="fa-solid fa-phone me-1"></i> <?= e($businessPhone) ?></div>
      <div>COD Available &nbsp;|&nbsp; Wholesale & Retail</div>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand brand-logo" href="<?= e(url('index.php')) ?>">
        <span class="brand-mark">PE</span>
        <span class="brand-text">
          <strong>Prisha Enterprises</strong>
          <small>Disposable Products</small>
        </span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link <?= active_nav('index.php') ?>" href="<?= e(url('index.php')) ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link <?= active_nav('shop.php') ?>" href="<?= e(url('shop.php')) ?>">Shop</a></li>
          <li class="nav-item"><a class="nav-link <?= active_nav('categories.php') ?>" href="<?= e(url('categories.php')) ?>">Categories</a></li>
          <li class="nav-item"><a class="nav-link <?= active_nav('about.php') ?>" href="<?= e(url('about.php')) ?>">About</a></li>
          <li class="nav-item"><a class="nav-link <?= active_nav('contact.php') ?>" href="<?= e(url('contact.php')) ?>">Contact</a></li>
        </ul>
        <div class="header-actions d-flex align-items-center gap-2">
          <form class="header-search d-none d-lg-flex" action="<?= e(url('shop.php')) ?>" method="get">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search">
            <button type="submit" class="btn btn-sm btn-search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
          </form>
          <a class="icon-btn" href="<?= e(url(customer_logged_in() ? 'account.php' : 'login.php')) ?>" title="Account">
            <i class="fa-regular fa-user"></i>
          </a>
          <a class="icon-btn cart-btn" href="<?= e(url('cart.php')) ?>" title="Cart">
            <i class="fa-solid fa-cart-shopping"></i>
            <span class="cart-count" id="cartCount"><?= (int)cart_count() ?></span>
          </a>
        </div>
        <form class="header-search d-flex d-lg-none mt-3 w-100" action="<?= e(url('shop.php')) ?>" method="get">
          <input type="search" name="q" class="form-control" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>">
          <button type="submit" class="btn btn-success ms-2"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>
    </div>
  </nav>
</header>
<main>
<?php if ($msg = get_flash('success')): ?>
  <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
  <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
