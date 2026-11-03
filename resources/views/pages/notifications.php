<?php ob_start(); ?>
<div x-data="notifPage()" x-init="load()">
  <header class="page-header">
    <div><h2>Notificações</h2><p>Veja atualizações e pendências relacionadas à sua operação.</p></div>
  </header>
  <div class="alert alert-danger" x-show="error" x-text="error"></div>
  <button class="btn btn-sm btn-outline-secondary mb-3" @click="readAll">Marcar todas como lidas</button>
  <template x-for="n in rows" :key="n.id">
    <a class="card card-body d-block mb-2 text-decoration-none" :href="n.link_url ? Api.url(n.link_url) : '#'" @click="read(n)">
      <div class="d-flex justify-content-between">
        <b x-text="n.subject"></b>
        <span class="badge text-bg-primary" x-show="!n.read_at">Nova</span>
      </div>
      <div class="small text-muted" x-text="Api.fmt.datetime(n.created_at)"></div>
    </a>
  </template>
  <template x-if="!rows.length"><div class="empty">Nenhuma notificação.</div></template>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function notifPage() {
  return {
    rows: [], error: '',
    async load() {
      try { this.rows = (await Api.get('/api/notifications')).data || []; }
      catch (e) { this.error = e.message || 'Não foi possível carregar as notificações.'; }
    },
    async read(n) { if (!n.read_at) await Api.post('/api/notifications/' + n.id + '/read'); },
    async readAll() {
      try { await Api.post('/api/notifications/read-all'); await this.load(); }
      catch (e) { this.error = e.message || 'Não foi possível marcar as notificações.'; }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
