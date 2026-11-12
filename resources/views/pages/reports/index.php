<?php ob_start(); ?>
<div x-data="reportsPage()" x-init="load()">
  <header class="page-header">
    <div><h2>Relatórios</h2><p>Filtre os dados operacionais e exporte análises completas.</p></div>
  </header>
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <select class="form-select" x-model="type" @change="typeChanged()">
        <option value="">Escolha o relatório</option>
        <template x-for="t in types" :key="t.id"><option :value="t.id" x-text="t.label"></option></template>
      </select>
    </div>
    <div class="col-md-4" x-show="type === 'event_report'" x-cloak>
      <select class="form-select" x-model="eventId" @change="run()">
        <option value="">Selecione o Evento/Feirão</option>
        <template x-for="event in events" :key="event.id">
          <option :value="event.id" x-text="event.name + (event.starts_on ? ' · ' + event.starts_on : '')"></option>
        </template>
      </select>
    </div>
    <div class="col-md-3"><input type="date" class="form-control" x-model="from" @change="run()"></div>
    <div class="col-md-3"><input type="date" class="form-control" x-model="to" @change="run()"></div>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-outline-secondary" type="button" @click="exp('csv')" :disabled="!canRun">CSV</button>
      <button class="btn btn-outline-primary" type="button" @click="exp('xlsx')" :disabled="!canRun">Excel moderno</button>
    </div>
  </div>
  <div class="row g-3 mb-3" x-show="summary.length" x-cloak>
    <template x-for="card in summary" :key="card.label">
      <div class="col-6 col-xl-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-muted small" x-text="card.label"></div><div class="fs-3 fw-semibold text-primary" x-text="card.value"></div></div></div></div>
    </template>
  </div>
  <div class="alert alert-info" x-show="type === 'event_report' && !eventId">Selecione um Evento/Feirão para carregar os indicadores e liberar a exportação.</div>
  <div class="table-responsive" x-show="rows.length">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><template x-for="c in cols" :key="c"><th x-text="c"></th></template></tr></thead>
      <tbody><template x-for="(row, i) in rows" :key="i"><tr><template x-for="c in cols" :key="c"><td x-text="row[c]"></td></template></tr></template></tbody>
    </table>
  </div>
</div>
<?php $content = ob_get_clean(); ob_start(); ?>
<script>
function reportsPage() {
  const iso = (d) => d.toISOString().slice(0,10);
  const t = new Date();
  return {
    types: [], events: [], type: '', eventId: '', from: iso(new Date(t.getFullYear(), t.getMonth(), 1)), to: iso(t), rows: [], cols: [], summary: [],
    get canRun() { return !!this.type && (this.type !== 'event_report' || !!this.eventId); },
    async load() {
      this.types = (await Api.get('/api/reports')).data;
      this.events = (await Api.get('/api/events', { per_page: 100 })).data || [];
    },
    typeChanged() { this.eventId = ''; this.rows = []; this.cols = []; this.summary = []; this.run(); },
    async run() {
      if (!this.canRun) { this.rows = []; this.cols = []; this.summary = []; return; }
      const params = { from: this.from, to: this.to };
      if (this.eventId) params.event_id = this.eventId;
      const { data } = await Api.get('/api/reports/' + this.type, params);
      this.cols = data.columns || [];
      this.rows = (data.rows || []).map(function (r) { const o = {}; (data.columns || []).forEach(function (c, i) { o[c] = Array.isArray(r) ? r[i] : r[c]; }); return o; });
      this.summary = Object.entries(data.summary || {}).map(([label, value]) => ({ label, value }));
    },
    exp(fmt) {
      if (!this.canRun) return;
      const params = { from: this.from, to: this.to, format: fmt };
      if (this.eventId) params.event_id = this.eventId;
      location.href = Api.url('/api/reports/' + this.type, params);
    }
  };
}
</script>
<?php $scripts = ob_get_clean(); include BASE_PATH . '/resources/views/layouts/app.php';
