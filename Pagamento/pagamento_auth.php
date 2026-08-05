<?php

require_once __DIR__ . '/../config/session_bootstrap.php';

if (!function_exists('pagamento_json')) {
    function pagamento_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('pagamento_csrf_token')) {
    function pagamento_csrf_token(): string
    {
        if (empty($_SESSION['pagamento_csrf'])) {
            $_SESSION['pagamento_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['pagamento_csrf'];
    }
}

if (!function_exists('pagamento_is_gestor')) {
    function pagamento_is_gestor(): bool
    {
        return in_array((int) ($_SESSION['nivel_acesso'] ?? 0), [1, 5], true);
    }
}

if (!function_exists('pagamento_require_gestor')) {
    function pagamento_require_gestor(bool $requireCsrf = true): void
    {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            pagamento_json(['success' => false, 'error' => 'Não autenticado.'], 401);
        }

        if (!pagamento_is_gestor()) {
            pagamento_json(['success' => false, 'error' => 'Sem permissão para gerir pagamentos.'], 403);
        }

        if (!$requireCsrf) {
            return;
        }

        $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
        if ($provided === '' || !hash_equals(pagamento_csrf_token(), $provided)) {
            pagamento_json(['success' => false, 'error' => 'Token CSRF inválido. Atualize a página e tente novamente.'], 419);
        }
    }
}

if (!function_exists('pagamento_request_json')) {
    function pagamento_request_json(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode(is_string($raw) ? $raw : '', true);

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('pagamento_current_user_id')) {
    function pagamento_current_user_id(): ?int
    {
        $id = (int) ($_SESSION['idusuario'] ?? 0);
        return $id > 0 ? $id : null;
    }
}
