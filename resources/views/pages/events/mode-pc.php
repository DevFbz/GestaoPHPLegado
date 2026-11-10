<?php
$id = (int) ($app['page']['params']['id'] ?? 0);
ob_start(); ?>
<style>
  [x-cloak] { display: none !important; }
  .event-pc { min-height: 100vh; background: #f4f7fb; color: #172033; padding: 24px; }
  .event-pc-shell { max-width: 1540px; margin: 0 auto; }
  .event-pc-topbar { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:20px; }
  .event-pc-brand { display:flex; align-items:center; gap:14px; }
  .event-pc-mark { width:46px; height:46px; border-radius:15px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg,#2563eb,#7c3aed); box-shadow:0 12px 24px rgba(37,99,235,.22); font-size:22px; }
  .event-pc-title { margin:0; font-size:clamp(1.15rem,2vw,1.65rem); font-weight:800; letter-spacing:-.03em; }
  .event-pc-subtitle { color:#667085; font-size:.88rem; margin:3px 0 0; }
  .event-pc-grid { display:grid; grid-template-columns:280px minmax(0,1fr) 350px; gap:18px; align-items:start; }
  .event-pc-card { background:rgba(255,255,255,.94); border:1px solid #e5eaf2; border-radius:20px; box-shadow:0 10px 30px rgba(21,34,56,.06); }
  .event-pc-card-head { padding:18px 20px 12px; border-bottom:1px solid #edf0f5; }
  .event-pc-card-head h2 { font-size:.98rem; font-weight:800; margin:0; }
  .event-pc-card-body { padding:18px 20px; }
  .event-pc-search { border-radius:12px; border:1px solid #dfe5ef; padding:11px 13px; background:#f8fafc; }
  .event-pc-industries { padding:10px; max-height:calc(100vh - 250px); overflow:auto; }
  .event-pc-industry { width:100%; border:0; background:transparent; border-radius:13px; display:flex; justify-content:space-between; align-items:center; padding:12px; text-align:left; color:#344054; transition:.18s ease; }
  .event-pc-industry:hover { background:#f1f5ff; color:#1d4ed8; }
  .event-pc-industry.active { background:#e8efff; color:#1d4ed8; box-shadow:inset 3px 0 #2563eb; }
  .event-pc-industry-name { font-weight:700; font-size:.9rem; }
  .event-pc-industry-saldo { font-size:.74rem; color:#667085; white-space:nowrap; }
  .event-pc-center { min-width:0; }
  .event-pc-empty { min-height:390px; display:grid; place-items:center; text-align:center; color:#667085; padding:50px; }
  .event-pc-empty-icon { width:64px; height:64px; border-radius:20px; display:grid; place-items:center; margin:0 auto 14px; background:#eef3ff; color:#2563eb; font-size:28px; }
  .event-pc-selected { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:16px; }
  .event-pc-selected h2 { margin:0; font-size:1.25rem; font-weight:800; }
  .event-pc-selected p { margin:4px 0 0; color:#667085; font-size:.85rem; }
  .event-pc-summary { display:flex; gap:8px; flex-wrap:wrap; }
  .event-pc-pill { border-radius:999px; background:#f1f5f9; color:#475467; padding:7px 11px; font-size:.78rem; font-weight:700; }
  .event-pc-items { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:12px; }
  .event-pc-item { border:1px solid #e5eaf2; border-radius:16px; padding:15px; background:#fff; }
  .event-pc-item-name { min-height:42px; font-weight:750; line-height:1.25; }
  .event-pc-item-code { color:#98a2b3; font-size:.75rem; margin-top:3px; }
  .event-pc-item-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:15px; }
  .event-pc-stock { font-size:.8rem; color:#667085; }
  .event-pc-stepper { display:flex; align-items:center; gap:9px; }
  .event-pc-stepper button { width:32px; height:32px; border:1px solid #d8dee9; border-radius:10px; background:#f8fafc; color:#1d4ed8; font-size:1.15rem; line-height:1; }
  .event-pc-qty { min-width:24px; text-align:center; font-weight:800; }
  .event-pc-checkout { position:sticky; top:20px; }
  .event-pc-checkout h2 { margin:0; font-size:1rem; font-weight:800; }
  .event-pc-checkout-label { display:block; color:#475467; font-size:.78rem; font-weight:700; margin:14px 0 6px; }
  .event-pc-checkout input { border-radius:11px; border-color:#dfe5ef; padding:11px 12px; }
  .event-pc-total { display:flex; justify-content:space-between; align-items:center; padding:13px 14px; border-radius:13px; background:#f4f7ff; color:#1d4ed8; margin-top:16px; }
  .event-pc-total strong { font-size:1.2rem; }
  .event-pc-signature { width:100%; height:145px; display:block; border:1px dashed #b8c4d8; border-radius:13px; background:#fff; touch-action:none; }
  .event-pc-confirm { width:100%; border:0; border-radius:12px; margin-top:14px; padding:13px 16px; color:#fff; background:linear-gradient(135deg,#2563eb,#4f46e5); font-weight:800; box-shadow:0 10px 18px rgba(37,99,235,.2); }
  .event-pc-confirm:disabled { opacity:.55; box-shadow:none; }
  .event-pc-clear { border:0; background:transparent; color:#667085; font-size:.78rem; padding:7px 0; }
  .event-pc-success { max-width:620px; margin:70px auto; text-align:center; padding:42px; }
  .event-pc-success-icon { width:72px; height:72px; border-radius:50%; display:grid; place-items:center; margin:0 auto 18px; background:#dcfae6; color:#039855; font-size:36px; }
  .event-pc-success h2 { font-size:1.45rem; font-weight:800; }
  .event-pc-success-actions { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-top:24px; }
  @media (max-width:1180px) { .event-pc-grid { grid-template-columns:235px minmax(0,1fr); } .event-pc-checkout { grid-column:1/-1; position:static; } .event-pc-checkout .event-pc-card-body { display:grid; grid-template-columns:repeat(2,1fr); gap:0 18px; } .event-pc-checkout h2,.event-pc-checkout .event-pc-total,.event-pc-checkout .event-pc-confirm { grid-column:1/-1; } }
  @media (max-width:720px) { .event-pc { padding:12px; } .event-pc-grid { grid-template-columns:1fr; } .event-pc-industries { max-height:230px; } .event-pc-checkout .event-pc-card-body { display:block; } .event-pc-topbar { align-items:flex-start; } .event-pc-selected { display:block; } }
</style>
<div class="event-pc" x-data="modoEventoPc(<?= $id ?>)" x-init="init()" x-cloak>
  <div class="event-pc-shell">
    <header class="event-pc-topbar">
      <div class="event-pc-brand">
        <div class="event-pc-mark"><i class="bi bi-display" aria-hidden="true"></i></div>
        <div><h1 class="event-pc-title" x-text="eventName || 'Modo Evento — PC'"></h1><p class="event-pc-subtitle">Operação de retirada · painel para computador</p></div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= e(url('/eventos/' . $id)) ?>"><i class="bi bi-arrow-left me-1"></i>Voltar ao evento</a>
    </header>

    <template x-if="done">
      <section class="event-pc-card event-pc-success">
        <div class="event-pc-success-icon">✓</div>
        <h2>Retirada confirmada</h2>
        <p class="text-muted mb-1">Protocolo <strong x-text="done.code"></strong></p>
        <p class="text-muted" x-text="mailHint(done)"></p>
        <div class="event-pc-success-actions">
          <a class="btn btn-outline-primary" x-show="done.pdf_url" :href="done.pdf_url" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Baixar comprovante PDF</a>
          <button class="btn btn-primary" @click="next()"><i class="bi bi-arrow-right me-1"></i>Próxima retirada</button>
        </div>
      </section>
    </template>

    <div x-show="!done" class="event-pc-grid">
      <aside class="event-pc-card">
        <div class="event-pc-card-head"><h2>1. Escolha a indústria</h2></div>
        <div class="event-pc-card-body"><input class="form-control event-pc-search" placeholder="Buscar indústria…" x-model="q" @input="filterInd()"></div>
        <div class="event-pc-industries">
          <template x-for="i in filtered" :key="i.id">
            <button type="button" class="event-pc-industry" :class="industry && industry.id===i.id ? 'active' : ''" @click="pickInd(i)">
              <span class="event-pc-industry-name" x-text="i.name"></span><span class="event-pc-industry-saldo" x-text="i.saldo + ' un.'"></span>
            </button>
          </template>
          <p class="small text-muted p-2 mb-0" x-show="!filtered.length">Nenhuma indústria encontrada.</p>
        </div>
      </aside>

      <main class="event-pc-card event-pc-center">
        <template x-if="!industry">
          <div class="event-pc-empty"><div><div class="event-pc-empty-icon"><i class="bi bi-box-seam"></i></div><h2 class="h5 fw-bold">Selecione uma indústria</h2><p class="mb-0">Os brindes e saldos disponíveis aparecerão aqui.</p></div></div>
        </template>
        <template x-if="industry">
          <div class="event-pc-card-body">
            <div class="event-pc-selected"><div><h2 x-text="industry.name"></h2><p>2. Informe os brindes e quantidades da retirada</p></div><div class="event-pc-summary"><span class="event-pc-pill"><i class="bi bi-box me-1"></i><span x-text="lines.length"></span> itens</span><span class="event-pc-pill"><i class="bi bi-clipboard-check me-1"></i><span x-text="totalQty()"></span> unidades</span></div></div>
            <div class="event-pc-items">
              <template x-for="line in lines" :key="line.item.id">
                <article class="event-pc-item"><div class="event-pc-item-name" x-text="line.item.name"></div><div class="event-pc-item-code" x-text="line.item.code"></div><div class="event-pc-item-foot"><span class="event-pc-stock">Saldo: <strong x-text="line.saldo"></strong></span><div class="event-pc-stepper"><button type="button" @click="line.qty=Math.max(0,line.qty-1)" aria-label="Diminuir">−</button><span class="event-pc-qty" x-text="line.qty"></span><button type="button" @click="line.qty=Math.min(line.saldo,line.qty+1)" aria-label="Aumentar">+</button></div></div></article>
              </template>
            </div>
          </div>
        </template>
      </main>

      <aside class="event-pc-card event-pc-checkout">
        <div class="event-pc-card-head"><h2>3. Confirmar retirada</h2></div>
        <div class="event-pc-card-body">
          <label class="event-pc-checkout-label" for="pc-recv">Nome de quem retira *</label><input id="pc-recv" class="form-control" placeholder="Nome completo" x-model="recv">
          <label class="event-pc-checkout-label" for="pc-email">E-mail para o comprovante *</label><input id="pc-email" class="form-control" type="email" placeholder="nome@empresa.com" x-model="email">
          <div class="event-pc-total"><span>Total da retirada</span><strong x-text="totalQty()"></strong></div>
          <label class="event-pc-checkout-label">Assinatura digital *</label><canvas id="sigEvPc" class="event-pc-signature"></canvas><button type="button" class="event-pc-clear" @click="clear()"><i class="bi bi-eraser me-1"></i>Limpar assinatura</button>
          <button class="event-pc-confirm" :disabled="saving || !industry" @click="confirm()"><i class="bi bi-check2-circle me-1"></i><span x-text="saving ? 'Registrando…' : 'Confirmar retirada'"></span></button>
        </div>
      </aside>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script src="<?= e(asset('vendor/signature_pad/signature_pad.umd.min.js')) ?>"></script>
<script>
function modoEventoPc(id) {
  return {
    eventName:'', industries:[], filtered:[], q:'', industry:null, lines:[], recv:'', email:'', pad:null, saving:false, done:null, key:Api.newKey(),
    async init(){ const {data}=await Api.get('/api/events/'+id); this.eventName=data.name; this.industries=data.industries||[]; this.filtered=this.industries; },
    filterInd(){ const q=this.q.toLowerCase(); this.filtered=this.industries.filter(i=>i.name.toLowerCase().includes(q)); },
    async pickInd(i){ this.industry=i; this.lines=((await Api.get('/api/events/'+id+'/balance',{industry_id:i.id})).data||[]).map(l=>({...l,qty:0})); this.$nextTick(()=>{const c=document.getElementById('sigEvPc'); if(c)this.pad=UI.signaturePad(c);}); },
    totalQty(){ return this.lines.reduce((sum,l)=>sum+Number(l.qty||0),0); },
    clear(){ if(this.pad)this.pad.clear(); },
    mailHint(done){ const a=(done.mail&&done.mail.addresses)||[]; if(done.mail&&done.mail.smtp&&a.length)return 'E-mail enviado para '+a.join(', ')+'.'; if(a.length)return 'Comprovante registrado para '+a.join(', ')+'.'; return 'Protocolo gerado. Informe um e-mail válido para receber o comprovante.'; },
    async next(){this.done=null;this.industry=null;this.lines=[];this.recv='';this.email='';this.q='';this.filtered=this.industries;this.key=Api.newKey();await this.init();},
    async confirm(){ const items=this.lines.filter(l=>l.qty>0).map(l=>({item_id:l.item.id,qty:l.qty})); if(!items.length){UI.toast('Informe as quantidades.','err');return;} if(!this.recv){UI.toast('Informe o nome.','err');return;} if(!this.email||!this.email.includes('@')){UI.toast('Informe o e-mail para enviar o comprovante.','err');return;} if(!this.pad||this.pad.isEmpty()){UI.toast('Assine no campo.','err');return;} this.saving=true;try{const {data}=await Api.postIdem('/api/events/'+id+'/withdrawals',{industry_id:this.industry.id,received_by_name:this.recv,received_by_email:this.email,items,signature:this.pad.toDataURL('image/png')},{idempotencyKey:this.key});this.done=data;}catch(e){UI.toast(e.message,'err');}this.saving=false;}
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/kiosk.php';
