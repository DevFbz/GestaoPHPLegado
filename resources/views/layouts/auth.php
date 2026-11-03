<?php
/** @var array $app */
$settings = $app['settings'];
$color = e($settings['primary_color'] ?? '#2563EB');
$title = $app['page']['title'] ?? 'Entrar';
$company = e($settings['company_name'] ?? 'Controle de Brindes');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Acesse o sistema de Controle de Brindes — <?= $company ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e($app['csrf']) ?>">
  <meta name="base-url" content="<?= e($app['base_url']) ?>">
  <title><?= e($title . ' — ' . ($settings['company_name'] ?? '')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
  <link rel="icon" type="image/png" href="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>">
  <meta name="theme-color" content="#2563EB">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <style>:root { --brand-primary: <?= $color ?>; }</style>
</head>
<body>
<a class="skip-link" href="#login-title">Ir para o formulário de acesso</a>
<div class="auth-wrap">
  <div class="auth-bg-watermark" aria-hidden="true">
    <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="">
  </div>
  <main class="auth-login-shell fade-in" aria-labelledby="login-title">
    <div class="auth-login-card">
      <div class="auth-login-brand">
        <span class="auth-login-logo">
          <?php if (!empty($settings['logo_url'])): ?>
            <img src="<?= e($settings['logo_url']) ?>" alt="Logo <?= $company ?>" onerror="this.onerror=null;this.src='<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>'">
          <?php else: ?>
            <img src="<?= e(asset('img/Emefarma_Símbolo_Azul Claro.png')) ?>" alt="Logo <?= $company ?>">
          <?php endif; ?>
        </span>
        <span class="auth-login-company"><?= $company ?></span>
      </div>
      <div class="auth-card-header">
        <span class="auth-kicker">CONTROLE DE BRINDES</span>
        <h1 id="login-title">Acesse sua conta</h1>
        <p>Entre com seus dados corporativos para continuar.</p>
      </div>
      <?= $content ?? '' ?>
      <p class="auth-security-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Acesso protegido e exclusivo para usuários autorizados</p>
    </div>
    <p class="auth-login-footer">© <?= date('Y') ?> <?= $company ?> <span aria-hidden="true">·</span> Gestão de brindes</p>
  </main>
</div>
<script src="<?= e(asset('js/api.js')) ?>"></script>
<script src="<?= e(asset('js/offline.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $scripts ?? '' ?>
<script src="<?= e(asset('vendor/alpinejs/alpine.min.js')) ?>"></script>
</body>
</html>

