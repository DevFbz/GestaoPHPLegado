<?php
$id = (int) ($app['page']['params']['id'] ?? 0);
ob_start(); ?>
<style>
  [x-cloak]{display:none!important}
  .event-tablet{min-height:100vh;background:#f4f7fb;color:#172033;padding:16px 14px 28px}
  .event-tablet-shell{width:min(100%,760px);margin:0 auto}
  .event-tablet-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
  .event-tablet-brand{display:flex;align-items:center;gap:11px;min-width:0}
  .event-tablet-mark{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;border-radius:14px;background:linear-gradient(135deg,var(--brand-primary),var(--brand-primary-dark));color:#fff;box-shadow:0 8px 18px color-mix(in srgb,var(--brand-primary) 22%,transparent);font-size:19px}
  .event-tablet-title{margin:0;font-size:clamp(1rem,4vw,1.3rem);font-weight:800;letter-spacing:-.025em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .event-tablet-subtitle{margin:2px 0 0;color:#667085;font-size:.73rem}
  .event-tablet-exit{border-color:#d5dce8;color:#475467;background:#fff;border-radius:10px;white-space:nowrap}
  .event-tablet-card{background:#fff;border:1px solid #e5eaf2;border-radius:18px;box-shadow:0 8px 24px rgba(21,34,56,.06)}
  .event-tablet-step{padding:15px;margin-bottom:12px}
  .event-tablet-step-head{display:flex;align-items:center;gap:10px;margin-bottom:11px}
  .event-tablet-step-number{width:27px;height:27px;display:grid;place-items:center;border-radius:9px;background:var(--brand-primary-light);color:var(--brand-primary-dark);font-size:.8rem;font-weight:800}
  .event-tablet-step-title{margin:0;font-size:.94rem;font-weight:800}
  .event-tablet-search{border-radius:11px;border:1px solid #dfe5ef;background:#f8fafc;padding:10px 12px}
  .event-tablet-industries{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px;max-height:190px;overflow:auto}
  .event-tablet-industry{display:flex;align-items:center;justify-content:space-between;gap:7px;width:100%;min-width:0;border:1px solid #e4e9f1;border-radius:12px;background:#fff;color:#344054;padding:10px;text-align:left;transition:.16s ease}
  .event-tablet-industry:hover{border-color:var(--brand-primary);background:var(--brand-primary-light)}
  .event-tablet-industry.active{border-color:var(--brand-primary);background:var(--brand-primary-light);box-shadow:0 0 0 2px color-mix(in srgb,var(--brand-primary) 13%,transparent)}
  .event-tablet-industry-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;font-weight:750}
  .saldo-badge{display:inline-flex;align-items:center;gap:4px;flex:0 0 auto;border-radius:999px;padding:4px 7px;font-size:.67rem;font-weight:800;white-space:nowrap}
  .saldo-critical{background:#fee4e2;color:#b42318}
  .saldo-warning{background:#fef0c7;color:#b54708}
  .saldo-ok{background:#dcfae6;color:#027a48}
  .saldo-legend{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;color:#667085;font-size:.68rem}
  .saldo-legend-item{display:inline-flex;align-items:center;gap:4px}
  .saldo-dot{width:8px;height:8px;border-radius:50%}
  .event-tablet-empty{padding:22px 10px;text-align:center;color:#667085;font-size:.82rem}
  .event-tablet-brands{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:11px}
  .event-tablet-brands h2{margin:0;font-size:.98rem;font-weight:800}
  .event-tablet-brands p{margin:3px 0 0;color:#667085;font-size:.72rem}
  .event-tablet-total{border-radius:10px;background:var(--brand-primary-light);color:var(--brand-primary-dark);padding:7px 9px;text-align:center;font-size:.68rem;font-weight:750;white-space:nowrap}
  .event-tablet-total strong{display:block;font-size:1.05rem;line-height:1.05}
  .event-tablet-items{display:grid;gap:8px}
  .event-tablet-item{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;border:1px solid #e5eaf2;border-radius:13px;padding:11px;background:#fff}
  .event-tablet-item-name{font-size:.82rem;font-weight:750;line-height:1.25}
  .event-tablet-item-meta{display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap;color:#667085;font-size:.68rem}
  .event-tablet-stepper{display:flex;align-items:center;gap:8px}
  .event-tablet-stepper button{width:35px;height:35px;border:1px solid #d5dce8;border-radius:10px;background:#f8fafc;color:var(--brand-primary-dark);font-size:1.2rem;line-height:1}
  .event-tablet-stepper button:active{transform:scale(.96)}
  .event-tablet-qty{min-width:22px;text-align:center;font-size:1rem;font-weight:850}
  .event-tablet-form{display:grid;gap:9px}
  .event-tablet-label{color:#475467;font-size:.72rem;font-weight:750}
  .event-tablet-form .form-control{border-radius:11px;border-color:#dfe5ef;padding:10px 12px}
  .event-tablet-signature{width:100%;height:130px;display:block;border:1px dashed #b8c4d8;border-radius:12px;background:#fff;touch-action:none}
  .event-tablet-actions{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .event-tablet-clear{border:0;background:transparent;color:#667085;font-size:.72rem;padding:5px 0}
  .event-tablet-confirm{width:100%;border:0;border-radius:11px;padding:12px 15px;background:linear-gradient(135deg,var(--brand-primary),var(--brand-primary-dark));color:#fff;font-weight:800;box-shadow:0 8px 16px color-mix(in srgb,var(--brand-primary) 20%,transparent)}
  .event-tablet-confirm:disabled{opacity:.55;box-shadow:none}
  .event-tablet-success{padding:30px 20px;text-align:center}
  .event-tablet-success-icon{width:64px;height:64px;margin:0 auto 12px;display:grid;place-items:center;border-radius:50%;background:#dcfae6;color:#027a48;font-size:32px}
  .event-tablet-success h2{margin:0 0 8px;font-size:1.25rem;font-weight:800}
  @media (min-width:620px){.event-tablet{padding:24px}.event-tablet-items{grid-template-columns:repeat(2,minmax(0,1fr))}.event-tablet-step{padding:18px}.event-tablet-form{grid-template-columns:1fr 1fr}.event-tablet-signature,.event-tablet-actions,.event-tablet-confirm{grid-column:1/-1}}
  @media (max-width:390px){.event-tablet-industries{grid-template-columns:1fr}.event-tablet-item{grid-template-columns:1fr}.event-tablet-stepper{justify-content:flex-end}}
</style>
<div class="event-tablet" x-data="modoEvento(<?= $id ?>)" x-init="init()" x-cloak>
  <div class="event-tablet-shell">
    <header class="event-tablet-top">
      <div class="event-tablet-brand">
        <div class="event-tablet-mark"><i class="bi bi-gift" aria-hidden="true"></i></div>
        <div><h1 class="event-tablet-title" x-text="eventName || 'Modo Evento'"></h1><p class="event-tablet-subtitle">Retirada de brindes · tablet</p></div>
      </div>
      <a class="btn btn-sm event-tablet-exit" href="<?= e(url('/eventos/' . $id)) ?>"><i class="bi bi-arrow-left me-1"></i>Sair</a>
    </header>

    <template x-if="done"><section class="event-tablet-card event-tablet-success">
      <div class="event-tablet-success-icon">✓</div><h2>Retirada confirmada</h2>
      <p class="text-muted mb-1">Protocolo <strong x-text="done.code"></strong></p><p class="small text-muted" x-text="mailHint(done)"></p>
      <p class="small" x-show="done.pdf_url"><a :href="done.pdf_url" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Baixar comprovante PDF</a></p>
      <button class="btn btn-primary w-100 mt-3" @click="next()"><i class="bi bi-arrow-right me-1"></i>Próxima retirada</button>
    </section></template>

    <div x-show="!done">
      <section class="event-tablet-card event-tablet-step">
        <div class="event-tablet-step-head"><span class="event-tablet-step-number">1</span><h2 class="event-tablet-step-title">Escolha a indústria</h2></div>
        <input class="form-control event-tablet-search" placeholder="Buscar indústria…" x-model="q" @input="filterInd()">
        <div class="event-tablet-industries"><template x-for="i in filtered" :key="i.id"><button type="button" class="event-tablet-industry" :class="industry && industry.id===i.id ? 'active' : ''" @click="pickInd(i)"><span class="event-tablet-industry-name" x-text="i.name"></span><span class="saldo-badge" :class="'saldo-'+stockLevel(i.saldo).class"><i class="bi" :class="stockLevel(i.saldo).icon"></i><span x-text="i.saldo"></span></span></button></template></div>
        <div class="saldo-legend"><span>Legenda de saldo:</span><span class="saldo-legend-item"><i class="saldo-dot saldo-critical"></i>Até 5</span><span class="saldo-legend-item"><i class="saldo-dot saldo-warning"></i>6 a 10</span><span class="saldo-legend-item"><i class="saldo-dot saldo-ok"></i>Acima de 10</span></div>
        <div class="event-tablet-empty" x-show="!filtered.length">Nenhuma indústria encontrada.</div>
      </section>

      <template x-if="industry"><section class="event-tablet-card event-tablet-step">
        <div class="event-tablet-brands"><div><h2>2. Escolha os brindes</h2><p x-text="industry.name"></p></div><div class="event-tablet-total"><strong x-text="totalQty()"></strong>unidades</div></div>
        <div class="event-tablet-items"><template x-for="line in lines" :key="line.item.id"><article class="event-tablet-item"><div><div class="event-tablet-item-name" x-text="line.item.name"></div><div class="event-tablet-item-meta"><span>Disponível: <strong x-text="line.saldo"></strong></span><span class="saldo-badge" :class="'saldo-'+stockLevel(line.saldo).class"><i class="bi" :class="stockLevel(line.saldo).icon"></i><span x-text="stockLevel(line.saldo).label"></span></span></div></div><div class="event-tablet-stepper"><button type="button" @click="line.qty=Math.max(0,line.qty-1)" aria-label="Diminuir quantidade">−</button><span class="event-tablet-qty" x-text="line.qty"></span><button type="button" @click="line.qty=Math.min(line.saldo,line.qty+1)" aria-label="Aumentar quantidade">+</button></div></article></template></div>
      </section></template>

      <template x-if="industry"><section class="event-tablet-card event-tablet-step"><div class="event-tablet-step-head"><span class="event-tablet-step-number">3</span><h2 class="event-tablet-step-title">Confirmar retirada</h2></div><div class="event-tablet-form">
        <div><label class="event-tablet-label" for="tablet-recv">Nome de quem retira *</label><input id="tablet-recv" class="form-control" placeholder="Nome completo" x-model="recv"></div>
        <div><label class="event-tablet-label" for="tablet-email">E-mail do comprovante *</label><input id="tablet-email" class="form-control" type="email" placeholder="nome@empresa.com" x-model="email"></div>
        <div class="event-tablet-actions"><label class="event-tablet-label mb-0">Assinatura digital *</label><button type="button" class="event-tablet-clear" @click="clear()"><i class="bi bi-eraser me-1"></i>Limpar</button></div>
        <canvas id="sigEv" class="event-tablet-signature"></canvas><button class="event-tablet-confirm" :disabled="saving" @click="confirm()"><i class="bi bi-check2-circle me-1"></i><span x-text="saving ? 'Registrando…' : 'Confirmar retirada'"></span></button>
      </div></section></template>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script src="<?= e(asset('vendor/signature_pad/signature_pad.umd.min.js')) ?>"></script>
<script>
function modoEvento(id) {
  return {
    eventName:'', industries:[], filtered:[], q:'', industry:null, lines:[], recv:'', email:'', pad:null, saving:false, done:null, key:Api.newKey(), _pageShowBound:false,
    async init(){ this.resetSignature(); if(!this._pageShowBound){this._pageShowBound=true;window.addEventListener('pageshow',()=>this.resetSignature());} const {data}=await Api.get('/api/events/'+id); this.eventName=data.name; this.industries=data.industries||[]; this.filtered=this.industries; },
    filterInd(){const q=this.q.toLowerCase();this.filtered=this.industries.filter(i=>i.name.toLowerCase().includes(q));},
    stockLevel(value){const n=Number(value)||0;if(n<=5)return {class:'critical',label:'Crítico',icon:'bi-exclamation-octagon-fill'};if(n<=10)return {class:'warning',label:'Atenção',icon:'bi-exclamation-triangle-fill'};return {class:'ok',label:'Disponível',icon:'bi-check-circle-fill'};},
    async pickInd(i){this.resetSignature();this.industry=i;this.lines=((await Api.get('/api/events/'+id+'/balance',{industry_id:i.id})).data||[]).map(l=>({...l,qty:0}));this.$nextTick(()=>{const c=document.getElementById('sigEv');if(c)this.pad=UI.signaturePad(c);});},
    resetSignature(){if(this.pad)this.pad.clear();this.pad=null;const c=document.getElementById('sigEv');if(c){const ctx=c.getContext('2d');if(ctx)ctx.clearRect(0,0,c.width,c.height);}},
    totalQty(){return this.lines.reduce((sum,l)=>sum+Number(l.qty||0),0);},clear(){this.resetSignature();},
    mailHint(done){const a=(done.mail&&done.mail.addresses)||[];if(done.mail&&done.mail.smtp&&a.length)return 'E-mail enviado para '+a.join(', ')+'.';if(a.length)return 'Comprovante registrado para '+a.join(', ')+'.';return 'Protocolo gerado. Informe um e-mail válido para receber o comprovante.';},
    async next(){this.resetSignature();this.done=null;this.industry=null;this.lines=[];this.recv='';this.email='';this.key=Api.newKey();this.q='';this.filtered=[];await this.init();},
    async confirm(){const items=this.lines.filter(l=>l.qty>0).map(l=>({item_id:l.item.id,qty:l.qty}));if(!items.length){UI.toast('Informe as quantidades.','err');return;}if(!this.recv){UI.toast('Informe o nome.','err');return;}if(!this.email||!this.email.includes('@')){UI.toast('Informe o e-mail para enviar o comprovante.','err');return;}if(!this.pad||this.pad.isEmpty()){UI.toast('Assine no campo.','err');return;}this.saving=true;try{const {data}=await Api.postIdem('/api/events/'+id+'/withdrawals',{industry_id:this.industry.id,received_by_name:this.recv,received_by_email:this.email,items,signature:this.pad.toDataURL('image/png')},{idempotencyKey:this.key});this.done=data;}catch(e){UI.toast(e.message,'err');}this.saving=false;}
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/kiosk.php';
