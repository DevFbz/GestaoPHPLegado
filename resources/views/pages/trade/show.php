<?php
$id = (int) ($app['page']['params']['id'] ?? 0);
ob_start(); ?>
<div x-data="tradeShow(<?= $id ?>)" x-init="load()">
  <div x-show="loading && !r" class="alert alert-info">Carregando solicitação TRADE…</div>
  <div x-show="loadError && !r" class="alert alert-danger alpine-flex justify-content-between align-items-center" role="alert">
    <span x-text="loadError || 'Não foi possível carregar a solicitação TRADE.'"></span>
    <button type="button" class="btn btn-sm btn-outline-danger" @click="load()">Tentar novamente</button>
  </div>
  <template x-if="r">
    <div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <div>
          <h2 class="h4 mb-1" x-text="r.public_code || r.code"></h2>
          <span class="badge text-bg-primary" x-text="r.status_label"></span>
          <span class="text-muted ms-2" x-text="r.industry?.name"></span>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
          <a class="btn btn-primary" x-show="r.next_action?.key==='receive'" :href="'<?= e(url('/recebimento')) ?>?id=' + r.id">Receber no CD e anexar NF</a>
          <a class="btn btn-primary" x-show="r.next_action?.key==='withdraw'" :href="'<?= e(url('/retirada')) ?>?code=' + encodeURIComponent(r.public_code || r.code)">Registrar retirada</a>
          <button class="btn btn-outline-success" x-show="r.next_action?.key==='delivered'" @click="delivered">Marcar entregue</button>
        </div>
      </div>
      <template x-if="r.next_action?.key==='approve_ticket'">
        <div class="card card-body mb-3">
          <h3 class="h6">Aprovar e informar o chamado</h3>
          <p class="small text-muted mb-2">O TRADE já enviou a solicitação. Informe o número do chamado somente se o pedido for aprovado. A NF será anexada depois, quando o brinde chegar no CD.</p>
          <div class="row g-2">
            <div class="col-md-5">
              <input class="form-control" x-model="ticket" placeholder="Nº do chamado de compra" maxlength="50">
            </div>
            <div class="col-auto">
              <button class="btn btn-primary" :disabled="saving" @click="approve">Aprovar</button>
              <button class="btn btn-outline-danger" :disabled="saving" @click="reject">Reprovar</button>
            </div>
          </div>
        </div>
      </template>
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card card-body mb-3">
            <div class="row small">
              <div class="col-md-6"><b>Finalidade:</b> <span x-text="r.purpose"></span></div>
              <div class="col-md-6"><b>Destinatário:</b> <span x-text="r.recipient || '—'"></span></div>
              <div class="col-md-4"><b>Chamado:</b> <span x-text="r.purchase_ticket_no || '—'"></span></div>
              <div class="col-md-4"><b>NF:</b>
                <span x-text="r.invoice_no || (r.invoice_url ? 'Anexada' : '—')"></span>
                <a class="ms-1" x-show="r.invoice_url" :href="r.invoice_url" target="_blank">abrir</a>
              </div>
              <div class="col-md-4"><b>Local:</b> <span x-text="r.delivery_place || '—'"></span></div>
              <div class="col-md-4"><b>Ação:</b> <span x-text="r.action_type_label || '—'"></span></div>
              <div class="col-md-4"><b>Valor total:</b> <span x-text="Api.fmt.money(r.total_value)"></span></div>
            </div>
            <table class="table mt-3 mb-0">
              <thead><tr><th>Brinde</th><th>Solic.</th><th>Recebido</th><th>Retirado</th><th>Valor un.</th></tr></thead>
              <tbody>
                <template x-for="it in r.items" :key="it.id">
                  <tr>
                    <td x-text="it.item.name"></td>
                    <td x-text="it.qty_requested"></td>
                    <td x-text="it.qty_received"></td>
                    <td x-text="it.qty_delivered"></td>
                    <td x-text="Api.fmt.money(it.unit_value)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div class="card card-body">
            <h3 class="h6">Histórico</h3>
            <template x-for="h in (r.history || [])" :key="h.created_at + h.to">
              <div class="small py-1 border-bottom">
                <span class="fw-semibold" x-text="h.to_label"></span>
                <span class="text-muted" x-text="' · ' + (h.user?.name || '') + ' · ' + Api.fmt.datetime(h.created_at)"></span>
                <div x-text="h.comment"></div>
              </div>
            </template>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card card-body text-center">
            <div class="fw-semibold mb-2">QR Code da solicitação</div>
            <template x-if="r.qr_png">
              <div>
                <img :src="r.qr_png" alt="QR Code da solicitação TRADE" width="220" height="220"
                     onerror="this.hidden=true;this.nextElementSibling.hidden=false"
                     class="bg-white p-2 rounded mx-auto d-block" style="image-rendering:pixelated">
                <div hidden class="alert alert-warning small">QR indisponível. Abra a consulta pelo link:
                  <a :href="'<?= e(url('/consulta-trade/')) ?>' + encodeURIComponent(r.public_code || r.code)"
                     x-text="r.public_code || r.code"></a>
                </div>
              </div>
            </template>
            <template x-if="!r.qr_png">
              <div class="alert alert-warning small">QR indisponível. Abra a consulta pelo link:
                <a :href="'<?= e(url('/consulta-trade/')) ?>' + encodeURIComponent(r.public_code || r.code)"
                   x-text="r.public_code || r.code"></a>
              </div>
            </template>
            <div class="mt-2 font-monospace" x-text="r.public_code || r.code"></div>
            <p class="small text-muted mb-0">No CD ou no evento, leia este código para identificar indústria, produto e saldo.</p>
            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
              <button type="button" class="btn btn-outline-primary" @click="printQr()" :disabled="!r.qr_png">Imprimir QR Code</button>
              <button type="button" class="btn btn-outline-primary" @click="downloadQr()" :disabled="!r.qr_png">Baixar QR Code</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function tradeShow(id) {
  return {
    r: null, ticket: '', saving: false, loading: false, loadError: '',
    downloadQr() {
      if (!this.r?.qr_png) { UI.toast('QR Code indisponível para baixar.', 'err'); return; }
      const code = String(this.r.public_code || this.r.code || 'solicitacao').replace(/[^A-Za-z0-9_-]/g, '_');
      const link = document.createElement('a');
      link.href = this.r.qr_png;
      link.download = `qrcode-${code}.png`;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      link.remove();
    },
    printQr() {
      if (!this.r?.qr_png) { UI.toast('QR Code indisponível para impressão.', 'err'); return; }
      const printWindow = window.open('', '_blank');
      if (!printWindow) { UI.toast('Permita pop-ups para imprimir o QR Code.', 'err'); return; }
      const image = printWindow.document.createElement('img');
      image.addEventListener('load', () => { printWindow.focus(); printWindow.print(); printWindow.close(); }, { once: true });
      image.addEventListener('error', () => { printWindow.close(); UI.toast('Não foi possível carregar o QR Code para impressão.', 'err'); }, { once: true });
      image.src = this.r.qr_png;
      image.alt = 'QR Code ' + (this.r.public_code || this.r.code);
      image.style.width = '320px';
      const title = printWindow.document.createElement('h2');
      title.textContent = this.r.public_code || this.r.code;
      printWindow.document.body.append(title, image);
    },
    async load() {
      this.loading = true;
      this.loadError = '';
      try {
        const { data } = await Api.get('/api/requests/' + id);
        this.r = data;
        this.loadError = '';
        this.ticket = this.r.purchase_ticket_no || '';
      } catch (e) {
        if (this.r) {
          // A última resposta válida continua utilizável. Do not turn a
          // background refresh hiccup into a full-page blocking error.
          this.loadError = '';
          UI.toast('Não foi possível atualizar agora. Os dados carregados continuam disponíveis.', 'err');
        } else {
          this.loadError = e.message || 'Não foi possível carregar a solicitação TRADE.';
          UI.toast('Solicitação TRADE não encontrada ou sem permissão para visualização.', 'err');
        }
      } finally {
        this.loading = false;
      }
    },
    async approve() {
      if (!this.ticket.trim()) { UI.toast('Informe o número do chamado para aprovar.', 'err'); return; }
      this.saving = true;
      try {
        await Api.post('/api/trade/requests/' + id + '/approve', { purchase_ticket_no: this.ticket.trim() });
        await this.load();
        UI.toast('Solicitação aprovada e encaminhada para recebimento no CD.', 'ok');
      } catch (e) {
        UI.toast(e.message || 'Não foi possível aprovar a solicitação.', 'err');
      } finally { this.saving = false; }
    },
    async reject() {
      const reason = prompt('Motivo da reprovação:');
      if (reason === null || !reason.trim()) return;
      this.saving = true;
      try { await Api.post('/api/trade/requests/' + id + '/reject', { reason: reason.trim() }); this.load(); }
      catch (e) { UI.toast(e.message, 'err'); }
      this.saving = false;
    },
    async delivered() {
      try { await Api.post('/api/trade/requests/' + id + '/delivered'); this.load(); }
      catch (e) { UI.toast(e.message, 'err'); }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
