<?php

// Session bootstrap for ImproovWeb
// Supports idle timeout (inactivity) and/or absolute timeout (fixed since login).

// ===== Config (edit as needed) =====
// Modes: 'idle', 'absolute', 'both'
if (!defined('IMPROOV_SESSION_MODE')) {
    define('IMPROOV_SESSION_MODE', 'both');
}

// ===== TESTE RÁPIDO =====
// Produção: 30 * 60
if (!defined('IMPROOV_SESSION_IDLE_SECONDS')) {
    define('IMPROOV_SESSION_IDLE_SECONDS', 30 * 60);
}

// Produção: sem aviso para inatividade (expira direto)
if (!defined('IMPROOV_SESSION_IDLE_WARN_SECONDS')) {
    define('IMPROOV_SESSION_IDLE_WARN_SECONDS', 0);
}

// Produção: 4 * 60 * 60
if (!defined('IMPROOV_SESSION_ABSOLUTE_SECONDS')) {
    define('IMPROOV_SESSION_ABSOLUTE_SECONDS', 4 * 60 * 60);
}

// Produção: (4 * 60 * 60) - (5 * 60)  // aviso 5 min antes
if (!defined('IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS')) {
    define('IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS', (4 * 60 * 60) - (5 * 60));
}

// Where to send the user when session expires (HTML requests)
if (!defined('IMPROOV_LOGIN_PATH')) {
    define('IMPROOV_LOGIN_PATH', 'index.html');
}

// ===== Helpers =====
if (!function_exists('improov_app_base_path')) {
    function improov_app_base_path(): string
    {
        $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($reqUri, '/flow/ImproovWeb/') !== false || preg_match('~^/flow/ImproovWeb(?:/|$)~', $reqUri)) {
            return '/flow/ImproovWeb';
        }
        return '/ImproovWeb';
    }
}

if (!function_exists('improov_is_json_request')) {
    function improov_is_json_request(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($xhr === 'xmlhttprequest') {
            return true;
        }
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('improov_end_session')) {
    function improov_end_session(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('improov_handle_expired_session')) {
    function improov_handle_expired_session(string $message = 'Sessão expirada.'): void
    {
        improov_end_session();

        if (improov_is_json_request()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            echo json_encode(['ok' => false, 'message' => $message]);
            exit;
        }

        $base = improov_app_base_path();
        header('Location: ' . $base . '/' . ltrim(IMPROOV_LOGIN_PATH, '/'));
        exit;
    }
}

// ===== Bootstrap =====
// Harden baseline behavior
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

$mode = (string)IMPROOV_SESSION_MODE;
$idleSeconds = (int)IMPROOV_SESSION_IDLE_SECONDS;
$absoluteSeconds = (int)IMPROOV_SESSION_ABSOLUTE_SECONDS;
$renewAbsoluteRequested = (isset($_SERVER['HTTP_X_IMPROOV_RENEW_ABSOLUTE']) && (string)$_SERVER['HTTP_X_IMPROOV_RENEW_ABSOLUTE'] === '1');

$maxLifetime = 0;
if ($mode === 'idle' || $mode === 'both') {
    $maxLifetime = max($maxLifetime, $idleSeconds);
}
if ($mode === 'absolute' || $mode === 'both') {
    $maxLifetime = max($maxLifetime, $absoluteSeconds);
}
if ($maxLifetime > 0) {
    ini_set('session.gc_maxlifetime', (string)$maxLifetime);
    ini_set('session.cookie_lifetime', (string)$maxLifetime);
}

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// Configure cookie params only if the session is not active yet
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $maxLifetime > 0 ? $maxLifetime : 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Enforce timeouts only for authenticated sessions
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    $now = time();

    if (!isset($_SESSION['login_ts']) || !is_int($_SESSION['login_ts'])) {
        // If login_ts was not set (legacy sessions), initialize it now.
        $_SESSION['login_ts'] = $now;
    }
    if (!isset($_SESSION['last_activity_ts']) || !is_int($_SESSION['last_activity_ts'])) {
        $_SESSION['last_activity_ts'] = $now;
    }

    // Idle timeout
    if (($mode === 'idle' || $mode === 'both') && $idleSeconds > 0) {
        $last = (int)$_SESSION['last_activity_ts'];
        if (($now - $last) > $idleSeconds) {
            improov_handle_expired_session('Sessão expirada por inatividade.');
        }
    }

    // Absolute timeout
    if (($mode === 'absolute' || $mode === 'both') && $absoluteSeconds > 0) {
        $loginTs = (int)$_SESSION['login_ts'];
        if (($now - $loginTs) > $absoluteSeconds) {
            improov_handle_expired_session('Sessão expirada.');
        }
    }

    // Explicit renewal of ABSOLUTE window (used by "Continuar sessão" on absolute warning modal)
    // This does not bypass already-expired sessions because checks above run first.
    if ($renewAbsoluteRequested && ($mode === 'absolute' || $mode === 'both') && $absoluteSeconds > 0) {
        $_SESSION['login_ts'] = $now;
    }

    // Refresh activity timestamp
    $_SESSION['last_activity_ts'] = $now;

    // Extend cookie expiry to match the chosen policy (best-effort)
    if (!headers_sent() && $maxLifetime > 0) {
        $cookieParams = session_get_cookie_params();
        $expires = 0;

        if ($mode === 'absolute') {
            $expires = ((int)$_SESSION['login_ts']) + $absoluteSeconds;
        } elseif ($mode === 'idle') {
            $expires = $now + $idleSeconds;
        } elseif ($mode === 'both') {
            $expires = min(((int)$_SESSION['login_ts']) + $absoluteSeconds, $now + $idleSeconds);
        }

        if ($expires > 0) {
            setcookie(session_name(), session_id(), [
                'expires' => $expires,
                'path' => $cookieParams['path'] ?? '/',
                'domain' => $cookieParams['domain'] ?? '',
                'secure' => (bool)($cookieParams['secure'] ?? $isSecure),
                'httponly' => (bool)($cookieParams['httponly'] ?? true),
                'samesite' => $cookieParams['samesite'] ?? 'Lax',
            ]);
        }
    }
}
