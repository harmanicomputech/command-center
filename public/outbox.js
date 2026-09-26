/*
 * The Field Force outbox: every form (registration, task update, issue,
 * survey response) is saved here first, in IndexedDB, with its UUID, and
 * removed only after the server confirms it. Shared by the pages
 * (window.ccOutbox) and the service worker (importScripts, Background Sync).
 *
 * Server answers per item: ok → removed; invalid → kept as "failed" with the
 * reason, for the agent to fix; error, 5xx or no network → kept and retried
 * with backoff. A 401 means the phone must sign in again: nothing is lost.
 */
(function (root) {
  'use strict';

  const DB_NAME = 'cc-outbox';
  const BATCH = 25;
  const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('cc-outbox') : null;

  function open() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        db.createObjectStore('items', { keyPath: 'id' });
        db.createObjectStore('ref', { keyPath: 'key' });
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function run(storeName, mode, fn) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, mode);
      const request = fn(tx.objectStore(storeName));
      tx.oncomplete = () => {
        db.close();
        resolve(request ? request.result : undefined);
      };
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  }

  function uuid() {
    if (root.crypto && root.crypto.randomUUID) {
      return root.crypto.randomUUID();
    }
    const bytes = root.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  }

  const all = () => run('items', 'readonly', (store) => store.getAll()).then((items) => (items || []).sort((a, b) => a.createdAt - b.createdAt));
  const get = (id) => run('items', 'readonly', (store) => store.get(id));
  const put = (item) => run('items', 'readwrite', (store) => store.put(item));

  async function counts() {
    const items = await all();
    const failed = items.filter((item) => item.status === 'failed').length;
    return { total: items.length, failed, pending: items.length - failed };
  }

  async function announce(extra = {}) {
    const state = { ...(await counts()), ...extra };
    channel && channel.postMessage(state);
    return state;
  }

  async function add(type, payload, label) {
    const item = { id: uuid(), type, payload, label: label || type, createdAt: Date.now(), status: 'pending', attempts: 0, nextAt: 0, error: null, errors: null };
    await put(item);
    await announce();
    requestBackgroundSync();
    return item;
  }

  // Save a corrected item under the same ID: it becomes pending again.
  async function replace(id, payload, label) {
    const item = await get(id);
    if (!item) {
      return add('voter', payload, label);
    }
    Object.assign(item, { payload, label: label || item.label, status: 'pending', attempts: 0, nextAt: 0, error: null, errors: null });
    await put(item);
    await announce();
    requestBackgroundSync();
    return item;
  }

  // A photo for a record in the outbox: sent after the record itself.
  async function addPhoto(ownerType, ownerUuid, blob, label) {
    const item = { id: uuid(), type: 'photo', payload: { owner_type: ownerType, owner_uuid: ownerUuid }, blob, label: label || 'Photo', createdAt: Date.now(), status: 'pending', attempts: 0, nextAt: 0, error: null, errors: null };
    await put(item);
    await announce();
    requestBackgroundSync();
    return item;
  }

  async function remove(id) {
    await run('items', 'readwrite', (store) => store.delete(id));
    return announce();
  }

  const setRef = (key, value) => run('ref', 'readwrite', (store) => store.put({ key, value, savedAt: Date.now() }));
  const getRef = (key) => run('ref', 'readonly', (store) => store.get(key)).then((row) => (row ? row.value : null));

  function requestBackgroundSync() {
    if (root.navigator && root.navigator.serviceWorker && root.document) {
      root.navigator.serviceWorker.ready
        .then((registration) => registration.sync && registration.sync.register('cc-outbox'))
        .catch(() => {});
    }
  }

  function backoff(item, message) {
    item.attempts += 1;
    item.nextAt = Date.now() + Math.min(15 * 60 * 1000, 5000 * Math.pow(2, item.attempts));
    item.error = message || null;
    return put(item);
  }

  async function token() {
    const response = await fetch('/api/field/token', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (response.status === 401 || response.redirected) {
      return null;
    }
    if (!response.ok) {
      throw new Error(`token ${response.status}`);
    }
    return (await response.json()).token;
  }

  async function send(batch, csrf) {
    return fetch('/api/field/sync', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ items: batch.map(({ id, type, payload }) => ({ id, type, payload })) }),
    });
  }

  async function sendPhoto(item, csrf) {
    const data = new FormData();
    data.append('uuid', item.id);
    data.append('owner_type', item.payload.owner_type);
    data.append('owner_uuid', item.payload.owner_uuid);
    data.append('photo', item.blob, 'photo.jpg');
    return fetch('/api/field/photos', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: data });
  }

  async function syncNow(force) {
    const ready = (await all()).filter((item) => item.status === 'pending' && (force || item.nextAt <= Date.now()));
    // Records first, their photos after (a photo needs its record on the server).
    const due = ready.filter((item) => item.type !== 'photo');
    const photos = ready.filter((item) => item.type === 'photo');
    if (!due.length && !photos.length) {
      return announce({ sent: 0 });
    }

    let sent = 0;
    try {
      let csrf = await token();
      if (csrf === null) {
        return announce({ auth: false });
      }

      for (let i = 0; i < due.length; i += BATCH) {
        const batch = due.slice(i, i + BATCH);
        let response = await send(batch, csrf);

        if (response.status === 419) {
          csrf = await token();
          response = csrf === null ? response : await send(batch, csrf);
        }
        if (response.status === 401 || response.status === 419) {
          return announce({ auth: false, sent });
        }
        if (!response.ok) {
          await Promise.all(batch.map((item) => backoff(item, response.status >= 500 ? 'Server busy: will retry.' : `Refused (${response.status}).`)));
          continue;
        }

        const { results } = await response.json();
        const byId = new Map(batch.map((item) => [item.id, item]));
        for (const result of results || []) {
          const item = byId.get(result.id);
          if (!item) {
            continue;
          }
          if (result.status === 'ok') {
            await run('items', 'readwrite', (store) => store.delete(item.id));
            sent += 1;
          } else if (result.status === 'invalid') {
            Object.assign(item, { status: 'failed', error: result.message || 'Needs fixing.', errors: result.errors || null });
            await put(item);
          } else {
            await backoff(item, result.message);
          }
        }
      }

      for (const item of photos) {
        const response = await sendPhoto(item, csrf);
        if (response.status === 401 || response.status === 419) {
          return announce({ auth: false, sent });
        }
        if (response.ok) {
          await run('items', 'readwrite', (store) => store.delete(item.id));
          sent += 1;
        } else if (response.status === 422 || response.status === 403) {
          const body = await response.json().catch(() => ({}));
          Object.assign(item, { status: 'failed', error: body.message || 'The server refused this photo.' });
          await put(item);
        } else {
          // 409: its record hasn't synced yet; 5xx: server busy. Try later.
          await backoff(item, response.status === 409 ? 'Waiting for its record to sync.' : 'Server busy: will retry.');
        }
      }
    } catch (error) {
      // No network (or it dropped mid-way): everything left stays queued.
      await Promise.all([...due, ...photos].map(async (item) => {
        const fresh = await get(item.id);
        return fresh && fresh.status === 'pending' ? backoff(fresh, 'No network: will retry.') : null;
      }));
      return announce({ offline: true, sent });
    }

    return announce({ sent });
  }

  // One sync at a time across the page and the service worker.
  function sync(force) {
    const locks = root.navigator && root.navigator.locks;
    return locks ? locks.request('cc-outbox-sync', () => syncNow(force)) : syncNow(force);
  }

  root.ccOutbox = { add, addPhoto, replace, remove, get, all, counts, sync, setRef, getRef, uuid, channel };
})(self);
