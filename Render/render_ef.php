<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/deadline_flow.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão expirada. Entre novamente.');
    }
    $actor = (int) ($_SESSION['idcolaborador'] ?? 0);
    $token = (string) ($_SESSION['render_ef_csrf'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if ($isPost && ($token === '' || !hash_equals($token, (string) ($_POST['csrf'] ?? '')))) {
        http_response_code(403);
        throw new RuntimeException('Atualize a página antes de solicitar o render.');
    }
    session_write_close();
    deadline_flow_require_schema($conn);
    $ready = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'render_ef_solicitacoes'");
    if (!$ready || !$ready->num_rows) {
        throw new RuntimeException('Render em alta ainda não está instalado. Aplique a migration 2026-10-20_render_ef.sql.');
    }
    $renderId = (int) ($_POST['render_id'] ?? $_GET['render_id'] ?? 0);
    $stmt = $conn->prepare("SELECT r.*, i.imagem_nome, i.status_id AS etapa_atual_id,
        si.nome_status AS etapa_atual
        FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
        JOIN status_imagem si ON si.idstatus = i.status_id WHERE r.idrender_alta = ?");
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $source = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$source) {
        throw new RuntimeException('Render de origem não encontrado.');
    }
    $imageId = (int) $source['imagem_id'];
    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? 'status');
    if ($isPost && strtoupper(trim((string) $source['etapa_atual'])) !== 'EF') {
        throw new RuntimeException('Render em alta está disponível somente para imagens que já estejam na etapa EF.');
    }
    if ($isPost && $actor <= 0) {
        throw new RuntimeException('Colaborador não identificado na sessão.');
    }
    $conn->begin_transaction();
    // Serializa cliques concorrentes para a mesma imagem, inclusive em cards diferentes.
    $stmt = $conn->prepare('SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? FOR UPDATE');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $lockedImage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($isPost && (int) $lockedImage['status_id'] !== (int) $source['etapa_atual_id']) {
        throw new RuntimeException('A etapa da imagem mudou. Atualize a página.');
    }
    $stmt = $conn->prepare('SELECT * FROM render_ef_solicitacoes WHERE imagem_id = ? FOR UPDATE');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($isPost && $action === 'prepare') {
        if (!$request || ($request['status'] === 'ERRO' && !$request['novo_job_id'] && !in_array($request['etapa'], ['SUBMETENDO', 'SUBMISSAO_INCERTA'], true))) {
            if (!empty($source['excluido_em'])) {
                throw new RuntimeException('Selecione um render de origem que não esteja arquivado.');
            }
            $stmt = $conn->prepare('SELECT deadline_job_id FROM render_tentativas WHERE render_id = ? AND deadline_job_id IS NOT NULL ORDER BY numero_tentativa DESC LIMIT 1');
            $stmt->bind_param('i', $renderId);
            $stmt->execute();
            $attempt = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $jobId = deadline_flow_valid_job_id($attempt['deadline_job_id'] ?? $source['deadline_job_id'] ?? null);
            if (!$jobId) {
                throw new RuntimeException('Este render não tem um Job Deadline registrado. Selecione o render anterior que contém o Job original.');
            }
            $stmt = $conn->prepare("INSERT INTO render_ef_solicitacoes
                (render_origem_id, imagem_id, criado_por, origem_job_id, mensagem)
                VALUES (?, ?, ?, ?, 'Aguardando o worker para inspecionar o Job original.')
                ON DUPLICATE KEY UPDATE render_origem_id = VALUES(render_origem_id), criado_por = VALUES(criado_por),
                origem_job_id = VALUES(origem_job_id), status = 'PENDENTE', etapa = 'FILA',
                mensagem = VALUES(mensagem), plano_json = NULL, worker_id = NULL");
            $stmt->bind_param('iiis', $renderId, $imageId, $actor, $jobId);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($isPost && $action === 'confirm') {
        if (!$request) {
            throw new RuntimeException('Inspecione o render antes de confirmar.');
        }
        if ((int) ($_POST['request_id'] ?? 0) !== (int) $request['id']) {
            throw new RuntimeException('Solicitação inválida.');
        }
        if ($request['status'] === 'PRONTO') {
            if (!hash_equals(hash('sha256', (string) $request['plano_json']), (string) ($_POST['plan_hash'] ?? ''))) {
                throw new RuntimeException('Os dados da inspeção mudaram. Confira novamente.');
            }
            $stmt = $conn->prepare("UPDATE render_ef_solicitacoes SET status = 'EXECUTAR', etapa = 'FILA', mensagem = 'Envio confirmado. Aguardando o worker.' WHERE id = ? AND status = 'PRONTO'");
            $stmt->bind_param('i', $request['id']);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($isPost && $action === 'resume') {
        if (!$request || !$request['novo_job_id'] || !in_array($request['status'], ['ERRO', 'ATENCAO'], true)) {
            throw new RuntimeException('Não há um Job EF identificado para retomar com segurança.');
        }
        $stmt = $conn->prepare("UPDATE render_ef_solicitacoes SET status = 'EXECUTAR', etapa = 'FILA', mensagem = 'Retomando a validação do mesmo Job EF, sem nova submissão.' WHERE id = ?");
        $stmt->bind_param('i', $request['id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($isPost || $action !== 'status') {
        throw new RuntimeException('Ação inválida.');
    }
    $stmt = $conn->prepare('SELECT * FROM render_ef_solicitacoes WHERE imagem_id = ?');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->commit();
    if ($request) {
        $request['plan_hash'] = hash('sha256', (string) $request['plano_json']);
        $plan = json_decode((string) $request['plano_json'], true) ?: [];
        $request['can_resume'] = !empty($request['novo_job_id']);
        // Caminhos auxiliares internos e snapshots completos ficam somente no servidor.
        $request['plan'] = array_intersect_key($plan, array_flip(['old_width', 'old_height', 'width', 'height', 'scene', 'destination', 'job_name', 'camera', 'frames', 'renderer', 'outputs', 'rounding']));
        unset($request['plano_json'], $request['worker_id']);
    }
    $worker = $conn->query("SELECT COUNT(*) AS online FROM deadline_workers WHERE versao = '2.1' AND status IN ('ATIVO', 'DRY_RUN') AND ultimo_heartbeat >= NOW() - INTERVAL 180 SECOND")->fetch_assoc();
    echo json_encode(['status' => 'sucesso', 'request' => $request, 'worker_online' => (int) $worker['online'] > 0], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }
    echo json_encode(['status' => 'erro', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
