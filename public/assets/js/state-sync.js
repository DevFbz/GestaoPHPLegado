/*
 * Cross-session invalidation for the browser UI.
 *
 * The server database remains the source of truth. localStorage stores only a
 * tiny revision marker, never business records. The authenticated server
 * revision poll detects other users; browser messaging keeps this user's tabs
 * in sync without copying business records into localStorage.
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
  let lastServerRevision = null;
  let listenerCount = 0;
  let hasUnsavedEdits = false;
  let staleNoticeShown = false;

  try {
    if ('BroadcastChannel' in global) channel = new global.BroadcastChannel(CHANNEL_NAME);
  } catch (_) {
    channel = null;
  }

  global.document?.addEventListener('input', (event) => {
    if (event.target?.closest?.('form')) hasUnsavedEdits = true;
  }, true);
  global.document?.addEventListener('change', (event) => {
    if (event.target?.closest?.('form')) hasUnsavedEdits = true;
  }, true);

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
    if (Number.isFinite(Number(data.server_revision))) {
      lastServerRevision = Math.max(lastServerRevision || 0, Number(data.server_revision));
    }
    if (data.source === 'remote' && data.origin !== TAB_ID) {
      if (hasUnsavedEdits) {
        if (!staleNoticeShown) {
          staleNoticeShown = true;
          global.dispatchEvent(new CustomEvent('brindes:data-stale', { detail: data }));
        }
        return;
      }
      if (listenerCount === 0) {
        global.location.reload();
        return;
      }
    }
    global.dispatchEvent(new CustomEvent('brindes:data-changed', { detail: data }));
  }

  function touch(source, serverRevision) {
    const data = {
      revision: serverRevision === undefined
        ? Date.now().toString(36) + '-' + Math.random().toString(36).slice(2)
        : 'server-' + String(serverRevision),
      changed_at: new Date().toISOString(),
      source: String(source || 'mutation'),
      origin: TAB_ID,
    };
    if (serverRevision !== undefined) {
      data.server_revision = Number(serverRevision);
      data.origin = 'server';
    }
    try { global.localStorage.setItem(KEY, JSON.stringify(data)); } catch (_) {}
    dispatch(data);
    try { channel?.postMessage(data); } catch (_) {}
  }

  function listen(callback) {
    listenerCount++;
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
      listenerCount = Math.max(0, listenerCount - 1);
      global.removeEventListener('brindes:data-changed', handler);
      global.removeEventListener('storage', storageHandler);
      channel?.removeEventListener('message', handler);
    };
  }

  let pollTimer = null;
  let pollStopped = false;
  let pollInFlight = false;
  const pollDelay = 2000;

  function schedulePoll(delay) {
    if (pollStopped) return;
    global.clearTimeout(pollTimer);
    pollTimer = global.setTimeout(pollServer, delay);
  }

  async function pollServer() {
    if (pollStopped || pollInFlight) return;
    if (global.document?.hidden) {
      schedulePoll(5000);
      return;
    }
    pollInFlight = true;
    try {
      const base = global.document?.querySelector('meta[name="base-url"]')?.content || '';
      const url = new URL(base + '/api/sync/revision', global.location.origin);
      if (lastServerRevision !== null) url.searchParams.set('since', String(lastServerRevision));
      const response = await global.fetch(url.toString(), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      if (response.status === 401) {
        pollStopped = true;
        global.Api?.handlers?.unauthenticated();
        return;
      }
      if (!response.ok) throw new Error('Sync request failed');
      const payload = await response.json();
      const state = payload?.data;
      const revision = Number(state?.revision);
      if (!Number.isFinite(revision)) throw new Error('Invalid sync revision');
      if (lastServerRevision === null) {
        lastServerRevision = revision;
      } else if (revision > lastServerRevision) {
        lastServerRevision = revision;
        if (state.changed === true) touch('remote', revision);
      }
    } catch (_) {
      // Keep the page usable while disconnected; the next request retries.
    } finally {
      pollInFlight = false;
      schedulePoll(pollDelay);
    }
  }

  global.document?.addEventListener('visibilitychange', () => {
    if (!global.document.hidden) schedulePoll(0);
  });
  global.BrindesSync = { touch, listen, key: KEY };
  schedulePoll(1200);
})(window);
