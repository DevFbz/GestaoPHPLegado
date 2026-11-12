<?php
$id = (int) ($app['page']['params']['id'] ?? 0);
ob_start(); ?>
<div x-data="protShow(<?= $id ?>)" x-init="load()">
  <header class="page-header">
    <div><h2>Detalhes da entrega</h2><p>Confira o protocolo, os itens entregues e os documentos associados.</p></div>
  </header>
  <template x-if="d">
    <div class="card card-body">
      <h2 class="h4" x-text="d.code"></h2>
      <div x-text="d.type_label"></div>
      <div x-show="d.industry">Indústria: <b x-text="d.industry && d.industry.name"></b></div>
      <div x-show="d.industry && d.industry.contact_email">E-mail da indústria: <span x-text="d.industry && d.industry.contact_email"></span></div>
      <div>Recebido por: <span x-text="d.received_by_name"></span></div>
      <div x-show="d.received_by_email">E-mail de quem retirou: <span x-text="d.received_by_email"></span></div>
      <div>Entregue por: <span x-text="d.delivered_by.name"></span></div>
      <div class="small text-muted" x-text="Api.fmt.datetime(d.created_at)"></div>
      <div class="table-responsive mt-3">
        <table class="table table-sm">
          <thead><tr><th>Brinde</th><th class="text-end">Quantidade retirada</th><th class="text-end">Saldo que ainda tem</th></tr></thead>
          <tbody>
            <template x-for="it in d.items" :key="it.item.id">
              <tr>
                <td x-text="it.item.name"></td>
                <td class="text-end" x-text="it.qty_withdrawn ?? it.qty"></td>
                <td class="text-end" x-text="it.remaining ?? it.balance_after ?? 0"></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
        <span class="badge" :class="d.email_status?.key === 'sent' ? 'text-bg-success' : (d.email_status?.key === 'failed' ? 'text-bg-danger' : 'text-bg-secondary')" x-text="d.email_status?.label || 'Não enviado'"></span>
        <span class="small text-muted" x-show="d.email_status?.key === 'sent'">Envio aceito pelo SMTP Brevo.</span>
      </div>
      <img x-show="d.signature_url" :src="d.signature_url" alt="Assinatura" style="max-height:80px;background:#fff">
      <div class="mt-2 d-flex flex-wrap gap-2">
        <a class="btn btn-primary" :href="mailtoHref()" target="_blank">Enviar estas informações por e-mail</a>
        <a class="btn btn-outline-secondary" :href="d.pdf_url" target="_blank">Baixar PDF</a>
        <button class="btn btn-outline-secondary" @click="resend" :disabled="sending" x-text="sending ? 'Enviando…' : 'Enviar email automático'"></button>
      </div>
    </div>
  </template>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function protShow(id) {
  return {
    d: null, sending: false,
    async load() { this.d = (await Api.get('/api/deliveries/' + id)).data; },
    mailtoHref() {
      const d = this.d || {};
      const ind = d.industry || {};
      const to = d.received_by_email || ind.contact_email || '';
      const lines = (d.items || []).map(function (it) {
        return '- ' + (it.item && it.item.name ? it.item.name : 'Brinde') + ': retirado ' + (it.qty_withdrawn ?? it.qty) + ', saldo ' + (it.remaining ?? it.balance_after ?? 0);
      });
      const body = [
        'Protocolo ' + (d.code || ''),
        d.type_label || '',
        'Indústria: ' + (ind.name || '—'),
        ind.contact_email ? ('E-mail da indústria: ' + ind.contact_email) : '',
        'Recebido por: ' + (d.received_by_name || ''),
        d.received_by_email ? ('E-mail de quem retirou: ' + d.received_by_email) : '',
        'Entregue por: ' + ((d.delivered_by && d.delivered_by.name) || ''),
        '',
        lines.join('\n'),
        '',
        'PDF: ' + (d.pdf_url || '')
      ].filter(function (line, i, arr) { return line !== '' || (arr[i - 1] !== '' && i !== 0); }).join('\n');
      const subject = 'Protocolo ' + (d.code || '') + (ind.name ? (' — ' + ind.name) : '');
      return 'mailto:' + encodeURIComponent(to) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    },
    async resend() {
      this.sending = true;
      try {
        const response = await Api.post('/api/deliveries/' + id + '/resend');
        await this.load();
        const status = response.data && response.data.email_status ? response.data.email_status : this.d.email_status;
        UI.toast(status && status.key === 'sent' ? 'E-mail enviado e aceito pelo Brevo.' : 'O e-mail não foi confirmado pelo Brevo: ' + ((status && status.label) || 'Não enviado') + '.', status && status.key === 'sent' ? 'ok' : 'err');
      } catch (e) { UI.toast(e.message, 'err'); }
      finally { this.sending = false; }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
