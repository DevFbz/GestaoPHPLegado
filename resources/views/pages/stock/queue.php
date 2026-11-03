<?php ob_start(); ?>
<div class="card card-body" x-data="exitQueue()" x-init="load()">
  <p class="text-muted mb-3">O gestor registra a saída no painel dele. Aqui só aparece o que já foi liberado, com QR para escanear. Confirme para baixar o estoque.</p>

  <div class="input-group mb-3">
    <input class="form-control" x-model="code" placeholder="SAI-2026-0001" @keydown.enter.prevent="lookup">
    <button class="btn btn-primary" type="button" @click="lookup">Identificar</button>
  </div>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-outline-secondary btn-sm" type="button" @click="toggleCam" x-text="camOn ? 'Fechar câmera' : 'Abrir câmera / escanear QR'"></button>
    <label class="btn btn-outline-secondary btn-sm mb-0">
      Ler QR pela foto
      <input type="file" accept="image/*" class="d-none" @change="fromFile($event)">
    </label>
  </div>
  <p class="small text-danger" x-show="camErr" x-text="camErr"></p>
  <div class="position-relative mb-3 rounded overflow-hidden" style="background:#111;max-width:480px" x-show="camOn">
    <video id="qrVideo" class="w-100" muted playsinline autoplay style="max-height:280px;object-fit:cover"></video>
    <canvas id="qrCanvas" class="d-none"></canvas>
  </div>

  <p class="small text-danger" x-show="err" x-text="err"></p>
  <p class="text-muted" x-show="!err && !rows.length">Nenhuma saída autorizada no momento.</p>

  <template x-for="o in rows" :key="o.id">
    <div class="border rounded p-3 mb-3" :class="picked && picked.id === o.id ? 'border-primary' : ''">
      <div class="d-flex flex-wrap gap-3 align-items-start">
        <img :src="o.qr_png" alt="QR" width="140" height="140" class="bg-white p-2 rounded" style="image-rendering:pixelated">
        <div class="flex-grow-1">
          <div class="fw-semibold" x-text="o.code"></div>
          <div x-text="o.item.code + ' — ' + o.item.name"></div>
          <div>Qtd: <b x-text="o.qty"></b> · <span x-text="o.purpose"></span></div>
          <div class="text-muted small" x-show="o.recipient" x-text="'Destino: ' + o.recipient"></div>
          <div class="text-muted small" x-text="o.status_label + ' · autorizado por ' + (o.authorized_by?.name || '')"></div>
          <button class="btn btn-primary mt-2" type="button" :disabled="saving === o.id" @click="confirm(o)">
            Confirmar saída
          </button>
        </div>
      </div>
    </div>
  </template>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script src="<?= e(asset('vendor/jsqr/jsQR.js')) ?>"></script>
