// Central fetch/XHR session recovery. No business request is replayed.
(function () {
if (window.FlowAuthNavigation) return;

const authBase = window.location.pathname.startsWith('/flow/ImproovWeb/') ? '/flow/ImproovWeb' : '/ImproovWeb';
function cleanSearch(search) {
    const query = search.replace(/^\?/, '').split('&').filter(part => !part.startsWith('_flow_auth_resume='));
    const result = query.join('&');
    return result ? '?' + result : '';
}
if (window.location.search.split('&').some(part => /^\??_flow_auth_resume=/.test(part))) {
    history.replaceState(history.state, '', window.location.pathname + cleanSearch(window.location.search) + window.location.hash);
}
let authNavigating = false;
function resumeAuthentication() {
    if (authNavigating) return;
    authNavigating = true;
    // A document GET lets the server guard store this tab's path and query.
    // Never replay a failed fetch, XHR, form POST or other business operation.
    // A distinct query forces a document GET even when the current URL has a hash.
    // The guard removes this reserved marker before storing the original destination.
    const search = cleanSearch(window.location.search);
    const marker = Date.now().toString(36) + Math.random().toString(36).slice(2);
    window.location.replace(window.location.pathname + search + (search ? '&' : '?') + '_flow_auth_resume=' + marker + window.location.hash);
}
function internalAuthRequest(value) {
    try {
        const url = new URL(value, window.location.href);
        const path = url.pathname;
        return url.origin === window.location.origin && path.startsWith(authBase + '/')
            && !path.startsWith(authBase + '/FlowReviewExt/')
            && ![authBase + '/login.php', authBase + '/logout.php', authBase + '/login-preview.php', authBase + '/index.html'].includes(path);
    } catch (_) { return false; }
}
function loginDocument(value) {
    try {
        const url = new URL(value, window.location.href);
        return url.origin === window.location.origin
            && [authBase + '/index.html', authBase + '/login-preview.php', authBase + '/'].includes(url.pathname);
    } catch (_) { return false; }
}
window.FlowAuthNavigation = Object.freeze({ reauthenticate: resumeAuthentication });
if (window.fetch) {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        const protectedRequest = internalAuthRequest(typeof input === 'string' || input instanceof URL ? input : input.url)
            && (init?.credentials || input?.credentials) !== 'omit';
        let options = init;
        if (protectedRequest) {
            const headers = new Headers(init?.headers || input?.headers);
            headers.set('X-Flow-Auth-Required', '1');
            options = { ...init, headers };
        }
        return nativeFetch(input, options).then((response) => {
            if (protectedRequest && response.status === 401) resumeAuthentication();
            if (protectedRequest && response.redirected && loginDocument(response.url)) {
                resumeAuthentication();
                // Legacy redirects also become an explicit failure, never a successful login HTML.
                return new Response(JSON.stringify({ ok: false, code: 'AUTH_REQUIRED' }), {
                    status: 401, headers: { 'Content-Type': 'application/json' }
                });
            }
            return response;
        });
    };
}
if (window.XMLHttpRequest) {
    const nativeOpen = XMLHttpRequest.prototype.open;
    const nativeSend = XMLHttpRequest.prototype.send;
    const requests = new WeakMap();
    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        requests.set(this, internalAuthRequest(url));
        return nativeOpen.call(this, method, url, ...args);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (requests.get(this)) {
            this.setRequestHeader('X-Flow-Auth-Required', '1');
            this.addEventListener('load', () => {
                if (this.status === 401 || loginDocument(this.responseURL)) resumeAuthentication();
            }, { once: true });
        }
        return nativeSend.call(this, body);
    };
}

})();
