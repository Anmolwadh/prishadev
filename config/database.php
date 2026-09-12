<?php
/**
 * Database configuration - Prisha Enterprises
 */
declare(strict_types=1);

// Auto-detect environment: Local (Windows / localhost / 127.0.0.1) vs Live Server (Hostomy / Linux)
$httpHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$isLocalEnv = stripos(PHP_OS, 'WIN') !== false
    || str_contains($httpHost, 'localhost')
    || str_contains($httpHost, '127.0.0.1');

if ($isLocalEnv) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'prisha_enterprises');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'prishaen00_shop');
    define('DB_USER', 'prishaen00_admin');
    define('DB_PASS', 'Anmol@777');
}
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
    } catch (PDOException $e) {
        // Fallback for Hostomy / Linux if localhost socket vs 127.0.0.1 port differs
        if (DB_HOST === 'localhost') {
            try {
                $fallbackDsn = 'mysql:host=127.0.0.1;dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $pdo = new PDO($fallbackDsn, DB_USER, DB_PASS, $options);
            } catch (PDOException) {
                // Keep original exception
            }
        }
        if (!$pdo instanceof PDO) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            if (php_sapi_name() !== 'cli') {
                include BASE_PATH . '/includes/error-500.php';
                exit;
            }
            throw $e;
        }
    }

    ensure_clients_schema($pdo);
    ensure_product_gst_schema($pdo);
    ensure_password_resets_schema($pdo);
    ensure_order_type_schema($pdo);
    ensure_expenses_schema($pdo);
    ensure_razorpay_schema($pdo);

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

function ensure_order_type_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $hasCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_type'")->fetch();
    if (!$hasCol) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN order_type ENUM('online', 'manual') NOT NULL DEFAULT 'online' AFTER order_number");
        $pdo->exec("UPDATE orders SET order_type = 'manual' WHERE notes LIKE '%Manual%' OR payment_method IN ('Cash', 'Cheque')");
    }
    $ensured = true;
}

function ensure_expenses_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expenses (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          bill_number VARCHAR(50) DEFAULT NULL,
          title VARCHAR(255) NOT NULL,
          category VARCHAR(100) NOT NULL,
          amount DECIMAL(10,2) NOT NULL,
          bill_date DATE NOT NULL,
          payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
          payment_status ENUM('Paid', 'Pending') NOT NULL DEFAULT 'Paid',
          vendor_name VARCHAR(150) DEFAULT NULL,
          receipt_file VARCHAR(255) DEFAULT NULL,
          notes TEXT DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY idx_expenses_bill_date (bill_date),
          KEY idx_expenses_category (category),
          KEY idx_expenses_status (payment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ensured = true;
}

function ensure_razorpay_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'razorpay_order_id'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE orders
            ADD COLUMN razorpay_order_id VARCHAR(100) NULL AFTER payment_method,
            ADD COLUMN razorpay_payment_id VARCHAR(100) NULL AFTER razorpay_order_id,
            ADD COLUMN razorpay_signature VARCHAR(255) NULL AFTER razorpay_payment_id,
            ADD KEY idx_orders_rzp_payment (razorpay_payment_id)");
    }

    // Seed default settings if missing
    $seedSettings = [
        'razorpay_enabled' => '1',
        'razorpay_key_id' => 'rzp_test_TbDuFqvITs1a92',
        'razorpay_key_secret' => 'Q5g6n8xT05olD7kAM2GNEUEA',
        'razorpay_mode' => 'test',
    ];

    $check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($seedSettings as $k => $v) {
        $check->execute([$k]);
        if (!$check->fetch()) {
            $insert->execute([$k, $v]);
        }
    }

    $ensured = true;
}
