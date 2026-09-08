(function () {
  'use strict';

  const PE = window.PE || { baseUrl: '', csrf: '' };

  function toast(message, type) {
    const container = document.getElementById('toastContainer');
    if (!container) {
      alert(message);
      return;
    }
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + (type === 'error' ? 'danger' : 'success') + ' border-0 show';
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    container.appendChild(el);
    setTimeout(function () { el.remove(); }, 3500);
  }

  function updateCartCount(count) {
    const badge = document.getElementById('cartCount');
    if (badge) badge.textContent = String(count);
  }

  async function cartRequest(payload) {
    const res = await fetch(PE.baseUrl + '/ajax/cart.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': PE.csrf
      },
      body: JSON.stringify(Object.assign({ csrf_token: PE.csrf }, payload))
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Request failed');
    }
    return data;
  }

  document.addEventListener('click', async function (e) {
    const addBtn = e.target.closest('[data-add-to-cart]');
    if (addBtn) {
      e.preventDefault();
      const id = parseInt(addBtn.getAttribute('data-product-id'), 10);
      const qtyInput = document.querySelector('[data-qty-for="' + id + '"]');
      const qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : parseInt(addBtn.getAttribute('data-qty') || '1', 10);
      addBtn.disabled = true;
      try {
        const data = await cartRequest({ action: 'add', product_id: id, qty: qty });
        updateCartCount(data.cart_count);
        toast(data.message || 'Added to cart', 'success');
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        addBtn.disabled = false;
      }
      return;
    }

    const buyBtn = e.target.closest('[data-buy-now]');
    if (buyBtn) {
      e.preventDefault();
      const id = parseInt(buyBtn.getAttribute('data-product-id'), 10);
      const qtyInput = document.querySelector('[data-qty-for="' + id + '"]');
      const qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
      buyBtn.disabled = true;
      try {
        await cartRequest({ action: 'add', product_id: id, qty: qty });
        window.location.href = PE.baseUrl + '/checkout.php';
      } catch (err) {
        toast(err.message, 'error');
        buyBtn.disabled = false;
      }
      return;
    }

    const qtyBtn = e.target.closest('[data-cart-qty]');
    if (qtyBtn) {
      e.preventDefault();
      const id = parseInt(qtyBtn.getAttribute('data-product-id'), 10);
      const delta = parseInt(qtyBtn.getAttribute('data-cart-qty'), 10);
      const row = qtyBtn.closest('[data-cart-row]');
      const input = row ? row.querySelector('.qty-input') : null;
      let qty = input ? parseInt(input.value, 10) || 1 : 1;
      qty = Math.max(0, qty + delta);
      try {
        const data = await cartRequest({ action: 'update', product_id: id, qty: qty });
        updateCartCount(data.cart_count);
        if (typeof window.refreshCartUI === 'function') {
          window.refreshCartUI(data);
        } else {
          location.reload();
        }
      } catch (err) {
        toast(err.message, 'error');
      }
      return;
    }

    const removeBtn = e.target.closest('[data-cart-remove]');
    if (removeBtn) {
      e.preventDefault();
      const id = parseInt(removeBtn.getAttribute('data-product-id'), 10);
      try {
        const data = await cartRequest({ action: 'remove', product_id: id });
        updateCartCount(data.cart_count);
        if (typeof window.refreshCartUI === 'function') {
          window.refreshCartUI(data);
        } else {
          location.reload();
        }
      } catch (err) {
        toast(err.message, 'error');
      }
      return;
    }

    const toggleBtn = e.target.closest('[data-toggle-password]');
    if (toggleBtn) {
      e.preventDefault();
      const targetSelector = toggleBtn.getAttribute('data-toggle-password');
      let input = targetSelector ? document.querySelector(targetSelector) : null;
      if (!input) {
        const group = toggleBtn.closest('.input-group') || toggleBtn.parentElement;
        input = group ? group.querySelector('input') : null;
      }
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      const icon = toggleBtn.querySelector('i');
      if (icon) {
        if (isPassword) {
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
          toggleBtn.setAttribute('aria-label', 'Hide password');
        } else {
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
          toggleBtn.setAttribute('aria-label', 'Show password');
        }
      }
    }
  });

  window.PEToast = toast;
  window.PECartRequest = cartRequest;
  window.PEUpdateCartCount = updateCartCount;
})();
