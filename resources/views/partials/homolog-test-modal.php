<?php
$isHomolog = str_contains(rtrim((string) ($app['base_url'] ?? ''), '/'), '/homolog');
if ($isHomolog): ?>
<style>
  html.homolog-modal-open, html.homolog-modal-open body { overflow:hidden!important; }
  .homolog-test-overlay { position:fixed; inset:0; z-index:2147483647; display:grid; place-items:center; padding:20px; background:rgba(15,23,42,.78); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); cursor:not-allowed; }
  .homolog-test-modal { width:min(100%,520px); border:1px solid #93c5fd; border-radius:22px; background:#fff; color:#172033; padding:28px; text-align:center; box-shadow:0 24px 80px rgba(2,6,23,.35); cursor:default; }
  .homolog-test-icon { width:64px; height:64px; display:grid; place-items:center; margin:0 auto 16px; border-radius:18px; background:#dbeafe; color:#1d4ed8; font-size:30px; }
  .homolog-test-kicker { margin:0 0 7px; color:#1d4ed8; font-size:.72rem; font-weight:850; letter-spacing:.12em; text-transform:uppercase; }
  .homolog-test-modal h2 { margin:0 0 10px; color:#172b4d; font-size:1.45rem; font-weight:850; letter-spacing:-.025em; }
  .homolog-test-modal p { margin:0; color:#475467; line-height:1.6; }
  .homolog-test-badge { display:inline-flex; align-items:center; gap:6px; margin-top:18px; border-radius:999px; background:#eff6ff; color:#1d4ed8; padding:8px 13px; font-size:.78rem; font-weight:800; }
  @media (max-width:480px) { .homolog-test-overlay { padding:14px; } .homolog-test-modal { padding:24px 18px; border-radius:18px; } .homolog-test-modal h2 { font-size:1.22rem; } }
</style>
<script>
  document.documentElement.classList.add('homolog-modal-open');
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') event.preventDefault();
  }, true);
</script>
<div class="homolog-test-overlay" id="homologTestOverlay" role="presentation" aria-hidden="false">
  <section class="homolog-test-modal" role="dialog" aria-modal="true" aria-labelledby="homologTestTitle" aria-describedby="homologTestDescription">
    <div class="homolog-test-icon"><i class="bi bi-cone-striped" aria-hidden="true"></i></div>
    <p class="homolog-test-kicker">Ambiente de homologação</p>
    <h2 id="homologTestTitle">Ambiente de testes</h2>
    <p id="homologTestDescription">Você está acessando uma versão exclusiva para testes e validações. Os dados e operações realizados aqui não representam o ambiente de produção.</p>
    <span class="homolog-test-badge"><i class="bi bi-shield-exclamation" aria-hidden="true"></i>Modal permanente de homologação</span>
  </section>
</div>
<?php endif; ?>
