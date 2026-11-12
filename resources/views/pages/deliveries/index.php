<?php ob_start(); ?>
<div x-data="protIndex()" x-init="load()">
  <header class="page-header">
    <div><h2>Entregas e comprovantes</h2><p>Agrupe protocolos pelo mesmo e-mail e envie todos em uma única mensagem.</p></div>
  </header>
  <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <div>
      <label class="form-label small mb-1">Buscar</label>
      <input class="form-control" style="max-width:220px" placeholder="Código, nome…" x-model="q" @input="debounced()">
    </div>
    <div>
      <label class="form-label small mb-1">De</label>
      <input type="date" class="form-control" x-model="from" @change="load()">
    </div>
    <div>
      <label class="form-label small mb-1">Até</label>
      <input type="date" class="form-control" x-model="to" @change="load()">
    </div>
  </div>
  <template x-for="group in groupedRows()" :key="group.key">
    <section class="card card-body mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
        <div>
          <div class="fw-semibold" x-text="group.email || 'Sem e-mail informado'"></div>
          <div class="small text-muted" x-show="group.industryName" x-text="'Indústria: ' + group.industryName"></div>
          <div class="small text-muted" x-text="group.rows.length + (group.rows.length === 1 ? ' protocolo' : ' protocolos')"></div>
        </div>
        <button class="btn btn-primary btn-sm" type="button" x-show="group.email && group.rows.length > 1" @click="sendGroup(group)" :disabled="sendingKey === group.key">
          <span x-text="sendingKey === group.key ? 'Enviando…' : 'Enviar todos em um e-mail'"></span>
        </button>
      </div>
      <div class="list-group list-group-flush">
        <template x-for="d in group.rows" :key="d.id">
          <a class="list-group-item list-group-item-action px-0 text-decoration-none" :href="'<?= e(url('/protocolos')) ?>/' + d.id">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <b x-text="d.code"></b>
                <span class="badge text-bg-secondary ms-1" x-text="d.type_label"></span>
                <span class="ms-2" x-text="d.received_by_name"></span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge" :class="{
                  'text-bg-success': d.email_status?.key === 'sent',
                  'text-bg-warning': d.email_status?.key === 'queued',
                  'text-bg-danger': d.email_status?.key === 'failed',
                  'text-bg-secondary': !['sent', 'queued', 'failed'].includes(d.email_status?.key)
                }" x-text="d.email_status?.label || 'Não enviado'"></span>
                <span class="small text-muted" x-text="Api.fmt.datetime(d.created_at)"></span>
              </div>
            </div>
          </a>
        </template>
      </div>
    </section>
  </template>
  <p class="text-muted" x-show="!rows.length">Nenhum comprovante neste período.</p>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function protIndex() {
  return {
    q: '', from: '', to: '', rows: [], t: null, sendingKey: '',
    debounced() { clearTimeout(this.t); this.t = setTimeout(() => this.load(), 300); },
    groupedRows() {
      const groups = {};
      this.rows.forEach(function (row) {
        const email = String(row.received_by_email || '').trim().toLowerCase();
        const industryId = row.industry && row.industry.id ? String(row.industry.id) : 'sem-industria';
        const key = email ? (industryId + ':' + email) : ('protocol-' + row.id);
        if (!groups[key]) groups[key] = { key: key, email: email, industryName: row.industry?.name || '', rows: [] };
        groups[key].rows.push(row);
      });
      return Object.values(groups);
    },
    async load() {
      const params = { q: this.q, from: this.from, to: this.to, page: 1, per_page: 100 };
      const first = await Api.get('/api/deliveries', params);
      const rows = first.data || [];
      const lastPage = Number(first.meta?.last_page || 1);
      for (let page = 2; page <= lastPage; page++) {
        const next = await Api.get('/api/deliveries', { ...params, page });
        rows.push(...(next.data || []));
      }
      this.rows = rows;
    },
    async sendGroup(group) {
      if (!group.email || group.rows.length < 2) return;
      this.sendingKey = group.key;
      try {
        const response = await Api.post('/api/deliveries/group-resend', { ids: group.rows.map(function (row) { return row.id; }) });
        const data = response.data || {};
        const status = data.email_status || {};
        UI.toast(status.key === 'sent'
          ? data.count + ' protocolos enviados em um único e-mail.'
          : 'O grupo não foi confirmado pelo SMTP: ' + (status.label || 'Não enviado') + '.',
          status.key === 'sent' ? 'ok' : 'err');
      } catch (e) { UI.toast(e.message, 'err'); }
      finally { this.sendingKey = ''; }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
