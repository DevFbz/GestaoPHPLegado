<?php
ob_start(); ?>
<form id="loginForm" class="auth-form" novalidate>
  <div class="auth-form-intro">
    <h3>Entrar na sua conta</h3>
    <p>Use suas credenciais corporativas para continuar.</p>
  </div>
  <div class="auth-field mb-3">
    <label class="form-label" for="login-email">E-mail</label>
    <div class="auth-input-wrap">
      <i class="bi bi-envelope" aria-hidden="true"></i>
      <input class="form-control" type="email" id="login-email" name="email"
             autocomplete="username" required autofocus
             placeholder="seu@email.com">
    </div>
    <div class="invalid-feedback" id="err-email"></div>
  </div>
  <div class="auth-field mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <label class="form-label mb-0" for="login-password">Senha</label>
      <a href="<?= e(url('/esqueci-senha')) ?>" class="auth-forgot">Esqueci minha senha</a>
    </div>
    <div class="auth-input-wrap mt-1">
      <i class="bi bi-lock" aria-hidden="true"></i>
      <input class="form-control" type="password" id="login-password" name="password"
             autocomplete="current-password" required placeholder="Digite sua senha">
      <button class="auth-password-toggle" type="button" id="togglePwd" aria-label="Mostrar senha">
        <i class="bi bi-eye" id="eyeIcon"></i>
      </button>
    </div>
    <div class="invalid-feedback" id="err-password"></div>
  </div>
  <p class="auth-error d-none" id="loginGlobalErr" role="alert"></p>
  <button class="btn btn-primary auth-submit w-100" type="submit" id="loginBtn">
    <span>Entrar</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
  </button>
</form>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
(function () {
  const form = document.getElementById('loginForm');
  const btn  = document.getElementById('loginBtn');
  const errEl = document.getElementById('loginGlobalErr');

  // Toggle password visibility
  document.getElementById('togglePwd')?.addEventListener('click', function () {
    const pwd = document.getElementById('login-password');
    const icon = document.getElementById('eyeIcon');
    const show = pwd.type === 'password';
    pwd.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  function setFieldError(id, msg) {
    const el = document.getElementById('err-' + id);
    const input = form.querySelector('[name="' + id + '"]');
    if (el) el.textContent = msg || '';
    if (input) input.classList.toggle('is-invalid', !!msg);
  }
  function clearErrors() {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    errEl.classList.add('d-none');
    errEl.textContent = '';
    ['email','password'].forEach(f => { const el = document.getElementById('err-'+f); if(el) el.textContent=''; });
  }

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    clearErrors();
    btn.disabled = true;
    btn.innerHTML = '<span class="spin d-inline-block me-2" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%"></span><span>Entrando…</span>';
    try {
      await Api.post('/api/auth/login', Object.fromEntries(new FormData(this)));
      const next = new URLSearchParams(location.search).get('next');
      location.href = Api.url(next && next.startsWith('/') && !next.startsWith('//') ? next : '/dashboard');
    } catch (e) {
      if (e && e.fields) {
        Object.entries(e.fields).forEach(([k, v]) => setFieldError(k, v));
      } else {
        errEl.textContent = e.message || 'Erro ao fazer login. Tente novamente.';
        errEl.classList.remove('d-none');
      }
    } finally {
      btn.disabled = false;
      btn.innerHTML = 'Entrar';
    }
  });
})();
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/auth.php';

