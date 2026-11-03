/*
 * Cross-tab invalidation for the browser UI.
 *
 * The server database remains the source of truth. localStorage stores only a
 * tiny revision marker, never business records, so a refresh cannot recreate
 * or overwrite stock, requests or movements with stale browser data.
 */
(function (global) {
  'use strict';

  const KEY = 'brindes:data-revision:v1';
  const CHANNEL_NAME = 'brindes-data-change:v1';
  const TAB_ID = (() => {
    try {
      const current = global.sessionStorage.getItem('brindes:tab-id');
      if (current) return current;
      const next = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
      global.sessionStorage.setItem('brindes:tab-id', next);
      return next;
    } catch (_) {
      return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }
  })();
  let channel = null;
  let lastRevision = '';

  try {
    if ('BroadcastChannel' in global) channel = new global.BroadcastChannel(CHANNEL_NAME);
  } catch (_) {
    channel = null;
  }

  function parse(value) {
    if (!value) return null;
    try {
      const data = typeof value === 'string' ? JSON.parse(value) : value;
      return data && data.revision ? data : null;
    } catch (_) {
      return null;
    }
  }

  function dispatch(data) {
    if (!data || data.revision === lastRevision) return;
    lastRevision = data.revision;
    global.dispatchEvent(new CustomEvent('brindes:data-changed', { detail: data }));
  }

  function touch(source) {
    const data = {
      revision: Date.now().toString(36) + '-' + Math.random().toString(36).slice(2),
      changed_at: new Date().toISOString(),
      source: String(source || 'mutation'),
      origin: TAB_ID,
    };
    try { global.localStorage.setItem(KEY, JSON.stringify(data)); } catch (_) {}
    dispatch(data);
    try { channel?.postMessage(data); } catch (_) {}
  }

  function listen(callback) {
    const handler = (event) => {
      const data = event?.detail || event;
      if (data && data.revision && data.origin !== TAB_ID) callback(data);
    };
    const storageHandler = (event) => {
      if (event.key === KEY) {
        const data = parse(event.newValue);
        if (data && data.origin !== TAB_ID) dispatch(data);
      }
    };
    global.addEventListener('brindes:data-changed', handler);
    global.addEventListener('storage', storageHandler);
    if (channel) channel.addEventListener('message', handler);
    return () => {
      global.removeEventListener('brindes:data-changed', handler);
      global.removeEventListener('storage', storageHandler);
      channel?.removeEventListener('message', handler);
    };
  }

  global.BrindesSync = { touch, listen, key: KEY };
})(window);
