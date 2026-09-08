<?php
/**
 * Core helper functions - Prisha Enterprises
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Session timeout
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
    $isAdmin = !empty($_SESSION['admin_id']);
    $isCustomer = !empty($_SESSION['customer_id']);
    session_unset();
    session_destroy();
    session_start();
    if ($isAdmin) {
        $_SESSION['flash_error'] = 'Session expired. Please login again.';
    }
}
$_SESSION['last_activity'] = time();

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    }
    exit;
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $cleanPath = url($path);
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $cleanPath;
    }
    return 'https://prisha-enterprises.in' . $cleanPath;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        if (is_ajax()) {
            json_response(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
        }
        flash('error', 'Invalid security token. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        if ($back !== '') {
            header('Location: ' . $back);
            exit;
        }
        redirect('index.php');
    }
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function client_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= strtoupper(substr($part, 0, 1));
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'C';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function round_money(float|int|string $amount): float
{
    return (float)round((float)$amount, 0);
}

function format_money(float|int|string $amount): string
{
    return '₹' . number_format(round_money($amount), 0);
}

function product_gst_rate(array $product): float
{
    $gst = (float)($product['gst'] ?? 0);
    if ($gst < 0) {
        return 0.0;
    }
    if ($gst > 100) {
        return 100.0;
    }
    return $gst;
}

function product_price_incl_gst(array $product): float
{
    $price = (float)($product['price'] ?? 0);
    return round_money($price + ($price * product_gst_rate($product) / 100));
}

function calc_discount(float $mrp, float $price): float
{
    if ($mrp <= 0 || $price >= $mrp) {
        return 0.0;
    }
    return round((($mrp - $price) / $mrp) * 100, 2);
}

function &settings_cache(): array
{
    static $cache = [];
    return $cache;
}

function get_setting(string $key, ?string $default = null): ?string
{
    $cache = &settings_cache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = getDB()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
    $cache = &settings_cache();
    $cache[$key] = $value;
}

function product_image_url(?string $image): string
{
    if (!$image) {
        return asset('images/placeholder-product.jpg');
    }
    $name = basename($image);
    $url = asset('images/' . rawurlencode($name));
    if (str_starts_with($name, 'prod_')) {
        $path = BASE_PATH . '/assets/images/' . $name;
        if (is_file($path)) {
            return $url . '?v=' . filemtime($path);
        }
        return $url . '?v=' . time();
    }
    if (is_file(BASE_PATH . '/assets/images/' . $name)) {
        return asset('images/' . rawurlencode($name));
    }
    if (is_file(UPLOAD_DIR . $name)) {
        return UPLOAD_URL . rawurlencode($name);
    }
    $svg = preg_replace('/\.(jpe?g|png|webp)$/i', '.svg', $name);
    if (is_string($svg) && $svg !== $name && is_file(BASE_PATH . '/assets/images/' . $svg)) {
        return asset('images/' . rawurlencode($svg));
    }
    return asset('images/placeholder-product.jpg');
}

function cart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

function cart_count(): int
{
    $count = 0;
    foreach (cart() as $item) {
        $count += (int)($item['qty'] ?? 0);
    }
    return $count;
}

function cart_subtotal(): float
{
    $subtotal = 0.0;
    foreach (cart() as $item) {
        $subtotal += ((float)$item['price']) * ((int)$item['qty']);
    }
    return round($subtotal, 2);
}

function is_rajpura_delivery(?string $city = null, ?string $address = null, ?string $pincode = null): bool
{
    $haystack = strtolower(trim(($city ?? '') . ' ' . ($address ?? '') . ' ' . ($pincode ?? '')));
    if ($haystack === '') {
        return false;
    }
    // Match Rajpura city/area in city or address
    if (str_contains($haystack, 'rajpura')) {
        return true;
    }
    // Common Rajpura PIN area
    if (preg_match('/\b14040[0-9]\b/', $haystack)) {
        return true;
    }
    return false;
}

function shipping_amount(float $subtotal, ?string $city = null, ?string $address = null, ?string $pincode = null): float
{
    $charge = (float)(get_setting('shipping_charge', '60') ?? 60);
    if ($subtotal <= 0) {
        return 0.0;
    }
    // Free delivery for Rajpura location
    if (is_rajpura_delivery($city, $address, $pincode)) {
        return 0.0;
    }
    return round($charge, 2);
}

function cart_tax(): float
{
    $tax = 0.0;
    foreach (cart() as $item) {
        $price = (float)$item['price'];
        $qty = (int)$item['qty'];
        $gst = (float)($item['gst'] ?? 0);
        $incl = round_money($price + ($price * $gst / 100));
        $tax += ($incl * $qty) - ($price * $qty);
    }
    return round_money($tax);
}

function cart_totals(?string $city = null, ?string $address = null, ?string $pincode = null): array
{
    $subtotal = cart_subtotal();
    $shipping = shipping_amount($subtotal, $city, $address, $pincode);
    $tax = cart_tax();
    return [
        'subtotal' => round_money($subtotal),
        'shipping' => round_money($shipping),
        'tax'      => $tax,
        'discount' => 0.0,
        'total'    => round_money($subtotal + $tax + $shipping),
        'is_rajpura' => is_rajpura_delivery($city, $address, $pincode),
    ];
}

function generate_order_number(PDO $pdo): string
{
    $prefix = 'PE' . date('Ymd');
    $stmt = $pdo->prepare(
        "SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/(\d{4})$/', (string)$last, $m)) {
        $seq = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image is too large. Maximum size is 5MB.',
        UPLOAD_ERR_PARTIAL => 'Image upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write uploaded file.',
        default => 'Upload failed. Please try a JPG, PNG or WEBP image under 5MB.',
    };
}

function detect_image_mime(string $path): ?string
{
    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime'])) {
        return strtolower((string)$info['mime']);
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? strtolower($mime) : null;
    }
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        return is_string($mime) ? strtolower($mime) : null;
    }
    return null;
}

function upload_product_image(array $file): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => upload_error_message($error), 'filename' => null];
    }
    if (($file['size'] ?? 0) > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'Image must be under 5MB.', 'filename' => null];
    }
    if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid upload. Please try again.', 'filename' => null];
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'];
    $ext = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, WEBP allowed.', 'filename' => null];
    }

    $mime = detect_image_mime($file['tmp_name']);
    if ($mime === null || !in_array($mime, $allowedMime, true)) {
        return ['success' => false, 'message' => 'Invalid image type. Please upload a real JPG, PNG or WEBP file.', 'filename' => null];
    }

    $safeExt = $ext === 'jpeg' ? 'jpg' : $ext;
    $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $safeExt;
    $assetDir = BASE_PATH . '/assets/images/';
    $destAsset = $assetDir . $filename;
    $tmp = (string)$file['tmp_name'];

    if (!is_dir($assetDir) && !@mkdir($assetDir, 0755, true) && !is_dir($assetDir)) {
        return ['success' => false, 'message' => 'Could not create image folder. Set assets/images to 755.', 'filename' => null];
    }
    if (!is_writable($assetDir)) {
        return ['success' => false, 'message' => 'Image folder is not writable. In File Manager set public_html/assets/images permission to 755 or 775.', 'filename' => null];
    }

    $saved = @copy($tmp, $destAsset);
    if (!$saved && is_uploaded_file($tmp)) {
        $saved = @move_uploaded_file($tmp, $destAsset);
    }
    if (!$saved) {
        return ['success' => false, 'message' => 'Could not save image. Set public_html/assets/images to 755.', 'filename' => null];
    }
    @chmod($destAsset, 0644);

    return ['success' => true, 'message' => 'Uploaded.', 'filename' => $filename];
}

function paginate(int $total, int $page, int $perPage = 12): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
    ];
}

function order_status_steps(): array
{
    return [
        'Pending'           => 1,
        'Confirmed'         => 2,
        'Processing'        => 3,
        'Shipped'           => 4,
        'Out for Delivery'  => 5,
        'Delivered'         => 6,
        'Cancelled'         => 0,
    ];
}

function stock_label(int $stock, ?int $threshold = null): array
{
    $threshold = $threshold ?? (int)(get_setting('low_stock_threshold', '10') ?? 10);
    if ($stock <= 0) {
        return ['label' => 'Out of Stock', 'class' => 'danger'];
    }
    if ($stock <= $threshold) {
        return ['label' => 'Low Stock', 'class' => 'warning'];
    }
    return ['label' => 'In Stock', 'class' => 'success'];
}

function validate_phone(string $phone): bool
{
    $phone = preg_replace('/\s+/', '', $phone) ?? '';
    return (bool)preg_match('/^[6-9]\d{9}$/', $phone);
}

function validate_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_pincode(string $pincode): bool
{
    return (bool)preg_match('/^\d{6}$/', $pincode);
}

function current_page(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}

function active_nav(string $page): string
{
    return current_page() === $page ? 'active' : '';
}

function send_email(string $to, string $subject, string $htmlBody, ?string $altBody = null): bool
{
    $businessName = get_setting('business_name', SITE_NAME) ?? SITE_NAME;
    $fromEmail = get_setting('email', 'info@prishaenterprises.com') ?: 'info@prishaenterprises.com';

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($businessName), $fromEmail);
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $headerStr = implode("\r\n", $headers);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $sent = @mail($to, $encodedSubject, $htmlBody, $headerStr);
    if (!$sent) {
        error_log(sprintf('[Email] Failed to send email to %s with subject: %s', $to, $subject));
    }
    return $sent;
}

