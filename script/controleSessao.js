// controleSessao.js

// Regras (final):
// - Inatividade: expira sem modal de renovação
// - Absoluta: mostra modal antes de expirar e permite renovação explícita

const idleExpireMs = (typeof window.IMPROOV_SESSION_IDLE_MS === 'number')
    ? window.IMPROOV_SESSION_IDLE_MS
    : 30 * 60 * 1000;

const idleWarnMs = (typeof window.IMPROOV_SESSION_IDLE_WARN_MS === 'number')
    ? window.IMPROOV_SESSION_IDLE_WARN_MS
    : 0;

const absExpireMs = (typeof window.IMPROOV_SESSION_ABSOLUTE_MS === 'number')
    ? window.IMPROOV_SESSION_ABSOLUTE_MS
    : 4 * 60 * 60 * 1000;

const absWarnMs = (typeof window.IMPROOV_SESSION_ABSOLUTE_WARN_MS === 'number')
    ? window.IMPROOV_SESSION_ABSOLUTE_WARN_MS
    : (4 * 60 * 60 * 1000) - (5 * 60 * 1000);

const loginTsSeconds = (typeof window.IMPROOV_LOGIN_TS === 'number') ? window.IMPROOV_LOGIN_TS : null;
let loginAtMs = (loginTsSeconds ? (loginTsSeconds * 1000) : Date.now());

let lastInteractionAtMs = Date.now();
let nextCheckTimer;
let absoluteWarnShown = false;
let sessionExpired = false;

// Keepalive: mantém servidor sincronizado com interações do usuário.
// Não estende o absoluto sozinho; ele só mantém o idle vivo quando há atividade.
// Em produção isso fica bem espaçado (ex.: ~5min). Em teste com tempos curtos,
// reduzimos automaticamente para não expirar por falta de request.
const keepaliveMinIntervalMs = Math.max(5 * 1000, Math.min(5 * 60 * 1000, Math.floor(idleWarnMs / 2)));
let lastKeepaliveAtMs = 0;

scheduleNextCheck();

function abrirModalSessao(state, reason) {
    const modal = document.getElementById("modalSessao");
    if (!modal) return;

    const titleEl = modal.querySelector('h2');
    const textEl = modal.querySelector('p');
    const buttons = modal.querySelectorAll('button');
    const primaryBtn = buttons[0] || null;
    const secondaryBtn = buttons[1] || null;

    modal.style.display = "flex";

    if (state === 'expired') {
        sessionExpired = true;
        if (titleEl) titleEl.textContent = 'Sessão expirada';
        if (reason === 'idle') {
            if (textEl) textEl.textContent = 'Sua sessão expirou por inatividade. Faça login novamente.';
        } else if (reason === 'absolute') {
            if (textEl) textEl.textContent = 'Sua sessão expirou por tempo máximo de login. Faça login novamente.';
        } else {
            if (textEl) textEl.textContent = 'Sua sessão expirou. Faça login novamente.';
        }

        if (primaryBtn) {
            primaryBtn.style.display = '';
            primaryBtn.textContent = 'Fazer login';
            primaryBtn.onclick = () => sair();
        }
        if (secondaryBtn) {
            secondaryBtn.style.display = 'none';
        }
        return;
    }

    // warning
    if (titleEl) titleEl.textContent = 'Sessão prestes a expirar';
    if (textEl) textEl.textContent = 'Sua sessão está perto do limite máximo. Deseja renovar por mais um período?';

    if (primaryBtn) {
        primaryBtn.style.display = '';
        primaryBtn.textContent = 'Continuar sessão';
        primaryBtn.onclick = () => renovarSessaoAbsoluta();
    }
    if (secondaryBtn) {
        secondaryBtn.style.display = '';
        secondaryBtn.textContent = 'Sair';
        secondaryBtn.onclick = () => sair();
    }
}

