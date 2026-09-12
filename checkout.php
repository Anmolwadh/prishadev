<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/razorpay.php';

if (!cart()) {
    flash('error', 'Your cart is empty.');
    redirect('shop.php');
}

$customer = current_customer();
$razorpayEnabled = razorpay_is_enabled();
$preCity = trim((string)($_POST['city'] ?? ($customer['city'] ?? '')));
$preAddress = trim((string)($_POST['address'] ?? ($customer['address'] ?? '')));
$prePincode = trim((string)($_POST['pincode'] ?? ($customer['pincode'] ?? '')));
$totals = cart_totals($preCity, $preAddress, $prePincode);
$shippingCharge = (float)(get_setting('shipping_charge', '60') ?? 60);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim((string)($_POST['customer_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $pincode = trim((string)($_POST['pincode'] ?? ''));
    $landmark = trim((string)($_POST['landmark'] ?? ''));

    if ($name === '') $errors[] = 'Full name is required.';
    if (!validate_phone($phone)) $errors[] = 'Enter a valid 10-digit mobile number.';
    if ($email !== '' && !validate_email($email)) $errors[] = 'Enter a valid email address.';
    if ($address === '') $errors[] = 'Address is required.';
    if ($city === '') $errors[] = 'City is required.';
    if ($state === '') $errors[] = 'State is required.';
    if (!validate_pincode($pincode)) $errors[] = 'Enter a valid 6-digit pincode.';

    if (!$errors) {
        $pdo = getDB();
        try {
            $pdo->beginTransaction();
            $cartItems = cart();
            if (!$cartItems) {
                throw new RuntimeException('Cart is empty.');
            }

            // Re-validate stock and prices
            $orderItems = [];
            $subtotal = 0.0;
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("SELECT id, name, sku, price, stock, status, gst FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([(int)$item['product_id']]);
                $product = $stmt->fetch();
                if (!$product || $product['status'] !== 'Active') {
                    throw new RuntimeException('A product in your cart is no longer available.');
                }
                $qty = (int)$item['qty'];
                if ($qty < 1 || $qty > (int)$product['stock']) {
                    throw new RuntimeException($product['name'] . ' has insufficient stock.');
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
            $total = round_money($subtotal + $tax + $shipping - $discount);
            $orderNumber = generate_order_number($pdo);

            $ins = $pdo->prepare(
                "INSERT INTO orders (order_number, order_type, customer_id, customer_name, email, phone, address, city, state, pincode, landmark,
                 subtotal, shipping, tax, discount, total, payment_method, payment_status, order_status)
                 VALUES (?, 'online', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COD', 'Pending', 'Pending')"
            );
            $ins->execute([
                $orderNumber,
                $customer['id'] ?? null,
                $name,
                $email !== '' ? $email : null,
                $phone,
                $address,
                $city,
                $state,
                $pincode,
                $landmark !== '' ? $landmark : null,
                $subtotal,
                $shipping,
                $tax,
                $discount,
                $total,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, price, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($orderItems as $oi) {
                $itemStmt->execute([$orderId, $oi['product_id'], $oi['product_name'], $oi['sku'], $oi['quantity'], $oi['price'], $oi['total']]);
                $stockStmt->execute([$oi['quantity'], $oi['product_id'], $oi['quantity']]);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException('Stock update failed for ' . $oi['product_name']);
                }
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order_number'] = $orderNumber;
            redirect('order-success.php?order=' . urlencode($orderNumber));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout | Prisha Enterprises';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container"><h1 class="mb-0">Checkout</h1></div>
</section>
<section class="section-pad">
  <div class="container">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="row g-4">
      <?= csrf_field() ?>
      <div class="col-lg-7">
        <div class="auth-card">
          <h2 class="h5 mb-3">Customer Information</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="customer_name" class="form-control" required value="<?= e($_POST['customer_name'] ?? ($customer['name'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile Number *</label>
              <input type="text" name="phone" class="form-control" required maxlength="10" value="<?= e($_POST['phone'] ?? ($customer['phone'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? ($customer['email'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Address *</label>
              <textarea name="address" id="checkoutAddress" class="form-control" rows="3" required><?= e($_POST['address'] ?? ($customer['address'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">City *</label>
              <input type="text" name="city" id="checkoutCity" class="form-control" required value="<?= e($_POST['city'] ?? ($customer['city'] ?? '')) ?>" placeholder="e.g. Rajpura">
            </div>
            <div class="col-md-4">
              <label class="form-label">State *</label>
              <input type="text" name="state" class="form-control" required value="<?= e($_POST['state'] ?? ($customer['state'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Pincode *</label>
              <input type="text" name="pincode" id="checkoutPincode" class="form-control" required maxlength="6" value="<?= e($_POST['pincode'] ?? ($customer['pincode'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Landmark</label>
              <input type="text" name="landmark" class="form-control" value="<?= e($_POST['landmark'] ?? '') ?>">
            </div>
          </div>
          <div class="mt-4">
            <label class="form-label fw-bold d-block mb-2">Select Payment Method *</label>
            <div class="vstack gap-2" id="paymentOptionsGroup">
              <?php if ($razorpayEnabled): ?>
                <label class="p-3 rounded-3 border d-flex align-items-center justify-content-between payment-card" style="cursor: pointer; background: #f8fafc;" id="labelRazorpay">
                  <div class="d-flex align-items-center gap-3">
                    <input type="radio" name="payment_method" value="Razorpay" checked class="form-check-input mt-0" id="payRazorpay">
                    <div>
                      <div class="fw-bold text-dark"><i class="fa-solid fa-credit-card text-primary me-2"></i>Pay Online (Razorpay)</div>
                      <div class="small text-muted">UPI (Google Pay, PhonePe, Paytm), Debit/Credit Cards, Net Banking, Wallets</div>
                    </div>
                  </div>
                  <span class="badge bg-primary text-white">Instant &amp; Secure</span>
                </label>
              <?php endif; ?>

              <label class="p-3 rounded-3 border d-flex align-items-center justify-content-between payment-card" style="cursor: pointer; background: #f8fafc;" id="labelCOD">
                <div class="d-flex align-items-center gap-3">
                  <input type="radio" name="payment_method" value="COD" <?= !$razorpayEnabled ? 'checked' : '' ?> class="form-check-input mt-0" id="payCOD">
                  <div>
                    <div class="fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>Cash on Delivery (COD)</div>
                    <div class="small text-muted">Pay in cash upon receiving your order at your doorstep</div>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="summary-box">
          <h2 class="h5 mb-3">Order Summary</h2>
          <?php foreach (cart() as $item): ?>
            <div class="d-flex justify-content-between mb-2 small">
              <span><?= e($item['name']) ?> × <?= (int)$item['qty'] ?></span>
            </div>
          <?php endforeach; ?>
          <hr>
          <div class="d-flex justify-content-between mb-3"><span>Total Amount</span><strong class="fs-5 text-success" id="coTotal"><?= e(format_money($totals['total'])) ?></strong></div>
          <div id="checkoutAlertBox" class="alert alert-danger d-none mb-3 py-2 small"></div>
          <button type="submit" id="btnPlaceOrder" class="btn btn-pe w-100 py-2 fs-6">
            <span id="btnText"><?= $razorpayEnabled ? 'Pay Online' : 'Place Order' ?></span>
            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
          </button>
        </div>
      </div>
    </form>
  </div>
</section>
<?php if ($razorpayEnabled): ?>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
<script>
(function () {
  const charge = <?= json_encode($shippingCharge) ?>;
  const subtotal = <?= json_encode((float)$totals['subtotal']) ?>;
  const tax = <?= json_encode((float)$totals['tax']) ?>;
  const cityEl = document.getElementById('checkoutCity');
  const addressEl = document.getElementById('checkoutAddress');
  const pinEl = document.getElementById('checkoutPincode');
  const totalEl = document.getElementById('coTotal');
  const form = document.querySelector('form.row.g-4');
  const btnPlaceOrder = document.getElementById('btnPlaceOrder');
  const btnText = document.getElementById('btnText');
  const btnSpinner = document.getElementById('btnSpinner');
  const alertBox = document.getElementById('checkoutAlertBox');
  const rzpRadios = document.querySelectorAll('input[name="payment_method"]');

  function isRajpura() {
    const text = ((cityEl?.value || '') + ' ' + (addressEl?.value || '') + ' ' + (pinEl?.value || '')).toLowerCase();
    return text.includes('rajpura') || /\b14040\d\b/.test(text);
  }

  function formatMoney(n) {
    return '₹' + Math.round(Number(n)).toLocaleString('en-IN');
  }

  function refreshTotal() {
    const shipping = isRajpura() ? 0 : (subtotal > 0 ? charge : 0);
    const finalAmount = Math.round(subtotal + tax + shipping);
    if (totalEl) totalEl.textContent = formatMoney(finalAmount);
    updateButtonText(finalAmount);
  }

  function updateButtonText(finalAmount) {
    const selected = document.querySelector('input[name="payment_method"]:checked')?.value || 'COD';
    if (btnText) {
      if (selected === 'Razorpay') {
        btnText.textContent = 'Pay Now ' + (finalAmount ? formatMoney(finalAmount) : '');
      } else {
        btnText.textContent = 'Place Order (COD)';
      }
    }
  }

  function showAlert(msg) {
    if (alertBox) {
      alertBox.textContent = msg;
      alertBox.classList.remove('d-none');
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      alert(msg);
    }
  }

  function hideAlert() {
    if (alertBox) {
      alertBox.classList.add('d-none');
      alertBox.textContent = '';
    }
  }

  function setLoading(loading) {
    if (btnPlaceOrder) btnPlaceOrder.disabled = loading;
    if (btnSpinner) {
      if (loading) btnSpinner.classList.remove('d-none');
      else btnSpinner.classList.add('d-none');
    }
  }

  rzpRadios.forEach(function (r) {
    r.addEventListener('change', function () {
      refreshTotal();
    });
  });

  ['input', 'change', 'blur'].forEach(function (evt) {
    cityEl?.addEventListener(evt, refreshTotal);
    addressEl?.addEventListener(evt, refreshTotal);
    pinEl?.addEventListener(evt, refreshTotal);
  });
  refreshTotal();

  if (form) {
    form.addEventListener('submit', function (e) {
      const selected = document.querySelector('input[name="payment_method"]:checked')?.value || 'COD';
      if (selected !== 'Razorpay') {
        // Standard COD submit proceeds naturally
        setLoading(true);
        return;
      }

      // Online payment with Razorpay
      e.preventDefault();
      hideAlert();

      if (!form.reportValidity()) {
        return;
      }

      if (typeof Razorpay === 'undefined') {
        showAlert('Razorpay checkout library failed to load. Please check your internet connection and try again.');
        return;
      }

      setLoading(true);
      const formData = new FormData(form);

      fetch('<?= e(url('ajax/razorpay-init.php')) ?>', {
        method: 'POST',
        body: formData
      })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (!data.success) {
          setLoading(false);
          showAlert(data.message || 'Could not initiate payment. Please try again.');
          return;
        }

        const options = {
          key: data.key_id,
          amount: data.amount,
          currency: data.currency || 'INR',
          name: data.business_name || 'Prisha Enterprises',
          description: data.description || 'Order Payment',
          image: '<?= e(asset('images/favicon.svg')) ?>',
          order_id: data.order_id,
          prefill: data.prefill || {},
          theme: {
            color: '#16a34a'
          },
          handler: function (response) {
            // Payment succeeded at Razorpay -> verify cryptographic signature on server
            if (btnText) btnText.textContent = 'Verifying Payment...';
            fetch('<?= e(url('ajax/razorpay-verify.php')) ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                razorpay_order_id: response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature: response.razorpay_signature
              })
            })
            .then(function (vRes) {
              return vRes.json();
            })
            .then(function (vData) {
              if (vData.success && vData.redirect_url) {
                window.location.href = vData.redirect_url;
              } else {
                setLoading(false);
                refreshTotal();
                showAlert(vData.message || 'Payment verification failed. Please contact support.');
              }
            })
            .catch(function (err) {
              setLoading(false);
              refreshTotal();
              showAlert('Verification network error: ' + err.message);
            });
          },
          modal: {
            ondismiss: function () {
              setLoading(false);
              refreshTotal();
              showAlert('Payment window was closed. You can retry payment or select Cash on Delivery.');
            }
          }
        };

        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function (resp) {
          setLoading(false);
          refreshTotal();
          const errDesc = resp.error?.description || 'Payment was declined by bank or cancelled.';
          showAlert('Payment Failed: ' + errDesc);
        });

        rzp.open();
      })
      .catch(function (err) {
        setLoading(false);
        refreshTotal();
        showAlert('Could not connect to server: ' + err.message);
      });
    });
  }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
