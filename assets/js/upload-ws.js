// Global upload WebSocket listener
(function () {
    const STORAGE_KEY = 'improov_client_id';
    const NOTIFIED_PREFIX = 'improov_upload_notified_';
    const BC_NAME = 'improov-upload';

    let ws = null;
    let subscribedId = null;
    let reconnectTimer = null;

    function log() {
        if (window.console && console.log) console.log.apply(console, arguments);
    }

    function getClientId() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }

    function markNotified(id) {
        try { localStorage.setItem(NOTIFIED_PREFIX + id, String(Date.now())); } catch (e) {}
    }

    function wasNotified(id) {
        try { return !!localStorage.getItem(NOTIFIED_PREFIX + id); } catch (e) { return false; }
    }

    function notify(title, body) {
        // prefer Notifications API
        try {
            if (window.Notification) {
                if (Notification.permission === 'granted') {
                    new Notification(title, { body });
                    return;
                }
                if (Notification.permission === 'default') {
                    // request permission and notify if granted
                    Notification.requestPermission().then(p => { if (p === 'granted') try { new Notification(title, { body }); } catch (e) { log('notify new error', e); } });
                    return;
                }
            }
        } catch (e) { log('notify check error', e); }

        // fallback UI: small transient toast in-page
        try {
            var t = document.createElement('div');
            t.textContent = title + ' — ' + body;
            t.style.position = 'fixed';
            t.style.right = '16px';
            t.style.bottom = '16px';
            t.style.background = 'rgba(0,0,0,0.85)';
            t.style.color = 'white';
            t.style.padding = '10px 14px';
            t.style.borderRadius = '8px';
            t.style.zIndex = 99999;
            t.style.fontSize = '13px';
            document.body.appendChild(t);
            setTimeout(function () { try { t.remove(); } catch (e) {} }, 6000);
        } catch (e) { log('notify fallback', e); }
    }

    // ensure notification for payload (handles permission flow)
    function ensureNotify(payload) {
        var title = 'Upload concluído';
        var body = payload.message || 'Upload finalizado com sucesso.';
        try {
            if (window.Notification && Notification.permission === 'granted') {
                notify(title, body);
                return;
            }
            if (window.Notification && Notification.permission === 'default') {
                // try requesting permission on user gesture; if browser blocks, fallback will show
                Notification.requestPermission().then(p => {
                    if (p === 'granted') notify(title, body);
                    else notify(title, body); // will use in-page fallback
                }).catch(function (e) { log('requestPermission error', e); notify(title, body); });
                return;
            }
        } catch (e) { log('ensureNotify error', e); }
        // fallback
        notify(title, body);
    }

    function connect() {
        const id = getClientId();
        if (!id) {
            log('upload-ws: no client id in localStorage; not connecting');
            return;
        }
        subscribedId = id;

        // build WS url (use path-based /ws/ when on HTTPS)
        let wsUrl;
        if (location.protocol === 'https:') {
            wsUrl = 'wss://' + location.hostname + '/ws/';
        } else {
            wsUrl = 'ws://' + location.hostname + ':8082';
        }

        try { ws = new WebSocket(wsUrl); } catch (e) { log('upload-ws create socket error', e); scheduleReconnect(); return; }

        ws.addEventListener('open', () => {
            log('upload-ws open', wsUrl, 'subscribe', subscribedId);
            try { ws.send(JSON.stringify({ subscribe: subscribedId })); } catch (e) {}
        });

        ws.addEventListener('message', ev => {
            try {
                const data = JSON.parse(ev.data);
                const payload = data.payload || data;
                if (!payload || !payload.id) return;
                if (payload.id !== subscribedId) return; // ignore unrelated

                log('upload-ws got', payload);

                const isDone = payload.status && payload.status.toString().toLowerCase() === 'done' || payload.progress === 100;
                if (isDone && !wasNotified(payload.id)) {
                    // mark once and notify
                    markNotified(payload.id);
                    // also broadcast so other tabs update quickly
                    try { bc.postMessage({ type: 'notified', id: payload.id }); } catch (e) {}
                    try { ensureNotify(payload); } catch (e) { notify('Upload concluído', payload.message || 'Upload finalizado com sucesso.'); }
                }
                // optionally: dispatch a DOM event for pages to hook into
                try {
                    const evn = new CustomEvent('improov:uploadProgress', { detail: payload });
                    window.dispatchEvent(evn);
                } catch (e) {}

            } catch (e) { log('upload-ws parse error', e, ev.data); }
        });

        ws.addEventListener('close', ev => { log('upload-ws closed', ev); scheduleReconnect(); });
        ws.addEventListener('error', err => { log('upload-ws error', err); try { ws.close(); } catch(e){}; });
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            connect();
        }, 2500);
    }

    // BroadcastChannel to coordinate notifications between tabs
    const bc = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel(BC_NAME) : null;
    if (bc) {
        bc.onmessage = (ev) => {
            try {
                const d = ev.data;
                if (!d) return;
                if (d.type === 'notified' && d.id) {
                    markNotified(d.id);
                }
            } catch (e) {}
        };
    }

    // watch for storage changes (other tabs setting client id or notified keys)
    window.addEventListener('storage', (ev) => {
        try {
            if (!ev.key) return;
            if (ev.key === STORAGE_KEY) {
                // client id changed -> reconnect
                log('upload-ws storage change client id', ev.newValue);
                if (ws) try { ws.close(); } catch (e) {}
                connect();
            }
            if (ev.key && ev.key.indexOf(NOTIFIED_PREFIX) === 0) {
                // nothing to do, but kept for completeness
            }
        } catch (e) {}
    });

    // expose a helper for other scripts to force subscribe
    window.improovUploadWS = {
        subscribe: function (id) {
            try { localStorage.setItem(STORAGE_KEY, id); } catch (e) {}
            if (ws && ws.readyState === WebSocket.OPEN) {
                try { ws.send(JSON.stringify({ subscribe: id })); } catch (e) {}
            } else {
                // close current and reconnect
                if (ws) try { ws.close(); } catch (e) {}
                connect();
            }
        }
    };

    // initial connect if id present
    try { if (getClientId()) connect(); } catch (e) {}

})();
