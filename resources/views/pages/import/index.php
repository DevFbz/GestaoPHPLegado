<?php ob_start(); ?>
<div class="card card-body" style="max-width:640px" x-data="importPage()">
  <header class="section-header">
    <div><h2>Importar dados</h2><p>Faça uma pré-visualização antes de confirmar a importação.</p></div>
  </header>
  <div class="mb-3">
    <label class="form-label">Tipo</label>
    <select class="form-select" x-model="type">
      <option value="items">Brindes</option>
      <option value="requests">Solicitações</option>
    </select>
  </div>
  <p><a :href="Api.url('/api/import/template/' + type)" download data-no-loading>Baixar modelo Excel</a></p>
  <div class="mb-3"><input class="form-control" type="file" accept=".xlsx" @change="file=$event.target.files[0]"></div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-primary" @click="preview" :disabled="busy">Pré-visualizar</button>
    <button class="btn btn-primary" @click="commit" :disabled="!canCommit || busy">Importar</button>
  </div>
  <div class="mt-3" x-show="result" x-cloak>
    <div class="alert" :class="canCommit ? 'alert-info' : 'alert-warning'">
      <strong x-text="result?.total || 0"></strong> linha(s) encontrada(s).
      <span x-show="result?.errors"> <strong x-text="result?.errors || 0"></strong> com erro.</span>
      <span x-show="canCommit"> Corrija as linhas inválidas ou clique em Importar para gravar as válidas.</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle import-preview-table">
        <thead><tr><th>Linha</th><th>Status</th><th>Conteúdo</th><th>Erros</th></tr></thead>
        <tbody>
          <template x-for="row in (result?.rows || [])" :key="row.line">
            <tr :class="row.ok ? '' : 'table-danger'">
              <td x-text="row.line"></td>
              <td><span class="badge" :class="row.ok ? 'text-bg-success' : 'text-bg-danger'" x-text="row.ok ? 'Válida' : 'Inválida'"></span></td>
              <td x-text="row.preview || '—'"></td>
              <td><span x-text="(row.errors || []).join('; ') || '—'"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div class="alert alert-success" x-show="imported" x-text="'Importação concluída: ' + (imported?.created || 0) + ' registro(s) criado(s).'"></div>
  </div>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function importPage() {
  return {
    type: 'items', file: null, result: null, busy: false, imported: null,
    get canCommit() { return !!this.result?.rows?.some((row) => row.ok); },
    async send(path) {
      if (!this.file) { UI.toast('Selecione o arquivo.', 'err'); return null; }
      this.busy = true;
      try {
        const fd = new FormData(); fd.append('file', this.file);
        const response = await Api.upload('/api/import/' + this.type + '/' + path, fd);
        return response.data;
      } finally { this.busy = false; }
    },
    async preview() {
      try { this.imported = null; this.result = await this.send('preview'); }
      catch (e) { UI.toast(e.message, 'err'); }
    },
    async commit() {
      if (!this.canCommit) { UI.toast('Nenhuma linha válida para importar.', 'err'); return; }
      try {
        this.imported = await this.send('commit');
        UI.toast('Importação concluída.', 'ok');
      } catch (e) { UI.toast(e.message, 'err'); }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
