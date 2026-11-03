<?php
$id = (int) ($app['page']['params']['id'] ?? 0);
ob_start(); ?>
<div x-data="reqShow(<?= $id ?>)" x-init="init()">
  <header class="page-header">
    <div><h2>Detalhes da solicitação</h2><p>Acompanhe o histórico, os itens e as ações permitidas para este processo.</p></div>
  </header>
  <div x-show="loading && !r" class="alert alert-info">Carregando solicitação…</div>
  <div x-show="error && !r" class="alert alert-danger alpine-flex justify-content-between align-items-center" role="alert">
    <span x-text="error || 'Não foi possível carregar a solicitação.'"></span>
    <button type="button" class="btn btn-sm btn-outline-danger" @click="load()">Tentar novamente</button>
  </div>
  <template x-if="r">
    <div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <div>
          <div class="text-muted" x-text="r.code"></div>
          <h2 class="h4" x-text="r.purpose"></h2>
          <span class="badge text-bg-primary" x-text="r.status_label"></span>
        </div>
        <div class="ms-auto">
          <button class="btn btn-primary" x-show="r.next_action && r.next_action.key==='approve'" @click="showApprove=true">Aprovar / Reprovar</button>
          <button class="btn btn-primary" x-show="r.next_action && r.next_action.key==='start_picking'" @click="act('/start-picking')">Iniciar separação</button>
          <button class="btn btn-primary" x-show="r.next_action && r.next_action.key==='ready'" @click="act('/ready')">Marcar como pronta</button>
          <a class="btn btn-primary" x-show="r.next_action && r.next_action.key==='deliver'" :href="'<?= e(url('/operacao')) ?>/' + r.id + '/entrega'">Registrar entrega</a>
          <a class="btn btn-outline-primary" x-show="r.next_action && r.next_action.key==='protocol' && r.delivery" :href="'<?= e(url('/protocolos')) ?>/' + r.delivery.id">Ver protocolo</a>
        </div>
      </div>
      <div class="card card-body mb-3">
        <div>Solicitante: <span x-text="r.requester.name"></span></div>
        <div>Departamento: <span x-text="r.department?.name || '—'"></span></div>
        <div>Indústria: <span x-text="r.industry?.name || '—'"></span></div>
        <div>Destinatário: <span x-text="r.recipient || '—'"></span></div>
        <div x-show="r.needs_purchase" class="text-warning">Requer compra</div>
      </div>
      <div class="card card-body mb-3">
        <h3 class="h6">Itens</h3>
        <template x-for="it in r.items" :key="it.id">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span x-text="it.item.code + ' — ' + it.item.name"></span>
            <span x-text="'ped. ' + it.qty_requested + (it.qty_approved!=null ? ' / apr. ' + it.qty_approved : '')"></span>
          </div>
        </template>
      </div>
      <div class="card card-body mb-3">
        <h3 class="h6">Histórico</h3>
        <div class="timeline">
          <template x-for="h in r.history" :key="h.created_at + h.to">
            <div class="timeline-item">
              <div class="small text-muted" x-text="Api.fmt.datetime(h.created_at) + ' · ' + (h.user?.name||'')"></div>
              <div x-text="h.to_label"></div>
            </div>
          </template>
        </div>
      </div>
      <div class="card card-body" x-show="showApprove">
        <h3 class="h6">Aprovação</h3>
        <template x-for="it in r.items" :key="it.id">
          <div class="d-flex gap-2 mb-2 align-items-center">
            <span class="flex-grow-1" x-text="it.item.name"></span>
            <input class="form-control" style="width:90px" type="number" min="0" :max="it.qty_requested" x-model.number="it.qty_approved">
          </div>
        </template>
        <textarea class="form-control mb-2" placeholder="Justificativa (obrigatória para reprovar)" x-model="just"></textarea>
        <button class="btn btn-success" @click="approve">Aprovar</button>
        <button class="btn btn-outline-danger" @click="reject">Reprovar</button>
      </div>
    </div>
  </template>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function reqShow(id) {
  return {
    r: null, showApprove: false, just: '', loading: false, error: '', offSync: null,
    init() {
      this.load();
      this.offSync = window.BrindesSync?.listen(() => this.load());
    },
    async load() {
      if (this.loading) return;
      this.loading = true;
      this.error = '';
      try {
        const { data } = await Api.get('/api/requests/' + id);
        if (!data || typeof data !== 'object') throw new Error('A API não retornou os dados da solicitação.');
        this.r = data;
        if (!Array.isArray(this.r.items)) this.r.items = [];
        if (!Array.isArray(this.r.history)) this.r.history = [];
        this.r.items.forEach(i => { if (i.qty_approved == null) i.qty_approved = i.qty_requested; });
      } catch (e) {
        if (this.r) {
          this.error = '';
          UI.toast('Não foi possível atualizar agora. Os dados carregados continuam disponíveis.', 'err');
        } else {
          this.error = e.message || 'Não foi possível carregar a solicitação.';
          UI.toast(this.error, 'err');
        }
      } finally { this.loading = false; }
    },
    async act(path) {
      try { this.r = (await Api.post('/api/requests/' + id + path)).data; UI.toast('Atualizado.', 'ok'); }
      catch (e) { UI.toast(e.message, 'err'); }
    },
    async approve() {
      try {
        this.r = (await Api.post('/api/requests/' + id + '/approve', {
          justification: this.just,
          items: this.r.items.map(i => ({ item_id: i.item.id, qty_approved: Number(i.qty_approved) }))
        })).data;
        this.showApprove = false; UI.toast('Aprovada.', 'ok');
      } catch (e) { UI.toast(e.message, 'err'); }
    },
    async reject() {
      try { this.r = (await Api.post('/api/requests/' + id + '/reject', { justification: this.just })).data; this.showApprove = false; }
      catch (e) { UI.toast(e.message, 'err'); }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
