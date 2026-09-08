</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

document.addEventListener('click', function (e) {
  var toggleBtn = e.target.closest('[data-toggle-password]');
  if (toggleBtn && !toggleBtn.getAttribute('onclick')) {
    e.preventDefault();
    togglePasswordVisibility(toggleBtn, e);
  }
});
</script>
</body>
</html>
