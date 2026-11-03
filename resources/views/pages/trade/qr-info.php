<?php
$code = (string) ($app['page']['params']['code'] ?? '');
ob_start(); ?>
<section class="card card-body" x-data="tradeQrInfo(<?= json_encode($code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" x-init="load()">
  <div x-show="loading" class="text-muted">Carregando consulta…</div>
  <div x-show="error" class="alert alert-danger mb-0" role="alert" x-text="error"></div>
  <template x-if="data">
    <div>
      <p class="text-muted small mb-1">Solicitação TRADE</p>
      <h2 class="h4 mb-4" x-text="data.code"></h2>
      <dl class="row mb-0">
        <dt class="col-sm-3">Indústria</dt>
        <dd class="col-sm-9" x-text="data.industry || '—'"></dd>
      </dl>
      <h3 class="h6 mt-3">Produtos e saldo disponível</h3>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Produto</th><th class="text-end">Saldo</th></tr></thead>
          <tbody>
            <template x-for="product in data.products" :key="product.code">
              <tr><td x-text="product.name"></td><td class="text-end" x-text="Number(product.saldo).toLocaleString('pt-BR')"></td></tr>
            </template>
            <tr x-show="!data.products.length"><td colspan="2" class="text-muted">Nenhum produto nesta solicitação.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </template>
</section>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function tradeQrInfo(code) {
  return {
    data: null, error: '', loading: true,
    async load() {
      try {
        const { data } = await Api.get('/api/trade/qr-info', { code });
        this.data = data;
      } catch (e) {
        this.error = e.message || 'Não foi possível carregar os dados deste QR Code.';
      } finally {
        this.loading = false;
      }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