<script>
function exitQueue() {
  return {
    rows: [], code: '', err: '', picked: null, saving: 0,
    camOn: false, stream: null, camErr: '', timer: null,
    async load() {
      this.err = '';
      try {
        const { data } = await Api.get('/api/stock/exit-orders', { status: 'autorizada' });
        this.rows = data || [];
      } catch (e) {
        this.err = e.message;
      }
    },
    extract(raw) {
      const s = String(raw || '').trim();
      const m = s.match(/SAI-\d{4}-\d+/i);
      if (m) return m[0].toUpperCase();
      try {
        const u = new URL(s);
        const c = u.searchParams.get('code');
        if (c) return this.extract(c);
      } catch (e) {}
      return s.toUpperCase();
    },
    decodeQr(source, width, height) {
      if (typeof jsQR !== 'function' || !width || !height) return null;
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      const maxSide = 1800;
      const baseScale = Math.min(1, maxSide / Math.max(width, height));
      const scales = [baseScale, Math.min(2, baseScale * 1.5), Math.min(3, baseScale * 2)];
      for (const angle of [0, 90, 180, 270]) {
        for (const scale of [...new Set(scales)]) {
          const w = Math.max(1, Math.round(width * scale));
          const h = Math.max(1, Math.round(height * scale));
          canvas.width = angle % 180 ? h : w;
          canvas.height = angle % 180 ? w : h;
          ctx.save();
          ctx.translate(canvas.width / 2, canvas.height / 2);
          ctx.rotate(angle * Math.PI / 180);
          ctx.drawImage(source, -w / 2, -h / 2, w, h);
          ctx.restore();
          try {
            const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const result = jsQR(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'attemptBoth' });
            if (result && result.data) return result.data;
          } catch (e) {}
        }
      }
      return null;
    },
    async lookup() {
      const code = this.extract(this.code);
      if (!code) { UI.toast('Informe ou leia o QR.', 'err'); return; }
      this.code = code;
      try {
        const { data } = await Api.get('/api/stock/exit-orders/lookup', { code });
        this.picked = data;
        if (data.status !== 'autorizada') {
          UI.toast(data.status_label || 'Esta saída já foi tratada.', 'err');
          return;
        }
        if (!this.rows.some(r => r.id === data.id)) this.rows.unshift(data);
        UI.toast('Saída identificada: ' + data.code, 'ok');
      } catch (e) {
        UI.toast(e.message, 'err');
      }
    },
    async confirm(o) {
      this.saving = o.id;
      try {
        await Api.post('/api/stock/exit-orders/' + o.id + '/confirm', {}, { idempotencyKey: Api.newKey() });
        UI.toast('Saída confirmada. Estoque atualizado.', 'ok');
        this.rows = this.rows.filter(r => r.id !== o.id);
        if (this.picked && this.picked.id === o.id) this.picked = null;
      } catch (e) {
        UI.toast(e.message, 'err');
      }
      this.saving = 0;
    },
    async toggleCam() {
      if (this.camOn) { this.stopCam(); return; }
      this.camErr = '';
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        const v = document.getElementById('qrVideo');
        v.srcObject = this.stream;
        await v.play();
        this.camOn = true;
        this.tick();
      } catch (e) {
        this.camErr = 'Não foi possível abrir a câmera.';
      }
    },
    stopCam() {
      this.camOn = false;
      if (this.timer) cancelAnimationFrame(this.timer);
      if (this.stream) this.stream.getTracks().forEach(t => t.stop());
      this.stream = null;
    },
    tick() {
      if (!this.camOn) return;
      const v = document.getElementById('qrVideo');
      const c = document.getElementById('qrCanvas');
      if (v && v.readyState >= 2 && typeof jsQR === 'function') {
        c.width = v.videoWidth; c.height = v.videoHeight;
        const ctx = c.getContext('2d');
        ctx.drawImage(v, 0, 0);
        const img = ctx.getImageData(0, 0, c.width, c.height);
        const q = jsQR(img.data, img.width, img.height);
        if (q && q.data) {
          this.code = this.extract(q.data);
          this.stopCam();
          this.lookup();
          return;
        }
      }
      this.timer = requestAnimationFrame(() => this.tick());
    },
    async fromFile(ev) {
      const f = ev.target.files && ev.target.files[0];
      if (!f) return;
      try {
        let bitmap;
        if (typeof createImageBitmap === 'function') {
          try { bitmap = await createImageBitmap(f, { imageOrientation: 'from-image' }); }
          catch (e) { bitmap = await createImageBitmap(f); }
        }
        else {
          bitmap = await new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(f);
            img.onload = () => { URL.revokeObjectURL(objectUrl); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('Imagem não suportada')); };
            img.src = objectUrl;
          });
        }
        const raw = this.decodeQr(bitmap, bitmap.width || bitmap.naturalWidth, bitmap.height || bitmap.naturalHeight);
        if (bitmap.close) bitmap.close();
        if (raw) { this.code = this.extract(raw); await this.lookup(); }
        else UI.toast('Não encontrei um QR legível nessa foto. Tente uma imagem mais nítida e com o código inteiro visível.', 'err');
      } catch (e) {
        UI.toast('Não consegui abrir essa imagem. Escolha uma foto JPG ou PNG.', 'err');
      } finally {
        ev.target.value = '';
      }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
