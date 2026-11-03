<?php ob_start(); ?>
<div x-data="reqIndex()" x-init="init()">
  <header class="page-header">
    <div><h2>Solicitações</h2><p>Acompanhe o andamento das solicitações e as próximas ações.</p></div>
  </header>
  <div class="alert alert-info small mb-3">
    <strong>Solicitações internas</strong> são pedidos para consumo interno da empresa, com aprovação, separação e entrega pelo estoque. Elas não representam uma compra externa de brindes.
  </div>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <select class="form-select" style="width:auto" x-model="status" @change="load()">
      <option value="">Todos</option>
      <template x-for="s in statuses" :key="s"><option :value="s" x-text="Api.labels.requestStatus[s]?.label || s"></option></template>
    </select>
    <?php if (can('requests.create') && !is_cd_operations()): ?>
    <a class="btn btn-primary ms-auto" href="<?= e(url('/solicitacoes/nova')) ?>">Nova solicitação</a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Código</th><th>Status</th><th>Solicitante</th><th>Finalidade</th><th>Valor</th><th>Data</th></tr></thead>
      <tbody>
        <template x-for="r in rows" :key="r.id">
          <tr>
            <td><a :href="'<?= e(url('/solicitacoes')) ?>/' + r.id" x-text="r.code"></a></td>
            <td><span class="badge text-bg-secondary" x-text="r.status_label"></span></td>
            <td x-text="r.requester.name"></td>
            <td x-text="r.purpose"></td>
            <td x-text="Api.fmt.money(r.total_value)"></td>
            <td x-text="Api.fmt.datetime(r.created_at)"></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function reqIndex() {
  return {
    status: new URLSearchParams(location.search).get('status') || '',
    rows: [], loading: false, offSync: null,
    statuses: Object.keys(Api.labels.requestStatus),
    init() {
      this.load();
      this.offSync = window.BrindesSync?.listen(() => this.load());
    },
    async load() {
      if (this.loading) return;
      this.loading = true;
      try {
        const { data } = await Api.get('/api/requests', { status: this.status });
        this.rows = Array.isArray(data) ? data : [];
      } catch (e) {
        UI.toast(e.message || 'Não foi possível carregar as solicitações.', 'err');
      } finally { this.loading = false; }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
