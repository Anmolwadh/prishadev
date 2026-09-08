</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('click', function (e) {
  const toggleBtn = e.target.closest('[data-toggle-password]');
  if (!toggleBtn) return;
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
});
</script>
</body>
</html>
