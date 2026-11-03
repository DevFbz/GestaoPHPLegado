(function (global) {
  'use strict';

  const DB_NAME = 'gestao-brindes-offline';
  const DB_VERSION = 1;
  const STORE = 'outbox';
  let dbPromise = null;
  let syncing = false;

  const replayable = [
    /^\/api\/requests$/,
    /^\/api\/requests\/\d+\/deliver$/,
    /^\/api\/trade\/requests$/,
    /^\/api\/trade\/requests\/\d+\/(withdraw|receive)$/,
    /^\/api\/stock\/(entries|exits|adjustments|transfers|reversals)$/,
    /^\/api\/stock\/exit-orders\/\d+\/confirm$/,
    /^\/api\/events\/\d+\/(returns|withdrawals)$/
  ];

  function openDb() {
    if (dbPromise) return dbPromise;
    if (!('indexedDB' in global)) return Promise.reject(new Error('Armazenamento offline indisponível neste navegador.'));
    dbPromise = new Promise((resolve, reject) => {
      const request = global.indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE)) {
          const store = db.createObjectStore(STORE, { keyPath: 'id' });
          store.createIndex('status', 'status', { unique: false });
          store.createIndex('created_at', 'created_at', { unique: false });
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error || new Error('Não foi possível abrir o armazenamento offline.'));
    });
    return dbPromise;
  }

  function randomId() {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') return global.crypto.randomUUID();
    return 'offline-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
  }

  function canQueue(method, path, body, idempotencyKey) {
    return method === 'POST'
      && typeof idempotencyKey === 'string'
      && idempotencyKey.length >= 8
      && typeof path === 'string'
      && replayable.some((pattern) => pattern.test(path))
      && body !== undefined
      && body !== null
      && !(body instanceof FormData);
  }

  async function add(entry) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE, 'readwrite');
      transaction.objectStore(STORE).add({
        id: randomId(),
        method: entry.method,
        path: entry.path,
        body: entry.body,
        idempotency_key: entry.idempotencyKey,
        status: 'pending',
        attempts: 0,
        created_at: Date.now(),
        last_error: ''
      });
      transaction.oncomplete = () => { refreshStatus(); resolve(true); };
      transaction.onerror = () => reject(transaction.error || new Error('Não foi possível guardar a operação offline.'));
    });
  }

  async function all() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const request = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
      request.onsuccess = () => resolve((request.result || []).sort((a, b) => a.created_at - b.created_at));
      request.onerror = () => reject(request.error);
    });
  }

  async function update(id, values) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE, 'readwrite');
      const store = transaction.objectStore(STORE);
      const request = store.get(id);
      request.onsuccess = () => {
        if (request.result) store.put(Object.assign(request.result, values));
      };
      transaction.oncomplete = () => { refreshStatus(); resolve(); };
      transaction.onerror = () => reject(transaction.error);
    });
  }

  async function remove(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE, 'readwrite');
      transaction.objectStore(STORE).delete(id);
      transaction.oncomplete = () => { refreshStatus(); resolve(); };
      transaction.onerror = () => reject(transaction.error);
    });
  }

  async function pendingCount() {
    try { return (await all()).length; } catch (_) { return 0; }
  }

  async function refreshStatus(extra) {
    const element = document.getElementById('connectionStatus');
    if (!element) return;
    const count = await pendingCount();
    const text = element.querySelector('[data-connection-text]');
    element.classList.remove('is-offline', 'is-syncing', 'has-pending');
    if (!global.navigator.onLine) {
      element.hidden = false;
      element.classList.add('is-offline');
      if (text) text.textContent = count ? 'Offline · ' + count + ' pendente(s)' : 'Offline';
      return;
    }
    if (syncing || extra === 'syncing') {
      element.hidden = false;
      element.classList.add('is-syncing');
      if (text) text.textContent = 'Sincronizando…';
      return;
    }
    if (count) {
      element.hidden = false;
      element.classList.add('has-pending');
      if (text) text.textContent = count + ' operação(ões) pendente(s)';
      return;
    }
    element.hidden = true;
  }

  async function sync() {
    if (syncing || !global.navigator.onLine || !global.Api) return;
    syncing = true;
    await refreshStatus('syncing');
    try {
      const entries = (await all()).filter((entry) => entry.status !== 'failed');
      if (entries.length) {
        try { await global.Api.refreshCsrf(); } catch (_) { return; }
      }
      for (const entry of entries) {
        await update(entry.id, { status: 'syncing', attempts: (entry.attempts || 0) + 1 });
        try {
          await global.Api.replay(entry);
          await remove(entry.id);
        } catch (error) {
          const transient = !error || error.status === 0 || error.code === 'NETWORK_ERROR';
          await update(entry.id, {
            status: transient ? 'pending' : 'failed',
            last_error: error?.message || 'Falha ao sincronizar.'
          });
          if (transient) break;
        }
      }
    } finally {
      syncing = false;
      await refreshStatus();
    }
  }

  async function clearLocalData() {
    try {
      const db = await openDb();
      await new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE, 'readwrite');
        transaction.objectStore(STORE).clear();
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
      });
    } catch (_) {}
    if (navigator.serviceWorker?.controller) navigator.serviceWorker.controller.postMessage({ type: 'clear-caches' });
  }

  global.Offline = {
    canQueue,
    enqueue: add,
    sync,
    pendingCount,
    hasPending: async () => (await pendingCount()) > 0,
    clearLocalData,
    refreshStatus
  };

  if ('serviceWorker' in navigator) {
    const base = document.querySelector('meta[name="base-url"]')?.content || '';
    navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(() => {});
  }
  global.addEventListener('online', sync);
  global.addEventListener('offline', () => refreshStatus());
  global.addEventListener('focus', () => sync());
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
  document.addEventListener('DOMContentLoaded', () => { refreshStatus(); setTimeout(sync, 500); });
})(window);
