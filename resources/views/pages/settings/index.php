<?php ob_start(); ?>
<div class="card card-body" style="max-width:760px" x-data="settingsPage()" x-init="load()">
  <header class="section-header">
    <div><h2>Configurações</h2><p>Personalize a marca e os parâmetros operacionais do sistema.</p></div>
  </header>
  <div class="mb-3">
    <label class="form-label">Nome da empresa</label>
    <input class="form-control" x-model="f.company_name">
  </div>
  <div class="mb-3">
    <label class="form-label">Cor principal</label>
    <input class="form-control form-control-color" type="color" x-model="f.primary_color" @input="document.documentElement.style.setProperty('--brand-primary', f.primary_color)">
  </div>
  <div class="mb-3">
    <label class="form-label">Logo</label>
    <input class="form-control" type="file" accept="image/*" @change="logo=$event.target.files[0]">
    <img class="mt-2" style="max-height:48px" x-show="f.logo_url" :src="f.logo_url">
  </div>
  <div class="mb-3">
    <label class="form-label">Dias para solicitação parada</label>
    <input class="form-control" type="number" min="1" x-model="f.stalled_days">
  </div>
  <div class="mb-3">
    <label class="form-label">E-mail no evento</label>
    <select class="form-select" x-model="f.event_email_mode">
      <option value="por_retirada">A cada retirada</option>
      <option value="consolidado">Resumo no encerramento</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">E-mails de alerta (separados por vírgula)</label>
    <input class="form-control" x-model="alertText">
  </div>
  <div class="mb-3">
    <label class="form-label">URL do formulário Lecom de suprimentos</label>
    <input class="form-control" type="url" x-model="f.lecom_supply_form_url" placeholder="https://.../formulario?brinde_id={id}&codigo={code}">
    <div class="form-text">Placeholders disponíveis: {id}, {code}, {name}, {quantity}, {unit_value}, {category}, {codigo}, {versao}. Para o processo de suprimentos, use {codigo}=26 e {versao}=10 na URL.</div>
  </div>
  <hr>
  <h2 class="h6 mb-3">Integração com o portal Lecom</h2>
  <div class="mb-3">
    <label class="form-label">Endereço do portal Lecom</label>
    <input class="form-control" type="url" x-model="f.lecom_portal_url" placeholder="https://seu-portal.lecom.com.br">
    <div class="form-text">O valor inicial é o portal de homologação encontrado na biblioteca Lecom. Informe aqui o endereço base do ambiente de produção quando aplicável.</div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-sm-6">
      <label class="form-label">Código do processo</label>
      <input class="form-control" type="number" min="1" x-model="f.lecom_process_id">
    </div>
    <div class="col-sm-6">
      <label class="form-label">Versão publicada</label>
      <input class="form-control" type="number" min="1" x-model="f.lecom_process_version">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Base da API Lecom</label>
    <input class="form-control" type="url" x-model="f.lecom_api_base_url" placeholder="https://api.lecom.com.br/service/bpm/api">
    <div class="form-text">Padrão documentado pela biblioteca: https://api.lecom.com.br/service/bpm/api</div>
  </div>
  <div class="alert alert-light border small" x-show="formUrl() || f.lecom_api_base_url">
    <div class="fw-semibold mb-1">Links gerados</div>
    <div x-show="formUrl()"><span class="text-muted">Abertura pelo portal:</span> <code class="user-select-all" x-text="formUrl()"></code></div>
    <div><span class="text-muted">Endpoint para criar a instância:</span> <code class="user-select-all" x-text="apiStartUrl()"></code></div>
    <div x-show="workspaceStartUrl()"><span class="text-muted">Rota Workspace usada pela biblioteca:</span> <code class="user-select-all" x-text="workspaceStartUrl()"></code></div>
    <div class="mt-2 text-muted">A API exige a chave <code>apikey</code> no servidor, o cabeçalho <code>X-Server</code> com o domínio do portal e este corpo:</div>
    <pre class="mb-0 mt-1"><code x-text="apiBody()"></code></pre>
  </div>
  <div class="form-text mb-3">A chave da API não é salva nem exibida nesta tela. O script da biblioteca usa a rota Workspace com método <code>PUT</code> e o cookie SSO do Lecom; depois, use o <code>processInstanceId</code> retornado para abrir <code>/workspace/form-app/{id}/1/1?isNewForm=true</code>.</div>
  <div class="alert" :class="f.mail_smtp ? 'alert-success' : 'alert-warning'" x-show="f.mail_driver">
    <span x-show="f.mail_smtp">Envio de e-mail por SMTP ativo.</span>
    <span x-show="!f.mail_smtp">Nesta demonstração o e-mail fica registrado no sistema. No servidor de vocês, configure SMTP no arquivo .env (MAIL_DRIVER=smtp + host/usuário/senha) para o comprovante da retirada sair na hora.</span>
  </div>
  <button class="btn btn-primary" @click="save">Salvar</button>
  <button class="btn btn-outline-secondary" @click="testMail">Testar e-mail</button>
  <a class="btn btn-outline-secondary" :href="Api.url('/api/admin/backup')">Baixar backup do banco</a>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function settingsPage() {
  return {
    f: {}, logo: null, alertText: '',
    async load() {
      this.f = (await Api.get('/api/settings')).data;
      this.alertText = (this.f.alert_emails || []).join(', ');
    },
    async save() {
      try {
        await Api.put('/api/settings', {
          company_name: this.f.company_name,
          primary_color: this.f.primary_color,
          stalled_days: Number(this.f.stalled_days),
          event_email_mode: this.f.event_email_mode,
          alert_emails: this.alertText,
          lecom_supply_form_url: this.f.lecom_supply_form_url || '',
          lecom_portal_url: this.f.lecom_portal_url || '',
          lecom_api_base_url: this.f.lecom_api_base_url || '',
          lecom_process_id: Number(this.f.lecom_process_id) || 26,
          lecom_process_version: Number(this.f.lecom_process_version) || 10
        });
        if (this.logo) {
          const fd = new FormData(); fd.append('logo', this.logo);
          await Api.upload('/api/settings/logo', fd);
        }
        UI.toast('Configurações salvas.', 'ok');
        location.reload();
      } catch (e) { UI.toast(e.message, 'err'); }
    },
    formUrl() {
      const base = String(this.f.lecom_portal_url || '').trim().replace(/\/+$/, '');
      if (!base) return '';
      const processId = Number(this.f.lecom_process_id) || 26;
      const version = Number(this.f.lecom_process_version) || 10;
      return `${base}/form-web/?processId=${encodeURIComponent(processId)}&version=${encodeURIComponent(version)}&newWS=true`;
    },
    apiStartUrl() {
      const base = String(this.f.lecom_api_base_url || 'https://api.lecom.com.br/service/bpm/api').trim().replace(/\/+$/, '');
      return `${base}/v1/process-instances`;
    },
    workspaceStartUrl() {
      const base = String(this.f.lecom_portal_url || '').trim().replace(/\/+$/, '');
      if (!base) return '';
      const processId = Number(this.f.lecom_process_id) || 26;
      const version = Number(this.f.lecom_process_version) || 10;
      return `${base}/workspace/api/process/start?processId=${encodeURIComponent(processId)}&version=${encodeURIComponent(version)}`;
    },
    apiBody() {
      return JSON.stringify({
        processId: Number(this.f.lecom_process_id) || 26,
        version: Number(this.f.lecom_process_version) || 10,
        language: 'pt_BR'
      }, null, 2);
    },
    async testMail() {
      try { const { data } = await Api.post('/api/settings/test-email'); UI.toast(data.message, 'ok'); }
      catch (e) { UI.toast(e.message, 'err'); }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
