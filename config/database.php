<?php
/**
 * Database configuration - Prisha Enterprises
 */
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'prisha_enterprises');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Application base URL (no trailing slash)
 * Change if your folder name differs under htdocs
 */
// Empty for PHP built-in server (http://localhost:8080)
// Use '/prisha-enterprises' if running under Apache/XAMPP htdocs
define('BASE_URL', '');
define('BASE_PATH', dirname(__DIR__));

define('SITE_NAME', 'Prisha Enterprises');
define('SESSION_TIMEOUT', 7200); // 2 hours
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', BASE_PATH . '/uploads/products/');
define('UPLOAD_URL', BASE_URL . '/uploads/products/');

date_default_timezone_set('Asia/Kolkata');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        ensure_clients_schema($pdo);
        ensure_product_gst_schema($pdo);
        ensure_password_resets_schema($pdo);
        ensure_admin_permissions_schema($pdo);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (php_sapi_name() !== 'cli') {
            include BASE_PATH . '/includes/error-500.php';
            exit;
        }
        throw $e;
    }

    return $pdo;
}

function ensure_clients_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS clients (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(150) NOT NULL,
          description TEXT NULL,
          sort_order INT NOT NULL DEFAULT 0,
          status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY idx_clients_status (status)
        ) ENGINE=InnoDB"
    );
    $count = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $insert = $pdo->prepare('INSERT INTO clients (name, description, sort_order, status) VALUES (?, ?, ?, ?)');
    $insert->execute(['Fine Dine Restaurant', 'Premium dining partner using our meal trays, containers and packaging for dine-in and takeaway service.', 1, 'Active']);
    $insert->execute(['Chandu Chat', 'Popular chat and snack outlet supplied with disposable plates, glasses and food packaging for daily service.', 2, 'Active']);
    $insert->execute(['Agra Chat Bhandar', 'Trusted chat bhandar partner supplied with disposable plates, glasses and packaging for everyday service.', 3, 'Active']);
}

function ensure_product_gst_schema(PDO $pdo): void
{
    $hasGst = $pdo->query("SHOW COLUMNS FROM products LIKE 'gst'")->fetch();
    if (!$hasGst) {
        $pdo->exec('ALTER TABLE products ADD COLUMN gst DECIMAL(5,2) NOT NULL DEFAULT 18.00 AFTER discount');
    }
    $hasTax = $pdo->query("SHOW COLUMNS FROM orders LIKE 'tax'")->fetch();
    if (!$hasTax) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER shipping');
    }
}

function ensure_password_resets_schema(PDO $pdo): void
{
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
}

function ensure_admin_permissions_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $hasRole = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'")->fetch();
    if (!$hasRole) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin' AFTER name");
    }

    $hasPermissions = $pdo->query("SHOW COLUMNS FROM admins LIKE 'permissions'")->fetch();
    if (!$hasPermissions) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN permissions TEXT NULL AFTER role");
    }

    $pdo->exec("UPDATE admins SET role = 'super_admin' WHERE id = 1 OR username = 'admin'");
    $ensured = true;
}

