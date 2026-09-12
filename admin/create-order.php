<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getDB();
$errors = [];

// Fetch active products for dropdown
$stmtProducts = $pdo->query("SELECT id, name, sku, price, stock, gst FROM products WHERE status = 'Active' ORDER BY name ASC");
$products = $stmtProducts->fetchAll();

// Fetch existing customers for auto-fill helper
$stmtCustomers = $pdo->query("SELECT id, name, email, phone, address, city, state, pincode FROM customers ORDER BY name ASC");
$customers = $stmtCustomers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customerName = trim((string)($_POST['customer_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $pincode = trim((string)($_POST['pincode'] ?? ''));
    $landmark = trim((string)($_POST['landmark'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
    $paymentStatus = trim((string)($_POST['payment_status'] ?? 'Paid'));
    $orderStatus = trim((string)($_POST['order_status'] ?? 'Confirmed'));
    $deductStock = !empty($_POST['deduct_stock']);

    $shipping = max(0, (float)($_POST['shipping'] ?? 0));
    $tax = max(0, (float)($_POST['tax'] ?? 0));
    $discount = max(0, (float)($_POST['discount'] ?? 0));

    // Basic customer validation
    if ($customerName === '') $errors[] = 'Customer name is required.';
    if (!validate_phone($phone)) $errors[] = 'Valid 10-digit phone number is required.';
    if ($email !== '' && !validate_email($email)) $errors[] = 'Valid email address is required.';
    if ($address === '') $errors[] = 'Delivery address is required.';
    if ($city === '') $errors[] = 'City is required.';
    if ($state === '') $errors[] = 'State is required.';
    if (!validate_pincode($pincode)) $errors[] = 'Valid 6-digit pincode is required.';

    // Product items validation
    $itemProductIds = $_POST['item_product_id'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];

    $orderItems = [];
    $subtotal = 0.0;

    if (empty($itemProductIds) || !is_array($itemProductIds)) {
        $errors[] = 'Please add at least one product to the order.';
    } else {
        // Build map of products for quick lookup
        $productsById = [];
        foreach ($products as $p) {
            $productsById[(int)$p['id']] = $p;
        }

        for ($i = 0; $i < count($itemProductIds); $i++) {
            $pid = (int)($itemProductIds[$i] ?? 0);
            $qty = (int)($itemQtys[$i] ?? 0);
            $customPrice = isset($itemPrices[$i]) ? (float)$itemPrices[$i] : null;

            if ($pid <= 0) {
                continue;
            }

            if (!isset($productsById[$pid])) {
                $errors[] = "Product ID #{$pid} is invalid or no longer active.";
                continue;
            }

            $prod = $productsById[$pid];

            if ($qty <= 0) {
                $errors[] = "Quantity for '{$prod['name']}' must be at least 1.";
                continue;
            }

            $unitPrice = ($customPrice !== null && $customPrice >= 0) ? $customPrice : (float)$prod['price'];
            $lineTotal = round_money($unitPrice * $qty);
            $subtotal += $lineTotal;

            $orderItems[] = [
                'product_id' => $pid,
                'product_name' => $prod['name'],
                'sku' => $prod['sku'],
                'quantity' => $qty,
                'price' => $unitPrice,
                'total' => $lineTotal,
                'current_stock' => (int)$prod['stock'],
            ];
        }

        if (empty($orderItems)) {
            $errors[] = 'Please select at least one valid product.';
        }
    }

    if (!$errors) {
        $grandTotal = round_money(max(0, $subtotal + $shipping + $tax - $discount));

        try {
            $pdo->beginTransaction();

            $orderNumber = generate_order_number($pdo);

            $stmtOrder = $pdo->prepare(
                'INSERT INTO orders (order_number, customer_id, customer_name, email, phone, address, city, state, pincode, landmark,
                 subtotal, shipping, tax, discount, total, payment_method, payment_status, order_status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtOrder->execute([
                $orderNumber,
                $customerId,
                $customerName,
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
                $grandTotal,
                $paymentMethod,
                $paymentStatus,
                $orderStatus,
                $notes !== '' ? $notes : 'Manual offline order created by Admin',
            ]);
            $newOrderId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, price, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($orderItems as $item) {
                $stmtItem->execute([
                    $newOrderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['sku'],
                    $item['quantity'],
                    $item['price'],
                    $item['total'],
                ]);

                if ($deductStock) {
                    $stmtStock->execute([$item['quantity'], $item['product_id']]);
                }
            }

            $pdo->commit();

            flash('success', "Order #{$orderNumber} created successfully for {$customerName}.");
            redirect('admin/order-details.php?id=' . $newOrderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Failed to create manual order: ' . $e->getMessage());
            $errors[] = 'Failed to create order: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Manual Order';
$adminPage = 'orders.php';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-cart-plus text-success me-2"></i>Create Manual Order</h2>
    <div class="text-muted small">Place and record an order manually on behalf of an offline, walk-in, or phone client.</div>
  </div>
  <a href="<?= e(url('admin/orders.php')) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fa-solid fa-arrow-left me-1"></i>Back to Orders
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" id="manualOrderForm" autocomplete="off">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Customer Details -->
    <div class="col-lg-5">
      <div class="admin-card mb-3">
        <h3 class="h6 fw-bold mb-3 border-bottom pb-2">
          <i class="fa-solid fa-user me-2 text-secondary"></i>Customer Information
        </h3>

        <?php if (!empty($customers)): ?>
          <div class="mb-3">
            <label class="form-label" for="selectCustomer">Load Existing Registered Customer (Optional)</label>
            <select id="selectCustomer" class="form-select">
              <option value="">-- Or enter customer details manually below --</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  data-name="<?= e($c['name']) ?>"
                  data-email="<?= e((string)$c['email']) ?>"
                  data-phone="<?= e($c['phone']) ?>"
                  data-address="<?= e((string)$c['address']) ?>"
                  data-city="<?= e((string)$c['city']) ?>"
                  data-state="<?= e((string)$c['state']) ?>"
                  data-pincode="<?= e((string)$c['pincode']) ?>">
                  <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="customer_id" id="customerId" value="<?= e($_POST['customer_id'] ?? '') ?>">
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="customerName">Customer Name <span class="text-danger">*</span></label>
          <input type="text" name="customer_name" id="customerName" class="form-control" required value="<?= e($_POST['customer_name'] ?? '') ?>" placeholder="e.g. Ramesh Kumar">
        </div>

        <div class="row g-2 mb-3">
          <div class="col-sm-6">
            <label class="form-label" for="customerPhone">Phone Number <span class="text-danger">*</span></label>
            <input type="tel" name="phone" id="customerPhone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>" placeholder="10-digit mobile" maxlength="15">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="customerEmail">Email Address</label>
            <input type="email" name="email" id="customerEmail" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="Optional">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="customerAddress">Delivery / Street Address <span class="text-danger">*</span></label>
          <textarea name="address" id="customerAddress" class="form-control" rows="2" required placeholder="Shop/House number, Street, Area"><?= e($_POST['address'] ?? '') ?></textarea>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-sm-6">
            <label class="form-label" for="customerCity">City <span class="text-danger">*</span></label>
            <input type="text" name="city" id="customerCity" class="form-control" required value="<?= e($_POST['city'] ?? 'Rajpura') ?>" placeholder="e.g. Rajpura">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="customerState">State <span class="text-danger">*</span></label>
            <input type="text" name="state" id="customerState" class="form-control" required value="<?= e($_POST['state'] ?? 'Punjab') ?>" placeholder="e.g. Punjab">
          </div>
        </div>

        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label" for="customerPincode">Pincode <span class="text-danger">*</span></label>
            <input type="text" name="pincode" id="customerPincode" class="form-control" required value="<?= e($_POST['pincode'] ?? '140401') ?>" placeholder="6-digit pincode" maxlength="10">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="customerLandmark">Landmark</label>
            <input type="text" name="landmark" id="customerLandmark" class="form-control" value="<?= e($_POST['landmark'] ?? '') ?>" placeholder="Nearby place">
          </div>
        </div>
      </div>

      <!-- Payment & Fulfillment -->
      <div class="admin-card">
        <h3 class="h6 fw-bold mb-3 border-bottom pb-2">
          <i class="fa-solid fa-receipt me-2 text-secondary"></i>Payment & Status
        </h3>

        <div class="row g-2 mb-3">
          <div class="col-sm-6">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="form-select">
              <option value="Cash" <?= ($_POST['payment_method'] ?? 'Cash') === 'Cash' ? 'selected' : '' ?>>Cash in Hand</option>
              <option value="UPI" <?= ($_POST['payment_method'] ?? '') === 'UPI' ? 'selected' : '' ?>>UPI / PhonePe / GPay</option>
              <option value="Bank Transfer" <?= ($_POST['payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer (NEFT/IMPS)</option>
              <option value="COD" <?= ($_POST['payment_method'] ?? '') === 'COD' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
              <option value="Cheque" <?= ($_POST['payment_method'] ?? '') === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Payment Status</label>
            <select name="payment_status" class="form-select">
              <option value="Paid" <?= ($_POST['payment_status'] ?? 'Paid') === 'Paid' ? 'selected' : '' ?>>Paid</option>
              <option value="Pending" <?= ($_POST['payment_status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Order Status</label>
          <select name="order_status" class="form-select">
            <option value="Confirmed" <?= ($_POST['order_status'] ?? 'Confirmed') === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="Pending" <?= ($_POST['order_status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Processing" <?= ($_POST['order_status'] ?? '') === 'Processing' ? 'selected' : '' ?>>Processing</option>
            <option value="Delivered" <?= ($_POST['order_status'] ?? '') === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
          </select>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="deduct_stock" value="1" id="deductStockCheck" checked>
          <label class="form-check-label small" for="deductStockCheck">
            Automatically deduct ordered quantities from product inventory stock
          </label>
        </div>

        <div class="mb-0">
          <label class="form-label" for="orderNotes">Order Notes / Offline Remarks</label>
          <textarea name="notes" id="orderNotes" class="form-control" rows="2" placeholder="e.g. Offline walk-in customer. Full cash received."><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Products & Line Items -->
    <div class="col-lg-7">
      <div class="admin-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
          <h3 class="h6 fw-bold mb-0">
            <i class="fa-solid fa-boxes-stacked me-2 text-secondary"></i>Order Items
          </h3>
          <button type="button" class="btn btn-sm btn-outline-success" id="addRowBtn">
            <i class="fa-solid fa-plus me-1"></i>Add Another Product
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="itemsTable">
            <thead class="table-light">
              <tr>
                <th style="min-width: 220px;">Product</th>
                <th style="width: 100px;">Qty</th>
                <th style="width: 130px;">Unit Price (₹)</th>
                <th style="width: 120px;" class="text-end">Total (₹)</th>
                <th style="width: 50px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody id="itemsTbody">
              <!-- Dynamic Rows inserted via JS -->
            </tbody>
          </table>
        </div>

        <!-- Totals & Calculations -->
        <div class="card bg-light border-0 mt-3 p-3">
          <div class="row g-2 justify-content-end text-end">
            <div class="col-sm-7 d-flex justify-content-between align-items-center">
              <span class="text-muted">Subtotal:</span>
              <span class="fw-bold" id="displaySubtotal">₹0.00</span>
            </div>
            <div class="col-sm-7 d-flex justify-content-between align-items-center">
              <label for="inputShipping" class="text-muted mb-0">Shipping (₹):</label>
              <input type="number" step="0.01" min="0" name="shipping" id="inputShipping" class="form-control form-control-sm text-end w-50" value="<?= e($_POST['shipping'] ?? '0.00') ?>">
            </div>
            <div class="col-sm-7 d-flex justify-content-between align-items-center">
              <label for="inputTax" class="text-muted mb-0">Tax / GST (₹):</label>
              <input type="number" step="0.01" min="0" name="tax" id="inputTax" class="form-control form-control-sm text-end w-50" value="<?= e($_POST['tax'] ?? '0.00') ?>">
            </div>
            <div class="col-sm-7 d-flex justify-content-between align-items-center">
              <label for="inputDiscount" class="text-muted mb-0">Discount (₹):</label>
              <input type="number" step="0.01" min="0" name="discount" id="inputDiscount" class="form-control form-control-sm text-end w-50" value="<?= e($_POST['discount'] ?? '0.00') ?>">
            </div>
            <hr class="col-sm-7 my-2">
            <div class="col-sm-7 d-flex justify-content-between align-items-center fs-5">
              <strong>Grand Total:</strong>
              <strong class="text-success" id="displayGrandTotal">₹0.00</strong>
            </div>
          </div>
        </div>

        <div class="d-grid gap-2 mt-4">
          <button type="submit" class="btn btn-success btn-lg">
            <i class="fa-solid fa-check-circle me-1"></i>Create & Save Order
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
// Catalog product list for JavaScript row generation
const catalogProducts = <?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'],
        'price' => (float)$p['price'],
        'stock' => (int)$p['stock'],
        'gst' => (float)($p['gst'] ?? 0),
    ];
}, $products)) ?>;

const tbody = document.getElementById('itemsTbody');

function createRow(selectedId = 0, initialQty = 1, initialPrice = null) {
  const tr = document.createElement('tr');
  tr.className = 'item-row';

  let optionsHtml = '<option value="">-- Select a product --</option>';
  catalogProducts.forEach(p => {
    const isSelected = (p.id === selectedId) ? 'selected' : '';
    optionsHtml += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}" data-gst="${p.gst}" ${isSelected}>${p.name} (Stock: ${p.stock})</option>`;
  });

  tr.innerHTML = `
    <td>
      <select name="item_product_id[]" class="form-select form-select-sm product-select" required>
        ${optionsHtml}
      </select>
    </td>
    <td>
      <input type="number" name="item_qty[]" class="form-control form-control-sm qty-input text-center" min="1" value="${initialQty}" required>
    </td>
    <td>
      <input type="number" step="0.01" min="0" name="item_price[]" class="form-control form-control-sm price-input text-end" value="${initialPrice !== null ? initialPrice : ''}" placeholder="Price" required>
    </td>
    <td class="text-end fw-semibold line-total">
      ₹0.00
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" title="Remove Item">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </td>
  `;

  tbody.appendChild(tr);

  const prodSelect = tr.querySelector('.product-select');
  const priceInput = tr.querySelector('.price-input');
  const qtyInput = tr.querySelector('.qty-input');
  const removeBtn = tr.querySelector('.remove-row-btn');

  prodSelect.addEventListener('change', function() {
    const selectedOpt = this.selectedOptions[0];
    if (selectedOpt && selectedOpt.value) {
      const defaultPrice = selectedOpt.getAttribute('data-price');
      priceInput.value = parseFloat(defaultPrice).toFixed(2);
    } else {
      priceInput.value = '';
    }
    calculateTotals();
  });

  qtyInput.addEventListener('input', calculateTotals);
  priceInput.addEventListener('input', calculateTotals);

  removeBtn.addEventListener('click', function() {
    if (tbody.querySelectorAll('tr').length > 1) {
      tr.remove();
      calculateTotals();
    } else {
      alert('An order must contain at least one product row.');
    }
  });

  // Trigger initial calculation
  if (selectedId > 0 && initialPrice === null) {
    prodSelect.dispatchEvent(new Event('change'));
  } else {
    calculateTotals();
  }
}

function calculateTotals() {
  let subtotal = 0;
  tbody.querySelectorAll('tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.qty-input')?.value || 0);
    const price = parseFloat(tr.querySelector('.price-input')?.value || 0);
    const lineTotal = (qty > 0 && price >= 0) ? (qty * price) : 0;
    tr.querySelector('.line-total').textContent = '₹' + lineTotal.toFixed(2);
    subtotal += lineTotal;
  });

  const shipping = parseFloat(document.getElementById('inputShipping')?.value || 0);
  const tax = parseFloat(document.getElementById('inputTax')?.value || 0);
  const discount = parseFloat(document.getElementById('inputDiscount')?.value || 0);

  const grandTotal = Math.max(0, subtotal + shipping + tax - discount);

  document.getElementById('displaySubtotal').textContent = '₹' + subtotal.toFixed(2);
  document.getElementById('displayGrandTotal').textContent = '₹' + grandTotal.toFixed(2);
}

// Add row button listener
document.getElementById('addRowBtn').addEventListener('click', function() {
  createRow();
});

// Dynamic input listeners for calculations
['inputShipping', 'inputTax', 'inputDiscount'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', calculateTotals);
});

// Customer auto-fill selection
const customerSelect = document.getElementById('selectCustomer');
if (customerSelect) {
  customerSelect.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    if (opt && opt.value) {
      document.getElementById('customerId').value = opt.value;
      document.getElementById('customerName').value = opt.getAttribute('data-name') || '';
      document.getElementById('customerEmail').value = opt.getAttribute('data-email') || '';
      document.getElementById('customerPhone').value = opt.getAttribute('data-phone') || '';
      document.getElementById('customerAddress').value = opt.getAttribute('data-address') || '';
      document.getElementById('customerCity').value = opt.getAttribute('data-city') || '';
      document.getElementById('customerState').value = opt.getAttribute('data-state') || '';
      document.getElementById('customerPincode').value = opt.getAttribute('data-pincode') || '';
    } else {
      document.getElementById('customerId').value = '';
    }
  });
}

// Initialize with 1 empty row on load
document.addEventListener('DOMContentLoaded', function() {
  if (tbody.children.length === 0) {
    createRow();
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
