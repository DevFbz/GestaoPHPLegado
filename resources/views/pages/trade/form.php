<?php ob_start(); ?>
<div class="card card-body" x-data="tradeForm()" x-init="init()">
  <p class="text-muted">Informe indústria, brinde, quantidade e finalidade. O número do chamado é preenchido só se o pedido for aprovado. A NF é anexada quando o material chegar no CD.</p>
  <form x-ref="form" @submit.prevent="save">
    <div x-show="formError" class="alert alert-danger" x-text="formError" role="alert"></div>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Indústria *</label>
        <select class="form-select" name="industry_id" x-model="f.industry_id" required>
          <option value="">Selecione</option>
          <template x-for="d in inds" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
        </select></div>
      <div class="col-md-6"><label class="form-label">Tipo de ação</label>
        <select class="form-select" x-model="f.action_type">
          <option value="campanha">Campanha</option>
          <option value="premiacao">Premiação</option>
          <option value="evento">Evento</option>
          <option value="feirao">Feirão</option>
          <option value="outro">Outro</option>
        </select></div>
      <div class="col-md-6"><label class="form-label">Descrição / finalidade *</label>
        <input class="form-control" name="purpose" x-model="f.purpose" required placeholder="Ex.: Air Fryer 5L — campanha setembro"></div>
      <div class="col-md-6"><label class="form-label">Para quem será entregue</label>
        <input class="form-control" name="recipient" x-model="f.recipient" placeholder="Vendedor, cliente, evento…"></div>
      <div class="col-md-6"><label class="form-label">Local de entrega</label>
        <input class="form-control" name="delivery_place" x-model="f.delivery_place" placeholder="CD Belford Roxo"></div>
      <div class="col-12"><label class="form-label">Observações</label>
        <textarea class="form-control" name="notes" x-model="f.notes"></textarea></div>
    </div>
    <h3 class="h6 mt-4">Brindes</h3>
    <input class="form-control mb-2" placeholder="Buscar brinde (físico, voucher, cartão…)" x-model="q" @input="search()">
    <div class="list-group mb-2">
      <template x-for="o in opts" :key="o.id">
        <button type="button" class="list-group-item" @click="add(o)" x-text="o.code + ' — ' + o.name"></button>
      </template>
    </div>
    <template x-for="(line, i) in items" :key="line.item_id">
      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-5" x-text="line.name"></div>
        <div class="col-md-2"><input class="form-control" :name="'items.' + i + '.qty_requested'" type="number" min="1" x-model.number="line.qty_requested" placeholder="Qtd"></div>
        <div class="col-md-3"><input class="form-control" x-model="line.unit_value" placeholder="Valor unit. R$"></div>
        <div class="col-md-1 small text-muted" x-text="Api.fmt.money((Number(line.qty_requested)||0) * (Number(String(line.unit_value).replace(',','.'))||0))"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="items.splice(i,1)">×</button></div>
      </div>
    </template>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <button class="btn btn-primary" :disabled="saving || lecomStarting">Enviar solicitação de compra</button>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function tradeForm() {
  return {
    f: { industry_id: '', purpose: '', recipient: '', action_type: 'campanha', delivery_place: 'CD Belford Roxo', notes: '' },
    inds: [], q: '', opts: [], items: [], saving: false, lecomStarting: false, formError: '', t: null,
    async init() {
      try {
        this.inds = (await Api.get('/api/industries', { all: 1 })).data || [];
      } catch (e) {
        this.formError = 'Não foi possível carregar as indústrias. Atualize a página ou procure o Administrador.';
      }
    },
    search() {
      clearTimeout(this.t);
      this.t = setTimeout(async () => {
        if (!this.q) { this.opts = []; return; }
        try {
          this.opts = (await Api.get('/api/items/options', { q: this.q })).data || [];
        } catch (e) {
          this.opts = [];
          this.formError = e.message || 'Não foi possível buscar os brindes.';
        }
      }, 250);
    },
    add(o) {
      if (this.items.find(i => i.item_id === o.id)) return;
      this.items.push({ item_id: o.id, name: o.code + ' — ' + o.name, qty_requested: 1, unit_value: o.unit_value ?? '' });
      this.opts = []; this.q = '';
    },
    readCookie(name) {
      const target = String(name).toLowerCase();
      const pair = document.cookie.split(';').map(part => part.trim()).find(part => {
        const separator = part.indexOf('=');
        return separator > 0 && part.slice(0, separator).toLowerCase() === target;
      });
      if (!pair) return '';
      const index = pair.indexOf('=');
      try { return decodeURIComponent(pair.slice(index + 1)); } catch (_) { return pair.slice(index + 1); }
    },
    findLecomValue(value, key) {
      if (!value || typeof value !== 'object') return '';
      if (value[key] !== undefined && value[key] !== null && String(value[key]) !== '') return String(value[key]);
      for (const child of Object.values(value)) {
        const found = this.findLecomValue(child, key);
        if (found) return found;
      }
      return '';
    },
    async readLecomResponse(response) {
      const raw = await response.text();
      let data = null;
      try { data = raw ? JSON.parse(raw) : null; } catch (_) {}
      return { response, data };
    },
    async startLecomWithSso(portal, processId, processVersion, ticket) {
      const base = String(portal || window.location.origin).replace(/\/+$/, '');
      const endpoint = base + '/workspace/api/process/start?processId='
        + encodeURIComponent(processId) + '&version=' + encodeURIComponent(processVersion);
      const headers = {
        'Content-Type': 'application/json;charset=UTF-8',
        'Accept': 'application/json, text/plain, */*',
        'language': 'pt_BR',
        'ticket-sso': ticket
      };
      if (this.readCookie('testMode').toLowerCase() === 'true') {
        headers['test-mode'] = 'true';
        const testUser = this.readCookie('LecomEnvironmentMode');
        if (testUser) headers['test-user'] = testUser;
      }
      let result;
      try {
        result = await this.readLecomResponse(await fetch(endpoint, {
          method: 'PUT', headers, body: '{}', credentials: 'include', cache: 'no-store'
        }));
      } catch (error) {
        const wrapped = new Error('Não foi possível chamar o Workspace do Lecom.');
        wrapped.code = 'LECOM_SSO_NETWORK_ERROR';
        wrapped.cause = error;
        throw wrapped;
      }
      if (!result.response.ok) {
        const message = result.response.status === 403
          ? 'O Lecom recusou a abertura do processo. Verifique sua permissão no processo e o login de homologação.'
          : 'O Workspace do Lecom recusou a criação da instância (HTTP ' + result.response.status + ').';
        const error = new Error(message);
        error.code = 'LECOM_SSO_START_FAILED';
        throw error;
      }
      const processInstanceId = this.findLecomValue(result.data, 'processInstanceId');
      if (!processInstanceId) throw new Error('O Lecom respondeu sem informar o identificador da nova instância.');
      const activityInstanceId = this.findLecomValue(result.data, 'activityInstanceId') || '1';
      const cycle = this.findLecomValue(result.data, 'cycle') || '1';
      return base + '/workspace/form-app/' + encodeURIComponent(processInstanceId) + '/'
        + encodeURIComponent(activityInstanceId) + '/' + encodeURIComponent(cycle) + '?isNewForm=true';
    },
    async openLecom() {
      if (this.lecomStarting) return;
      this.lecomStarting = true;
      const tab = window.open('about:blank', '_blank');
      const portal = <?= json_script(trim((string) setting('lecom_portal_url', ''))) ?>;
      const processId = <?= json_script((int) setting('lecom_process_id', '26')) ?>;
      const processVersion = <?= json_script((int) setting('lecom_process_version', '10')) ?>;
      try {
        let target = '';
        const ticket = this.readCookie('LecomSSOTicket') || this.readCookie('lecomssoticket');
        if (ticket) target = await this.startLecomWithSso(portal, processId, processVersion, ticket);
        if (!target) {
          const fallback = await Api.postIdem('/api/lecom/process/start', {});
          target = fallback?.data?.url || '';
        }
        if (!target) throw new Error('O Lecom não retornou o endereço do formulário.');
        if (tab && !tab.closed) tab.location.href = target;
        else window.location.href = target;
      } catch (error) {
        if (tab && !tab.closed) tab.close();
        UI.toast(error.message || 'Não foi possível abrir o chamado no Lecom.', 'err');
      } finally {
        this.lecomStarting = false;
      }
    },
    async save() {
      this.formError = '';
      UI.clearErrors(this.$refs.form);
      const errors = {};
      if (!this.f.industry_id) errors.industry_id = 'Selecione a indústria.';
      if (!String(this.f.purpose || '').trim()) errors.purpose = 'Informe a finalidade da solicitação.';
      if (this.items.length === 0) errors.items = 'Inclua pelo menos um brinde.';
      this.items.forEach((line, index) => {
        if (!Number.isInteger(Number(line.qty_requested)) || Number(line.qty_requested) < 1) {
          errors.items = 'Informe uma quantidade maior que zero para todos os brindes.';
          errors['items.' + index + '.qty_requested'] = 'Quantidade inválida.';
        }
      });
      if (Object.keys(errors).length) {
        this.formError = 'Verifique os campos destacados.';
        UI.fieldErrors(this.$refs.form, errors);
        return;
      }
      this.saving = true;
      try {
        const { data } = await Api.postIdem('/api/trade/requests', {
          ...this.f,
          industry_id: Number(this.f.industry_id),
          items: this.items.map(i => ({
            item_id: Number(i.item_id),
            qty_requested: Number(i.qty_requested),
            unit_value: i.unit_value === '' ? null : Number(String(i.unit_value).replace(',', '.'))
          }))
        });
        location.href = Api.url('/solicitacoes-trade/' + data.id);
      } catch (e) {
        if (e.code === 'VALIDATION_ERROR') {
          this.formError = e.message || 'Verifique os campos destacados.';
          UI.fieldErrors(this.$refs.form, e.fields);
        } else {
          this.formError = e.message || 'Não foi possível criar a solicitação.';
          UI.toast(this.formError, 'err');
        }
      } finally {
        this.saving = false;
      }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
