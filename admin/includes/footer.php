</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePasswordVisibility(btn) {
  if (!btn) return;
  var group = btn.closest('.input-group') || btn.parentElement;
  if (!group) return;
  var input = group.querySelector('input');
  if (!input) return;
  var isPass = input.type === 'password';
  input.type = isPass ? 'text' : 'password';
  var icon = btn.querySelector('i');
  if (icon) {
    if (isPass) {
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
      btn.setAttribute('aria-label', 'Hide password');
    } else {
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
      btn.setAttribute('aria-label', 'Show password');
    }
  }
  input.focus();
}

document.addEventListener('click', function (e) {
  var toggleBtn = e.target.closest('[data-toggle-password]');
  if (toggleBtn && !toggleBtn.getAttribute('onclick')) {
    e.preventDefault();
    togglePasswordVisibility(toggleBtn);
  }
});
</script>
</body>
</html>