function fecharModalSessao() {
    const modal = document.getElementById("modalSessao");
    if (modal) modal.style.display = "none";
    scheduleNextCheck();
}

function renovarSessaoAbsoluta() {
    const base = getAppBasePath();
    fetch(`${base}/renova_sessao.php`, {
        credentials: "same-origin",
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            // Renovação explícita do período ABSOLUTO.
            'X-Improov-Renew-Absolute': '1'
        }
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error(`Falha ao renovar sessão (HTTP ${response.status})`);
            }
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (data && data.ok === false) {
                    throw new Error(data.message || 'Falha ao renovar sessão');
                }
                return data;
            }
            return response.text();
        })
        .then(() => {
            fecharModalSessao();
            const now = Date.now();
            // Reinicia janela absoluta e também atividade local
            loginAtMs = now;
            lastInteractionAtMs = now;
            absoluteWarnShown = false;
            sessionExpired = false;
            scheduleNextCheck();
        })
        .catch(() => {
            abrirModalSessao('expired', 'server');
        });
}

function sair() {
    const base = getAppBasePath();
    window.location.href = `${base}/logout.php`;
}

function getAppBasePath() {
    // Se o sistema roda em subpasta (ex.: /flow/ImproovWeb/...), precisamos manter o prefixo.
    // Se roda na raiz do domínio, retorna ''.
    const p = window.location.pathname || '';
    const marker = '/ImproovWeb/';
    const idx = p.indexOf(marker);
    if (idx === -1) return '';
    return p.slice(0, idx + '/ImproovWeb'.length);
}

function scheduleNextCheck() {
    if (sessionExpired) return;
    clearTimeout(nextCheckTimer);

    const now = Date.now();
    const idleExpireAt = lastInteractionAtMs + idleExpireMs;

    const absWarnAt = loginAtMs + absWarnMs;
    const absExpireAt = loginAtMs + absExpireMs;

    // If absolute already expired, show expired.
    if (absExpireAt && now >= absExpireAt) {
        abrirModalSessao('expired', 'absolute');
        return;
    }

    // If idle already expired, show expired.
    if (now >= idleExpireAt) {
        abrirModalSessao('expired', 'idle');
        return;
    }

    // Absolute warning (single shot per absolute cycle)
    if (now >= absWarnAt && !absoluteWarnShown) {
        absoluteWarnShown = true;
        abrirModalSessao('warning', 'absolute');
        return;
    }

    // Schedule the next boundary
    const candidates = [idleExpireAt, absExpireAt];
    if (!absoluteWarnShown) {
        candidates.push(absWarnAt);
    }

    const future = candidates.filter(t => t > now).sort((a, b) => a - b)[0];
    const delay = future ? Math.max(50, future - now) : 1000;
    nextCheckTimer = setTimeout(scheduleNextCheck, delay);
}

function noteInteraction() {
    if (sessionExpired) return;
    const now = Date.now();
    lastInteractionAtMs = now;

    maybeKeepalive();
    scheduleNextCheck();
}

function maybeKeepalive() {
    const now = Date.now();
    if (now - lastKeepaliveAtMs < keepaliveMinIntervalMs) return;

    // If absolute deadline already passed, do not keepalive.
    if (loginAtMs && now >= (loginAtMs + absExpireMs)) return;

    lastKeepaliveAtMs = now;
    const base = getAppBasePath();
    fetch(`${base}/renova_sessao.php`, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then((r) => {
        if (!r.ok) throw new Error('keepalive failed');
        return r.json().catch(() => ({}));
    }).then((data) => {
        if (data && data.ok === false) throw new Error('keepalive rejected');
    }).catch(() => {
        abrirModalSessao('expired', 'server');
    });
}

// Interações (inclui mouse e outros inputs)
['mousemove', 'mousedown', 'click', 'scroll', 'keydown', 'touchstart'].forEach((evt) => {
    document.addEventListener(evt, noteInteraction, { passive: true });
});

