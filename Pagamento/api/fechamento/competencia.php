<?php

require_once __DIR__.'/../../pagamento_auth.php';
require_once __DIR__.'/../../../config/pagamento_fechamento.php';
require_once __DIR__.'/../../services/FechamentoCompetenciaService.php';
header('Cache-Control: no-store');
$conn = null;
try {
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if (!$post && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        pagamento_json(['success' => false,'error' => 'Método não permitido.'], 405);
    }
    pagamento_require_gestor($post);
    if (!pagamento_fechamento_enabled()) {
        pagamento_json(['success' => false,'error' => 'Fechamento indisponível.'], 404);
    }
    $d = $post ? pagamento_request_json() : $_GET;
    $action = $post ? ($d['acao'] ?? '') : 'resumo';
    $allowed = match($action) {
        'resumo' => ['competencia'], 'concluir','quitar' => ['acao','competencia','idempotency_key'],
        'pagar' => ['acao','competencia','idempotency_key','colaborador_id','data_pagamento','observacao'],
        default => throw new InvalidArgumentException('Ação inválida.')
    };
    if (array_diff(array_keys($d), $allowed)) {
        throw new InvalidArgumentException('Campos não permitidos.');
    }
    $ref = $d['competencia'] ?? '';
    if (!is_string($ref) || !pagamento_competencia_nova($ref)) {
        throw new InvalidArgumentException('Competência fora do novo fluxo.');
    }
    $u = pagamento_current_user_id();
    session_write_close();
    $conn = pagamento_fechamento_connection();
    if (!FechamentoCompetenciaService::disponivel($conn)) {
        throw new RuntimeException('Migration da competência ausente.');
    }
    $s = new FechamentoCompetenciaService($conn, $u);
    if ($post) {
        $key = $d['idempotency_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)) {
            throw new InvalidArgumentException('Chave idempotente obrigatória.');
        }
    }
    $data = match($action) {
        'resumo' => $s->resumo($ref), 'concluir' => $s->concluir($ref, $key), 'quitar' => $s->concluirPagamento($ref, $key),
        'pagar' => $s->pagar($ref, filter_var($d['colaborador_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0, $key, (string)($d['data_pagamento'] ?? ''), (string)($d['observacao'] ?? ''))
    };
    pagamento_json(['success' => true,'data' => $data]);
} catch (InvalidArgumentException|DomainException $e) {
    pagamento_json(['success' => false,'error' => $e->getMessage()], 409);
} catch (Throwable $e) {
    $requestId = bin2hex(random_bytes(8));
    $diagnostico = ['request_id' => $requestId,'exception' => get_class($e)];
    if ($e instanceof mysqli_sql_exception) {
        $diagnostico['errno'] = $e->getCode();
        $diagnostico['sqlstate'] = $e->getSqlState();
        if ($e->getCode() === 1644 && $e->getSqlState() === '45000') {
            $diagnostico['regra'] = substr(preg_replace('/[\r\n\t]+/', ' ', $e->getMessage()), 0, 160);
        } elseif ($e->getCode() === 1062 && preg_match("/for key '([^']+)'/", $e->getMessage(), $match)) {
            $diagnostico['indice'] = $match[1];
        }
    }
    error_log('pagamento_competencia '.json_encode($diagnostico));
    pagamento_json(['success' => false,'code' => 'COMPETENCIA_STORAGE_ERROR','request_id' => $requestId,'error' => 'Não foi possível carregar ou registrar a competência. Confira a implantação e tente novamente.'], 503);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
