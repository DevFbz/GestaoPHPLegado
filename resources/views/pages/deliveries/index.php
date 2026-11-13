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
  <div class="alert alert-danger" x-show="loadError" x-text="loadError"></div>
  <template x-for="group in groupedRows()" :key="group.key">
    <section class="card card-body mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
        <div>
          <div class="fw-semibold" x-text="group.email || 'Sem e-mail informado'"></div>
          <div class="small text-muted" x-show="group.industryNames" x-text="'Indústrias: ' + group.industryNames"></div>
          <div class="small text-muted" x-text="group.rows.length + (group.rows.length === 1 ? ' protocolo' : ' protocolos')"></div>
        </div>
        <button class="btn btn-primary btn-sm" type="button" x-show="group.email && group.rows.length > 1" @click="sendGroup(group)" :disabled="sendingKey === group.key || group.rows.some(function (row) { return sendingKey === 'protocol-' + row.id; })">
          <span x-text="sendingKey === group.key ? 'Enviando…' : 'Enviar e-mail (manualmente)'"></span>
        </button>
      </div>
      <div class="list-group list-group-flush">
        <template x-for="d in group.rows" :key="d.id">
          <div class="list-group-item px-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <a class="text-decoration-none flex-grow-1" :href="'<?= e(url('/protocolos')) ?>/' + d.id">
                <b x-text="d.code"></b>
                <span class="badge text-bg-secondary ms-1" x-text="d.type_label"></span>
                <span class="ms-2" x-text="d.received_by_name"></span>
              </a>
              <div class="d-flex align-items-center gap-2">
                <span class="badge" :class="{
                  'text-bg-success': d.email_status?.key === 'sent',
                  'text-bg-warning': d.email_status?.key === 'queued',
                  'text-bg-danger': d.email_status?.key === 'failed',
                  'text-bg-secondary': !['sent', 'queued', 'failed'].includes(d.email_status?.key)
                }" x-text="d.email_status?.label || 'Não enviado'"></span>
                <span class="small text-muted" x-text="Api.fmt.datetime(d.created_at)"></span>
                <button class="btn btn-outline-primary btn-sm" type="button" x-show="group.rows.length === 1" @click="sendSingle(d)" :disabled="sendingKey === ('protocol-' + d.id) || sendingKey === group.key">
                  <span x-text="sendingKey === ('protocol-' + d.id) ? 'Enviando…' : 'Enviar e-mail (manualmente)'"></span>
                </button>
              </div>
            </div>
          </div>
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
    q: '', from: '', to: '', rows: [], t: null, sendingKey: '', loadError: '',
    debounced() { clearTimeout(this.t); this.t = setTimeout(() => this.load(), 300); },
    groupedRows() {
      const groups = {};
      this.rows.forEach(function (row) {
        const email = String(row.received_by_email || '').trim().toLowerCase();
        const key = email ? email : ('protocol-' + row.id);
        if (!groups[key]) groups[key] = { key: key, email: email, industryNames: '', rows: [] };
        groups[key].rows.push(row);
        const industryName = row.industry?.name || '';
        if (industryName && !groups[key].industryNames.split(' · ').includes(industryName)) {
          groups[key].industryNames = groups[key].industryNames ? groups[key].industryNames + ' · ' + industryName : industryName;
        }
      });
      return Object.values(groups);
    },
    async load() {
      this.loadError = '';
      try {
        const params = { q: this.q, from: this.from, to: this.to, page: 1, per_page: 100 };
        const first = await Api.get('/api/deliveries', params);
        const rows = first.data || [];
        const lastPage = Number(first.meta?.last_page || 1);
        for (let page = 2; page <= lastPage; page++) {
          const next = await Api.get('/api/deliveries', { ...params, page });
          rows.push(...(next.data || []));
        }
        this.rows = rows;
      } catch (e) {
        this.loadError = e.message || 'Não foi possível carregar os comprovantes.';
      }
    },
    async sendSingle(row) {
      const key = 'protocol-' + row.id;
      if (this.sendingKey && this.sendingKey !== key) return;
      this.sendingKey = key;
      try {
        const response = await Api.post('/api/deliveries/' + row.id + '/resend');
        const data = response.data || {};
        const status = data.email_status || {};
        row.email_status = status;
        UI.toast(status.key === 'sent'
          ? 'E-mail enviado e aceito pelo Brevo.'
          : 'O e-mail não foi confirmado pelo SMTP: ' + (status.label || 'Não enviado') + '.',
          status.key === 'sent' ? 'ok' : 'err');
      } catch (e) { UI.toast(e.message, 'err'); }
      finally { this.sendingKey = ''; }
    },
    async sendGroup(group) {
      if (this.sendingKey || !group.email || group.rows.length < 2) return;
      this.sendingKey = group.key;
      try {
        const response = await Api.post('/api/deliveries/group-resend', { ids: group.rows.map(function (row) { return row.id; }) });
        const data = response.data || {};
        const status = data.email_status || {};
        group.rows.forEach(function (row) { row.email_status = status; });
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
