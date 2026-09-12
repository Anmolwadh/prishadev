<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

if (!razorpay_is_enabled()) {
    json_response(['success' => false, 'message' => 'Online payment via Razorpay is currently disabled.'], 400);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf(is_string($csrfToken) ? $csrfToken : null)) {
    json_response(['success' => false, 'message' => 'Security token expired. Please refresh the page.'], 403);
}

$cartItems = cart();
if (!$cartItems) {
    json_response(['success' => false, 'message' => 'Your cart is empty.'], 400);
}

$customer = current_customer();
$name = trim((string)($input['customer_name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$city = trim((string)($input['city'] ?? ''));
$state = trim((string)($input['state'] ?? ''));
$pincode = trim((string)($input['pincode'] ?? ''));
$landmark = trim((string)($input['landmark'] ?? ''));

// Validate inputs
if ($name === '') {
    json_response(['success' => false, 'message' => 'Full name is required.'], 422);
}
if (!validate_phone($phone)) {
    json_response(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.'], 422);
}
if ($email !== '' && !validate_email($email)) {
    json_response(['success' => false, 'message' => 'Enter a valid email address.'], 422);
}
if ($address === '') {
    json_response(['success' => false, 'message' => 'Delivery address is required.'], 422);
}
if ($city === '') {
    json_response(['success' => false, 'message' => 'City is required.'], 422);
}
if ($state === '') {
    json_response(['success' => false, 'message' => 'State is required.'], 422);
}
if (!validate_pincode($pincode)) {
    json_response(['success' => false, 'message' => 'Enter a valid 6-digit pincode.'], 422);
}

try {
    $pdo = getDB();

    // Verify products and stock
    $orderItems = [];
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $stmt = $pdo->prepare("SELECT id, name, sku, price, stock, status, gst FROM products WHERE id = ?");
        $stmt->execute([(int)$item['product_id']]);
        $product = $stmt->fetch();
        if (!$product || $product['status'] !== 'Active') {
            json_response(['success' => false, 'message' => 'A product in your cart is no longer available.'], 400);
        }
        $qty = (int)$item['qty'];
        if ($qty < 1 || $qty > (int)$product['stock']) {
            json_response(['success' => false, 'message' => "Insufficient stock for {$product['name']}. Only {$product['stock']} available."], 400);
        }
        $line = round((float)$product['price'] * $qty, 2);
        $gst = max(0, min(100, (float)($product['gst'] ?? 0)));
        $incl = round_money((float)$product['price'] + ((float)$product['price'] * $gst / 100));
        $lineTax = round_money(($incl * $qty) - $line);
        $subtotal += $line;
        $orderItems[] = [
            'product_id' => (int)$product['id'],
            'product_name' => $product['name'],
            'sku' => $product['sku'],
            'quantity' => $qty,
            'price' => (float)$product['price'],
            'total' => $line,
            'tax' => $lineTax,
        ];
    }

    $shipping = shipping_amount($subtotal, $city, $address, $pincode);
    $discount = 0.0;
    $tax = 0.0;
    foreach ($orderItems as $oi) {
        $tax += (float)$oi['tax'];
    }
    $tax = round_money($tax);
    $grandTotal = round_money(max(1, $subtotal + $tax + $shipping - $discount));

    $orderNumber = generate_order_number($pdo);

    // Call Razorpay API to create order
    $rzpOrder = razorpay_create_order($grandTotal, $orderNumber, [
        'customer_name' => $name,
        'phone' => $phone,
        'email' => $email,
    ]);

    if (!$rzpOrder['success']) {
        json_response(['success' => false, 'message' => $rzpOrder['error'] ?? 'Could not initialize Razorpay payment.'], 500);
    }

    // Store pending order details in session to safely verify upon payment callback
    $_SESSION['rzp_pending_order'] = [
        'order_number' => $orderNumber,
        'razorpay_order_id' => $rzpOrder['order_id'],
        'customer_id' => $customer['id'] ?? null,
        'customer_name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'pincode' => $pincode,
        'landmark' => $landmark !== '' ? $landmark : null,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $grandTotal,
        'items' => $orderItems,
        'created_at' => time(),
    ];

    $creds = razorpay_get_credentials();
    json_response([
        'success' => true,
        'key_id' => $creds['key_id'],
        'order_id' => $rzpOrder['order_id'],
        'amount' => $rzpOrder['amount'],
        'currency' => $rzpOrder['currency'] ?? 'INR',
        'order_number' => $orderNumber,
        'business_name' => get_setting('business_name', 'Prisha Enterprises'),
        'description' => 'Order ' . $orderNumber,
        'prefill' => [
            'name' => $name,
            'email' => $email,
            'contact' => $phone,
        ],
        'notes' => [
            'order_number' => $orderNumber,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Razorpay Init Error: ' . $e->getMessage());
    $msg = 'An error occurred while preparing your payment: ' . $e->getMessage();
    json_response(['success' => false, 'message' => $msg], 500);
}
