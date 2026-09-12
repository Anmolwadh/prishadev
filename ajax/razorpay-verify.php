<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$razorpayOrderId = trim((string)($input['razorpay_order_id'] ?? ''));
$razorpayPaymentId = trim((string)($input['razorpay_payment_id'] ?? ''));
$razorpaySignature = trim((string)($input['razorpay_signature'] ?? ''));

if ($razorpayOrderId === '' || $razorpayPaymentId === '' || $razorpaySignature === '') {
    json_response(['success' => false, 'message' => 'Missing Razorpay payment verification details.'], 400);
}

// Cryptographic signature verification
$isValidSignature = razorpay_verify_signature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);
if (!$isValidSignature) {
    error_log("Razorpay signature verification failed for Order: $razorpayOrderId, Payment: $razorpayPaymentId");
    json_response(['success' => false, 'message' => 'Payment signature verification failed. Please contact support.'], 400);
}

$pending = $_SESSION['rzp_pending_order'] ?? null;
if (!$pending || !is_array($pending) || ($pending['razorpay_order_id'] ?? '') !== $razorpayOrderId) {
    error_log("Pending order session mismatch for Razorpay Order: $razorpayOrderId");
    json_response(['success' => false, 'message' => 'Order session expired or invalid. If your account was debited, please contact us.'], 400);
}

$pdo = getDB();

try {
    $pdo->beginTransaction();

    $orderNumber = (string)$pending['order_number'];
    $subtotal = (float)$pending['subtotal'];
    $shipping = (float)$pending['shipping'];
    $tax = (float)$pending['tax'];
    $discount = (float)$pending['discount'];
    $total = (float)$pending['total'];

    $stmtOrder = $pdo->prepare(
        "INSERT INTO orders (
            order_number, order_type, customer_id, customer_name, email, phone,
            address, city, state, pincode, landmark,
            subtotal, shipping, tax, discount, total,
            payment_method, payment_status, order_status,
            razorpay_order_id, razorpay_payment_id, razorpay_signature,
            notes
         ) VALUES (
            ?, 'online', ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            'Razorpay', 'Paid', 'Confirmed',
            ?, ?, ?,
            ?
         )"
    );

    $notes = 'Online Payment verified via Razorpay. Payment ID: ' . $razorpayPaymentId;
    $stmtOrder->execute([
        $orderNumber,
        $pending['customer_id'] ?? null,
        $pending['customer_name'],
        $pending['email'],
        $pending['phone'],
        $pending['address'],
        $pending['city'],
        $pending['state'],
        $pending['pincode'],
        $pending['landmark'],
        $subtotal,
        $shipping,
        $tax,
        $discount,
        $total,
        $razorpayOrderId,
        $razorpayPaymentId,
        $razorpaySignature,
        $notes,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, price, total)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

    foreach ($pending['items'] as $item) {
        $stmtItem->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['sku'],
            $item['quantity'],
            $item['price'],
            $item['total'],
        ]);
        $stmtStock->execute([$item['quantity'], $item['product_id']]);
    }

    $pdo->commit();

    // Clear cart and pending session
    $_SESSION['cart'] = [];
    unset($_SESSION['rzp_pending_order']);
    $_SESSION['last_order_number'] = $orderNumber;

    json_response([
        'success' => true,
        'order_number' => $orderNumber,
        'redirect_url' => url('order-success.php?order=' . urlencode($orderNumber)),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Razorpay Verification DB Error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to finalize your order. Error: ' . $e->getMessage()], 500);
}
