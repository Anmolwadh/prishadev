<?php
/**
 * Authentication helpers - Prisha Enterprises
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function customer_logged_in(): bool
{
    return !empty($_SESSION['customer_id']);
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function current_customer(): ?array
{
    if (!customer_logged_in()) {
        return null;
    }
    static $customer = null;
    if ($customer !== null) {
        return $customer;
    }
    $stmt = getDB()->prepare('SELECT id, name, email, phone, created_at FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['customer_id']]);
    $customer = $stmt->fetch() ?: null;
    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    }
    return $customer;
}

function current_admin(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }
    static $admin = null;
    if ($admin !== null) {
        return $admin;
    }
    $stmt = getDB()->prepare('SELECT id, username, name, email, role, permissions FROM admins WHERE id = ? AND status = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['admin_id'], 'Active']);
    $admin = $stmt->fetch() ?: null;
    if (!$admin) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    }
    return $admin;
}

function is_super_admin(?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    if (!$admin) {
        return false;
    }
    return ($admin['role'] ?? '') === 'super_admin';
}

function has_permission(string $permission, ?array $admin = null): bool
{
    $admin = $admin ?? current_admin();
    if (!$admin) {
        return false;
    }
    if (is_super_admin($admin)) {
        return true;
    }
    $raw = $admin['permissions'] ?? '';
    if (empty($raw)) {
        return false;
    }
    $perms = is_array($raw) ? $raw : json_decode((string)$raw, true);
    if (!is_array($perms)) {
        return false;
    }
    return in_array($permission, $perms, true);
}

function require_permission(string $permission): void
{
    require_admin();
    if (!has_permission($permission)) {
        flash('error', 'Access denied. You do not have permission to access this page.');
        redirect('admin/dashboard.php');
    }
}

function require_super_admin(): void
{
    require_admin();
    if (!is_super_admin()) {
        flash('error', 'Access denied. Only Super Administrators can access this page.');
        redirect('admin/dashboard.php');
    }
}

function get_available_permissions(): array
{
    return [
        'orders_manage' => [
            'label' => 'Manage Orders',
            'desc' => 'View, process, and update order statuses',
            'group' => 'Orders'
        ],
        'orders_delete' => [
            'label' => 'Delete Orders',
            'desc' => 'Permanently delete orders and ordered item records',
            'group' => 'Orders'
        ],
        'products_manage' => [
            'label' => 'Manage Products & Categories',
            'desc' => 'Add, edit, and delete products, pricing, images, and categories',
            'group' => 'Catalog'
        ],
        'inventory_manage' => [
            'label' => 'Manage Inventory',
            'desc' => 'View stock alerts and adjust product stock quantities',
            'group' => 'Catalog'
        ],
        'customers_manage' => [
            'label' => 'Customers, Clients & Enquiries',
            'desc' => 'View customer accounts, manage partner clients, and bulk enquiries',
            'group' => 'Users'
        ],
        'reports_view' => [
            'label' => 'View Financial & Sales Reports',
            'desc' => 'Access revenue analytics, sales reports, and business metrics',
            'group' => 'Administration'
        ],
        'settings_manage' => [
            'label' => 'Manage Store Settings',
            'desc' => 'Update business contact, shipping charges, and store preferences',
            'group' => 'Administration'
        ],
    ];
}

function require_customer(): void
{
    if (!customer_logged_in()) {
        flash('error', 'Please login to continue.');
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? url('login.php');
        redirect('login.php');
    }
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        flash('error', 'Please login to access admin panel.');
        redirect('admin/login.php');
    }
}

function login_customer(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$customer['id'];
    $_SESSION['customer_name'] = $customer['name'];
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = $admin['name'] ?? $admin['username'];
}

function logout_customer(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
}

function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
}

function ensure_password_resets_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          email VARCHAR(100) NOT NULL,
          token_hash VARCHAR(64) NOT NULL,
          user_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
          expires_at DATETIME NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_pwd_resets_token (token_hash),
          KEY idx_pwd_resets_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ensured = true;
}

function create_password_reset_token(string $email, string $userType = 'customer'): ?string
{
    $email = trim($email);
    if (!validate_email($email)) {
        return null;
    }

    $pdo = getDB();
    ensure_password_resets_table($pdo);
    if ($userType === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND status = 'Active' LIMIT 1");
    } else {
        $userType = 'customer';
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    }
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return null;
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    // Clean up previous tokens for this email and type
    $del = $pdo->prepare('DELETE FROM password_resets WHERE email = ? AND user_type = ?');
    $del->execute([$email, $userType]);

    // Insert new token
    $ins = $pdo->prepare('INSERT INTO password_resets (email, token_hash, user_type, expires_at) VALUES (?, ?, ?, ?)');
    $ins->execute([$email, $tokenHash, $userType, $expiresAt]);

    return $rawToken;
}

function verify_password_reset_token(string $email, string $token, string $userType = 'customer'): bool
{
    $email = trim($email);
    $token = trim($token);
    if ($email === '' || $token === '') {
        return false;
    }

    $userType = ($userType === 'admin') ? 'admin' : 'customer';
    $tokenHash = hash('sha256', $token);

    $pdo = getDB();
    ensure_password_resets_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT id FROM password_resets WHERE email = ? AND token_hash = ? AND user_type = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$email, $tokenHash, $userType]);
    return (bool)$stmt->fetch();
}

function reset_user_password(string $email, string $token, string $newPassword, string $userType = 'customer'): bool
{
    if (strlen($newPassword) < 6) {
        return false;
    }

    $userType = ($userType === 'admin') ? 'admin' : 'customer';
    if (!verify_password_reset_token($email, $token, $userType)) {
        return false;
    }

    $pdo = getDB();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($userType === 'admin') {
        $update = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ? AND status = 'Active'");
    } else {
        $update = $pdo->prepare('UPDATE customers SET password = ? WHERE email = ?');
    }
    $update->execute([$hash, $email]);

    // Remove token after successful reset
    $del = $pdo->prepare('DELETE FROM password_resets WHERE email = ? AND user_type = ?');
    $del->execute([$email, $userType]);

    return true;
}

