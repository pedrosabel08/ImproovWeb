<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/fotografico_service.php';
require_once __DIR__ . '/ws_notify.php';

function foto_response(bool $success, $data = null, ?string $code = null, ?string $message = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $payload = ['success' => $success];
    if ($success) {
        $payload['data'] = $data;
    } else {
        $payload['error'] = ['code' => $code ?: 'ERRO', 'message' => $message ?: 'Erro inesperado'];
        if ($data !== null) {
            $payload['error']['details'] = $data;
        }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function foto_require_auth(): void
{
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        foto_response(false, null, 'NAO_AUTENTICADO', 'Sessao expirada.', 401);
    }
}

function foto_csrf_token(): string
{
    if (empty($_SESSION['fotografico_csrf'])) {
        $_SESSION['fotografico_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['fotografico_csrf'];
}

function foto_require_csrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($provided === '' || !hash_equals(foto_csrf_token(), $provided)) {
        foto_response(false, null, 'CSRF_INVALIDO', 'Atualize a pagina e tente novamente.', 419);
    }
}

function foto_payload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $payload = json_decode(file_get_contents('php://input'), true);
        return is_array($payload) ? $payload : [];
    }
    return $_POST;
}

function foto_manager(mysqli $conn): bool
{
    return improov_usuario_eh_gestor_sidebar($conn);
}

function foto_assert_obra_access(mysqli $conn, int $obraId): void
{
    if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
        foto_response(false, null, 'SEM_ACESSO', 'Sem acesso a esta obra.', 403);
    }
}

function foto_plan(mysqli $conn, int $planoId, bool $forUpdate = false): array
{
    $suffix = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $conn->prepare('SELECT * FROM fotografico_plano WHERE id = ?' . $suffix);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$plan) {
        foto_response(false, null, 'PLANO_NAO_ENCONTRADO', 'Plano fotografico nao encontrado.', 404);
    }
    foto_assert_obra_access($conn, (int) $plan['obra_id']);
    return $plan;
}

function foto_can_edit(mysqli $conn, array $plan): bool
{
    $actorId = fotografico_actor_id();
    return foto_manager($conn)
        || ($actorId !== null && (int) ($plan['responsavel_plano_id'] ?? 0) === $actorId);
}

function foto_can_execute(mysqli $conn, array $plan): bool
{
    $actorId = fotografico_actor_id();
    return foto_manager($conn)
        || ($actorId !== null && (int) ($plan['responsavel_execucao_id'] ?? 0) === $actorId);
}

function foto_assert_capability(bool $allowed, string $message): void
{
    if (!$allowed) {
        foto_response(false, null, 'SEM_PERMISSAO', $message, 403);
    }
}

function foto_assert_version(array $plan, array $payload): void
{
    $expected = isset($payload['version']) ? (int) $payload['version'] : 0;
    if ($expected <= 0 || $expected !== (int) $plan['lock_version']) {
        foto_response(false, null, 'VERSAO_DESATUALIZADA', 'O plano foi alterado por outro usuario. Recarregue os dados.', 409);
    }
}

function foto_fetch_all(mysqli_result $result): array
{
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function foto_get_detail(mysqli $conn, int $planoId): array
{
    $plan = foto_plan($conn, $planoId);
    $stmt = $conn->prepare(
        "SELECT p.*, o.nome_obra, o.nomenclatura, o.local, o.maps_url AS obra_maps_url,
                rp.nome_colaborador AS responsavel_plano_nome,
                re.nome_colaborador AS responsavel_execucao_nome,
                ig.imagem_nome AS imagem_gatilho_nome
           FROM fotografico_plano p
           JOIN obra o ON o.idobra = p.obra_id
      LEFT JOIN colaborador rp ON rp.idcolaborador = p.responsavel_plano_id
      LEFT JOIN colaborador re ON re.idcolaborador = p.responsavel_execucao_id
      LEFT JOIN imagens_cliente_obra ig ON ig.idimagens_cliente_obra = p.imagem_gatilho_id
          WHERE p.id = ?"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT v.*, a.caminho AS mapa_caminho, a.nome_original AS mapa_nome, a.mime AS mapa_mime
           FROM fotografico_plano_versao v
      LEFT JOIN fotografico_anexo a ON a.id = v.mapa_anexo_id AND a.arquivado_em IS NULL
          WHERE v.plano_id = ?
          ORDER BY CASE v.status WHEN 'RASCUNHO' THEN 0 WHEN 'PUBLICADA' THEN 1 ELSE 2 END, v.numero DESC"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $versions = foto_fetch_all($stmt->get_result());
    $stmt->close();
    $activeVersion = $versions[0] ?? null;
    $detail['versoes'] = $versions;
    $detail['versao_ativa'] = $activeVersion;
    $detail['imagens'] = [];
    $detail['posicoes'] = [];

    if ($activeVersion) {
        $versionId = (int) $activeVersion['id'];
        $stmt = $conn->prepare(
            "SELECT pi.*, i.imagem_nome, i.tipo_imagem, i.status_id, i.substatus_id,
                    i.subtipo_id, s.nome AS referencia_nome,
                    GROUP_CONCAT(DISTINCT per.nome ORDER BY per.ordem SEPARATOR ', ') AS periodos_vinculados,
                    GROUP_CONCAT(DISTINCT po.codigo ORDER BY po.ordem SEPARATOR ', ') AS posicoes_vinculadas,
                    MIN(c.prioridade) AS prioridade_vinculada
               FROM fotografico_plano_imagem pi
               JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = pi.imagem_id
          LEFT JOIN subtipo_imagem s ON s.id = i.subtipo_id
          LEFT JOIN fotografico_captura_imagem ci ON ci.plano_imagem_id = pi.id
          LEFT JOIN fotografico_captura c ON c.id = ci.captura_id
          LEFT JOIN fotografico_posicao po ON po.id = c.posicao_id
          LEFT JOIN fotografico_periodo per ON per.id = c.periodo_id
              WHERE pi.versao_id = ?
              GROUP BY pi.id ORDER BY pi.ordem, pi.id"
        );
        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        $detail['imagens'] = foto_fetch_all($stmt->get_result());
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM fotografico_posicao WHERE versao_id = ? ORDER BY ordem, id');
        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        $positions = foto_fetch_all($stmt->get_result());
        $stmt->close();
        foreach ($positions as &$position) {
            $positionId = (int) $position['id'];
            $stmt = $conn->prepare(
                "SELECT c.*, pe.codigo AS periodo_codigo, pe.nome AS periodo_nome
                   FROM fotografico_captura c
                   JOIN fotografico_periodo pe ON pe.id = c.periodo_id
                  WHERE c.posicao_id = ? ORDER BY c.prioridade, c.id"
            );
            $stmt->bind_param('i', $positionId);
            $stmt->execute();
            $captures = foto_fetch_all($stmt->get_result());
            $stmt->close();
            foreach ($captures as &$capture) {
                $captureId = (int) $capture['id'];
                $stmt = $conn->prepare(
                    'SELECT plano_imagem_id FROM fotografico_captura_imagem WHERE captura_id = ? ORDER BY plano_imagem_id'
                );
                $stmt->bind_param('i', $captureId);
                $stmt->execute();
                $capture['plano_imagem_ids'] = array_map(
                    'intval',
                    array_column(foto_fetch_all($stmt->get_result()), 'plano_imagem_id')
                );
                $stmt->close();
            }
            unset($capture);
            $position['capturas'] = $captures;
        }
        unset($position);
        $detail['posicoes'] = $positions;
    }

    foreach (
        [
            'sla' => 'SELECT * FROM fotografico_sla WHERE plano_id = ? ORDER BY id',
            'holds' => 'SELECT h.*, c.nome_colaborador AS responsavel_nome FROM fotografico_hold h LEFT JOIN colaborador c ON c.idcolaborador = h.responsavel_id WHERE h.plano_id = ? ORDER BY h.id DESC',
            'pendencias' => 'SELECT pe.*, c.nome_colaborador AS responsavel_nome FROM fotografico_pendencia pe LEFT JOIN colaborador c ON c.idcolaborador = pe.responsavel_id WHERE pe.plano_id = ? ORDER BY pe.id DESC',
            'execucoes' => "SELECT e.*, c.nome_colaborador AS responsavel_nome, ep.nome_colaborador AS enviado_por_nome,
                             ec.decisao AS decisao_conferencia, ec.consideracao, ec.conferido_em AS conferencia_em,
                             cc.nome_colaborador AS conferente_nome
                        FROM fotografico_execucao e
                   LEFT JOIN colaborador c ON c.idcolaborador = e.responsavel_id
                   LEFT JOIN colaborador ep ON ep.idcolaborador = e.enviado_por
                   LEFT JOIN fotografico_execucao_conferencia ec ON ec.id = (
                        SELECT ec2.id FROM fotografico_execucao_conferencia ec2
                         WHERE ec2.execucao_id = e.id ORDER BY ec2.id DESC LIMIT 1
                   )
                   LEFT JOIN colaborador cc ON cc.idcolaborador = ec.conferido_por
                       WHERE e.plano_id = ? ORDER BY e.tentativa DESC",
            'anexos' => 'SELECT * FROM fotografico_anexo WHERE plano_id = ? AND arquivado_em IS NULL ORDER BY id DESC',
            'eventos' => 'SELECT e.*, c.nome_colaborador AS ator_nome FROM fotografico_evento e LEFT JOIN colaborador c ON c.idcolaborador = e.ator_id WHERE e.plano_id = ? ORDER BY e.id DESC LIMIT 100'
        ] as $key => $sql
    ) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $planoId);
        $stmt->execute();
        $detail[$key] = foto_fetch_all($stmt->get_result());
        $stmt->close();
    }

    foreach ($detail['execucoes'] as &$execution) {
        $executionId = (int) $execution['id'];
        $stmt = $conn->prepare('SELECT * FROM fotografico_anexo WHERE entidade_tipo = \'EXECUCAO\' AND entidade_id = ? AND arquivado_em IS NULL ORDER BY id DESC');
        $stmt->bind_param('i', $executionId);
        $stmt->execute();
        $execution['anexos'] = foto_fetch_all($stmt->get_result());
        $stmt->close();
    }
    unset($execution);

    $detail['resumo_execucao'] = [
        'tentativas' => count($detail['execucoes']),
        'ultima_tentativa' => $detail['execucoes'][0] ?? null,
    ];

    // Diagnóstico sempre calculado no servidor: a interface nunca deduz prontidão sozinha.
    $detail['prontidao'] = foto_verificar_prontidao_execucao($conn, $planoId);

    $detail['permissions'] = [
        'manage' => foto_manager($conn),
        'edit' => foto_can_edit($conn, $plan),
        'execute' => foto_can_execute($conn, $plan),
        'review' => foto_can_edit($conn, $plan),
    ];
    $detail['csrf_token'] = foto_csrf_token();
    return $detail;
}

function foto_period_map(mysqli $conn): array
{
    $result = $conn->query('SELECT id, codigo FROM fotografico_periodo WHERE ativo = 1');
    $map = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $map[(string) $row['codigo']] = (int) $row['id'];
    }
    return $map;
}

/** Fonte única da condição que permite iniciar a execução. */
function foto_verificar_prontidao_execucao(mysqli $conn, int $planoId): array
{
    $blocks = [];
    // A consulta de diagnóstico também é usada em GETs. O lock é obtido
    // somente pela rotina que efetivamente vai alterar o estado.
    $plan = foto_plan($conn, $planoId);
    $stmt = $conn->prepare("SELECT id, status, mapa_anexo_id FROM fotografico_plano_versao WHERE plano_id = ? AND status = 'RASCUNHO' ORDER BY numero DESC LIMIT 1");
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $version = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$version) {
        $stmt = $conn->prepare("SELECT id, status, mapa_anexo_id FROM fotografico_plano_versao WHERE plano_id = ? AND status = 'PUBLICADA' ORDER BY numero DESC LIMIT 1");
        $stmt->bind_param('i', $planoId);
        $stmt->execute();
        $version = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$version) {
        return ['pronto' => false, 'bloqueios' => [['codigo' => 'VERSAO_AUSENTE', 'mensagem' => 'O plano não possui uma versão para validar.']]];
    }
    $versionId = (int) $version['id'];
    if ((int) ($version['mapa_anexo_id'] ?? 0) <= 0) {
        $blocks[] = ['codigo' => 'MAPA_OBRIGATORIO', 'mensagem' => 'Envie a imagem do mapa fotográfico.'];
    }
    if ((int) ($plan['responsavel_execucao_id'] ?? 0) <= 0) {
        $blocks[] = ['codigo' => 'EXECUTOR_OBRIGATORIO', 'mensagem' => 'Informe o responsável pela execução.'];
    }
    if (empty($plan['data_planejada'])) {
        $blocks[] = ['codigo' => 'DATA_PLANEJADA_OBRIGATORIA', 'mensagem' => 'Informe a data planejada da execução.'];
    }
    $stmt = $conn->prepare("SELECT id, codigo, COALESCE(observacao, '') AS observacao FROM fotografico_posicao WHERE versao_id = ? ORDER BY ordem, id");
    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $positions = foto_fetch_all($stmt->get_result());
    $stmt->close();
    if (!$positions) {
        $blocks[] = ['codigo' => 'POSICAO_AUSENTE', 'mensagem' => 'Crie ao menos um ponto no mapa.'];
    }
    foreach ($positions as $position) {
        if (trim((string) $position['observacao']) === '') {
            $blocks[] = ['codigo' => 'PONTO_SEM_DESCRICAO', 'entidade_id' => (int) $position['id'], 'mensagem' => 'O ponto ' . $position['codigo'] . ' não possui descrição.'];
        }
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM fotografico_captura WHERE posicao_id = ?');
        $positionId = (int) $position['id'];
        $stmt->bind_param('i', $positionId);
        $stmt->execute();
        $captureCount = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($captureCount === 0) {
            $blocks[] = ['codigo' => 'PONTO_SEM_CAPTURA', 'entidade_id' => $positionId, 'mensagem' => 'O ponto ' . $position['codigo'] . ' não possui nenhum período/captura definido.'];
        }
    }
    $stmt = $conn->prepare("SELECT pi.id, i.imagem_nome, pi.decisao, pi.motivo_exclusao,
        EXISTS(SELECT 1 FROM fotografico_captura_imagem ci WHERE ci.plano_imagem_id = pi.id) AS vinculada
        FROM fotografico_plano_imagem pi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = pi.imagem_id WHERE pi.versao_id = ? ORDER BY pi.ordem, pi.id");
    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $images = foto_fetch_all($stmt->get_result());
    $stmt->close();
    $included = 0;
    foreach ($images as $image) {
        if ($image['decisao'] === 'PENDENTE') {
            $blocks[] = ['codigo' => 'IMAGEM_SEM_DECISAO', 'entidade_id' => (int) $image['id'], 'mensagem' => 'A imagem “' . $image['imagem_nome'] . '” ainda não possui decisão.'];
        }
        if (in_array($image['decisao'], ['EXCLUIDA', 'REMOVIDA'], true) && trim((string) $image['motivo_exclusao']) === '') {
            $blocks[] = ['codigo' => 'EXCLUSAO_SEM_MOTIVO', 'entidade_id' => (int) $image['id'], 'mensagem' => 'Informe o motivo da exclusão da imagem “' . $image['imagem_nome'] . '”.'];
        }
        if ($image['decisao'] === 'INCLUIDA') {
            $included++;
            if (!(int) $image['vinculada']) {
                $blocks[] = ['codigo' => 'IMAGEM_SEM_VINCULO', 'entidade_id' => (int) $image['id'], 'mensagem' => 'A imagem “' . $image['imagem_nome'] . '” não está vinculada a um ponto/período.'];
            }
        }
        if (in_array($image['decisao'], ['EXCLUIDA', 'REMOVIDA'], true) && (int) $image['vinculada']) {
            $blocks[] = ['codigo' => 'IMAGEM_EXCLUIDA_VINCULADA', 'entidade_id' => (int) $image['id'], 'mensagem' => 'Remova os vínculos da imagem excluída “' . $image['imagem_nome'] . '”.'];
        }
    }
    if ($included === 0) {
        $blocks[] = ['codigo' => 'IMAGEM_CONFIRMADA_AUSENTE', 'mensagem' => 'Confirme ao menos uma imagem contratada.'];
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM fotografico_pendencia WHERE plano_id = ? AND status = 'ABERTA' AND codigo IN ('COMPLEMENTO_MATERIAL','IMAGEM_NOVA')");
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $pending = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($pending > 0) {
        $blocks[] = ['codigo' => 'PENDENCIA_BLOQUEANTE', 'mensagem' => 'Existem pendências bloqueantes abertas para o plano.'];
    }
    return ['pronto' => $blocks === [], 'status_atual' => $plan['status'], 'status_esperado' => 'PRONTO_EXECUCAO', 'bloqueios' => $blocks, 'versao_id' => $versionId];
}

function foto_sincronizar_prontidao_execucao(mysqli $conn, int $planoId, ?int $actorId, string $origin): array
{
    $readiness = foto_verificar_prontidao_execucao($conn, $planoId);
    $plan = foto_plan($conn, $planoId, true);
    if (in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
        $next = $readiness['pronto'] ? 'PRONTO_EXECUCAO' : 'EM_ELABORACAO';
        if ($next !== $plan['status']) {
            $stmt = $conn->prepare('UPDATE fotografico_plano SET status = ?, iniciado_em = COALESCE(iniciado_em, NOW()), lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('si', $next, $planoId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planoId, 'PRONTIDAO_ATUALIZADA', $plan['status'], $next, $actorId, $origin, ['bloqueios' => $readiness['bloqueios']]);
        }
    }
    return $readiness;
}

function foto_create_manual_campaign(mysqli $conn, int $obraId, ?int $actorId): int
{
    foto_assert_obra_access($conn, $obraId);
    foto_assert_capability(foto_manager($conn), 'Somente gestor ou administrador pode criar outra campanha.');
    $stmt = $conn->prepare("SELECT 1 FROM fotografico_plano WHERE obra_id = ? AND status NOT IN ('CONCLUIDO','CANCELADO') LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $active = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($active) {
        foto_response(false, null, 'CAMPANHA_ATIVA', 'Conclua ou cancele a campanha atual antes de criar outra.', 422);
    }

    $stmt = $conn->prepare('SELECT COALESCE(MAX(campanha_numero), 0) + 1 AS numero FROM fotografico_plano WHERE obra_id = ?');
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $number = (int) $stmt->get_result()->fetch_assoc()['numero'];
    $stmt->close();
    $plannerId = fotografico_colaborador_ativo($conn, FOTOGRAFICO_RESPONSAVEL_PLANO_ID)
        ? FOTOGRAFICO_RESPONSAVEL_PLANO_ID
        : null;
    $stmt = $conn->prepare(
        "INSERT INTO fotografico_plano
            (obra_id, campanha_numero, origem, status, responsavel_plano_id, criado_por)
         VALUES (?, ?, 'MANUAL', 'PLANO_A_FAZER', ?, ?)"
    );
    $stmt->bind_param('iiii', $obraId, $number, $plannerId, $actorId);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $planId = (int) $conn->insert_id;
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO fotografico_plano_versao (plano_id, numero, status, motivo, criado_por) VALUES (?, 1, 'RASCUNHO', 'Nova campanha manual', ?)");
    $stmt->bind_param('ii', $planId, $actorId);
    $stmt->execute();
    $versionId = (int) $conn->insert_id;
    $stmt->close();
    $stmt = $conn->prepare(
        "INSERT INTO fotografico_plano_imagem (versao_id, imagem_id, decisao, pavimento_referencia, ordem)
         SELECT ?, i.idimagens_cliente_obra, 'PENDENTE', s.nome,
                ROW_NUMBER() OVER (ORDER BY i.idimagens_cliente_obra)
           FROM imagens_cliente_obra i
      LEFT JOIN subtipo_imagem s ON s.id = i.subtipo_id
          WHERE i.obra_id = ? AND LOWER(TRIM(COALESCE(i.tipo_imagem, ''))) <> 'planta humanizada'"
    );
    $stmt->bind_param('ii', $versionId, $obraId);
    $stmt->execute();
    $stmt->close();
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $due = fotografico_next_business_due($conn, $now);
    $nowSql = $now->format('Y-m-d H:i:s');
    $dueSql = $due->format('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO fotografico_sla (plano_id, tipo, started_at, due_at_original, due_at_effective) VALUES (?, 'CRIACAO', ?, ?, ?)");
    $stmt->bind_param('isss', $planId, $nowSql, $dueSql, $dueSql);
    $stmt->execute();
    $stmt->close();
    fotografico_evento($conn, $planId, 'CAMPANHA_CRIADA', null, 'PLANO_A_FAZER', $actorId, 'Fotografico/api.php');
    return $planId;
}

foto_require_auth();
if (!fotografico_schema_ready($conn)) {
    foto_response(false, null, 'MIGRATION_PENDENTE', 'A migration do modulo Fotografico ainda nao foi aplicada.', 503);
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'list'));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET' && $action === 'list') {
        $result = $conn->query(
            "SELECT p.id, p.obra_id, p.campanha_numero, p.status, p.data_planejada,
                    p.lock_version, p.updated_at, o.nome_obra, o.nomenclatura,
                    rp.nome_colaborador AS responsavel_plano_nome,
                    re.nome_colaborador AS responsavel_execucao_nome,
                    MIN(CASE WHEN s.completed_at IS NULL THEN s.due_at_effective END) AS proximo_prazo,
                    SUM(CASE WHEN pe.status = 'ABERTA' THEN 1 ELSE 0 END) AS pendencias_abertas
               FROM fotografico_plano p
               JOIN obra o ON o.idobra = p.obra_id
          LEFT JOIN colaborador rp ON rp.idcolaborador = p.responsavel_plano_id
          LEFT JOIN colaborador re ON re.idcolaborador = p.responsavel_execucao_id
          LEFT JOIN fotografico_sla s ON s.plano_id = p.id
          LEFT JOIN fotografico_pendencia pe ON pe.plano_id = p.id
              GROUP BY p.id
              ORDER BY FIELD(p.status, 'HOLD','PLANO_A_FAZER','EM_ELABORACAO','PRONTO_EXECUCAO','EM_CONFERENCIA','CONCLUIDO','CANCELADO'), proximo_prazo, p.id DESC"
        );
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            if (improov_usuario_pode_acessar_obra($conn, (int) $row['obra_id'])) {
                $rows[] = $row;
            }
        }
        foto_response(true, ['planos' => $rows, 'csrf_token' => foto_csrf_token(), 'can_manage' => foto_manager($conn)]);
    }

    if ($method === 'GET' && $action === 'get') {
        foto_response(true, foto_get_detail($conn, (int) ($_GET['plano_id'] ?? 0)));
    }

    if ($method === 'GET' && $action === 'summary') {
        $obraId = (int) ($_GET['obra_id'] ?? 0);
        foto_assert_obra_access($conn, $obraId);
        $stmt = $conn->prepare("SELECT id FROM fotografico_plano WHERE obra_id = ? ORDER BY campanha_numero DESC, id DESC LIMIT 1");
        $stmt->bind_param('i', $obraId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        foto_response(true, $row ? foto_get_detail($conn, (int) $row['id']) : ['obra_id' => $obraId, 'status' => 'AGUARDANDO_FACHADA', 'csrf_token' => foto_csrf_token()]);
    }

    if ($method === 'GET' && $action === 'collaborators') {
        $result = $conn->query('SELECT idcolaborador AS id, nome_colaborador AS nome FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador');
        foto_response(true, ['colaboradores' => $result ? foto_fetch_all($result) : []]);
    }

    if ($method !== 'POST') {
        foto_response(false, null, 'METODO_INVALIDO', 'Metodo nao permitido.', 405);
    }
    foto_require_csrf();
    $payload = foto_payload();
    $actorId = fotografico_actor_id();

    if ($action === 'create_campaign') {
        $conn->begin_transaction();
        try {
            $planId = foto_create_manual_campaign($conn, (int) ($payload['obra_id'] ?? 0), $actorId);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        fotografico_notify_update('campaign.created', ['plan_id' => $planId, 'client_event_id' => (string) ($payload['client_event_id'] ?? '')]);
        foto_response(true, foto_get_detail($conn, $planId));
    }

    $planId = (int) ($payload['plano_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $plan = foto_plan($conn, $planId, true);
        foto_assert_version($plan, $payload);

        if ($action === 'start') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para iniciar este plano.');
            if ($plan['status'] !== 'PLANO_A_FAZER') {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'O plano nao esta aguardando inicio.', 422);
            }
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status = 'EM_ELABORACAO', iniciado_em = NOW(), lock_version = lock_version + 1 WHERE id = ?");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'ELABORACAO_INICIADA', 'PLANO_A_FAZER', 'EM_ELABORACAO', $actorId, 'Fotografico/api.php');
        } elseif ($action === 'plan_update') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para alterar este plano.');
            if (!in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'Crie uma revisao antes de alterar um plano publicado.', 422);
            }
            $executorId = array_key_exists('responsavel_execucao_id', $payload) ? (int) $payload['responsavel_execucao_id'] : (int) ($plan['responsavel_execucao_id'] ?? 0);
            $executor = $executorId > 0 && fotografico_colaborador_ativo($conn, $executorId) ? $executorId : null;
            $planned = array_key_exists('data_planejada', $payload) ? (trim((string) $payload['data_planejada']) ?: null) : $plan['data_planejada'];
            $stmt = $conn->prepare('UPDATE fotografico_plano SET responsavel_execucao_id = ?, data_planejada = ?, lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('isi', $executor, $planned, $planId);
            $stmt->execute();
            $stmt->close();
            if (array_key_exists('endereco', $payload) || array_key_exists('maps_url', $payload)) {
                $address = array_key_exists('endereco', $payload) ? (trim((string) $payload['endereco']) ?: null) : null;
                $mapsUrl = array_key_exists('maps_url', $payload) ? (trim((string) $payload['maps_url']) ?: null) : null;
                $stmt = $conn->prepare('UPDATE obra SET local = COALESCE(?, local), maps_url = COALESCE(?, maps_url) WHERE idobra = ?');
                $stmt->bind_param('ssi', $address, $mapsUrl, $plan['obra_id']);
                $stmt->execute();
                $stmt->close();
            }
            fotografico_evento($conn, $planId, 'METADADOS_ATUALIZADOS', null, null, $actorId, 'Fotografico/api.php');
            foto_sincronizar_prontidao_execucao($conn, $planId, $actorId, 'Fotografico/api.php');
        } elseif ($action === 'image_update') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para alterar imagens.');
            if (!in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'Crie uma revisao antes de alterar um plano publicado.', 422);
            }
            $versionId = (int) ($payload['versao_id'] ?? 0);
            $imageItemId = (int) ($payload['imagem_plano_id'] ?? 0);
            $decision = strtoupper(trim((string) ($payload['decisao'] ?? 'PENDENTE')));
            if (!in_array($decision, ['PENDENTE', 'INCLUIDA', 'EXCLUIDA', 'REMOVIDA'], true)) {
                foto_response(false, null, 'DECISAO_INVALIDA', 'Decisao de imagem invalida.', 422);
            }
            $reason = trim((string) ($payload['motivo_exclusao'] ?? '')) ?: null;
            if (in_array($decision, ['EXCLUIDA', 'REMOVIDA'], true) && $reason === null) {
                foto_response(false, null, 'MOTIVO_OBRIGATORIO', 'Informe o motivo para excluir a imagem.', 422);
            }
            $stmt = $conn->prepare("SELECT id FROM fotografico_plano_imagem WHERE id = ? AND versao_id = ? AND EXISTS(SELECT 1 FROM fotografico_plano_versao WHERE id = ? AND plano_id = ? AND status = 'RASCUNHO') FOR UPDATE");
            $stmt->bind_param('iiii', $imageItemId, $versionId, $versionId, $planId);
            $stmt->execute();
            $exists = (bool) $stmt->get_result()->fetch_row();
            $stmt->close();
            if (!$exists) {
                foto_response(false, null, 'IMAGEM_INVALIDA', 'Imagem do rascunho não encontrada.', 422);
            }
            $stmt = $conn->prepare('UPDATE fotografico_plano_imagem SET decisao = ?, motivo_exclusao = ? WHERE id = ?');
            $stmt->bind_param('ssi', $decision, $reason, $imageItemId);
            $stmt->execute();
            $stmt->close();
            if (in_array($decision, ['EXCLUIDA', 'REMOVIDA'], true)) {
                $stmt = $conn->prepare('DELETE ci FROM fotografico_captura_imagem ci JOIN fotografico_captura c ON c.id = ci.captura_id JOIN fotografico_posicao p ON p.id = c.posicao_id WHERE ci.plano_imagem_id = ? AND p.versao_id = ?');
                $stmt->bind_param('ii', $imageItemId, $versionId);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $conn->prepare('UPDATE fotografico_plano SET lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'DECISAO_IMAGEM_ATUALIZADA', null, null, $actorId, 'Fotografico/api.php', ['plano_imagem_id' => $imageItemId, 'decisao' => $decision]);
            foto_sincronizar_prontidao_execucao($conn, $planId, $actorId, 'Fotografico/api.php');
        } elseif ($action === 'save_draft') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para editar este plano.');
            if (!in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'Crie uma revisao antes de alterar um plano publicado.', 422);
            }
            $versionId = (int) ($payload['versao_id'] ?? 0);
            $stmt = $conn->prepare("SELECT id FROM fotografico_plano_versao WHERE id = ? AND plano_id = ? AND status = 'RASCUNHO' FOR UPDATE");
            $stmt->bind_param('ii', $versionId, $planId);
            $stmt->execute();
            $validVersion = (bool) $stmt->get_result()->fetch_row();
            $stmt->close();
            if (!$validVersion) {
                foto_response(false, null, 'VERSAO_INVALIDA', 'Rascunho nao encontrado.', 422);
            }

            $executorId = (int) ($payload['responsavel_execucao_id'] ?? 0);
            $executor = $executorId > 0 && fotografico_colaborador_ativo($conn, $executorId) ? $executorId : null;
            $plannedDate = trim((string) ($payload['data_planejada'] ?? '')) ?: null;
            $stmt = $conn->prepare('UPDATE fotografico_plano SET responsavel_execucao_id = ?, data_planejada = ?, status = \'EM_ELABORACAO\', iniciado_em = COALESCE(iniciado_em, NOW()), lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('isi', $executor, $plannedDate, $planId);
            $stmt->execute();
            $stmt->close();

            $address = trim((string) ($payload['endereco'] ?? '')) ?: null;
            $mapsUrl = trim((string) ($payload['maps_url'] ?? '')) ?: null;
            $stmt = $conn->prepare('UPDATE obra SET local = ?, maps_url = ? WHERE idobra = ?');
            $stmt->bind_param('ssi', $address, $mapsUrl, $plan['obra_id']);
            $stmt->execute();
            $stmt->close();

            $items = is_array($payload['imagens'] ?? null) ? $payload['imagens'] : [];
            $stmtImage = $conn->prepare(
                'UPDATE fotografico_plano_imagem SET decisao = ?, motivo_exclusao = ?, pavimento_referencia = ?, observacao_tecnica = ?, ordem = ? WHERE id = ? AND versao_id = ?'
            );
            foreach ($items as $index => $item) {
                $decision = strtoupper((string) ($item['decisao'] ?? 'PENDENTE'));
                if (!in_array($decision, ['PENDENTE', 'INCLUIDA', 'EXCLUIDA', 'REMOVIDA'], true)) {
                    $decision = 'PENDENTE';
                }
                $reason = trim((string) ($item['motivo_exclusao'] ?? '')) ?: null;
                if (in_array($decision, ['EXCLUIDA', 'REMOVIDA'], true) && $reason === null) {
                    foto_response(false, null, 'MOTIVO_OBRIGATORIO', 'Informe o motivo para excluir uma imagem.', 422);
                }
                $floor = trim((string) ($item['pavimento_referencia'] ?? '')) ?: null;
                $note = trim((string) ($item['observacao_tecnica'] ?? '')) ?: null;
                $itemId = (int) ($item['id'] ?? 0);
                $order = $index + 1;
                $stmtImage->bind_param('ssssiii', $decision, $reason, $floor, $note, $order, $itemId, $versionId);
                $stmtImage->execute();
            }
            $stmtImage->close();

            $conn->query('DELETE FROM fotografico_posicao WHERE versao_id = ' . $versionId);
            $periods = foto_period_map($conn);
            $positions = is_array($payload['posicoes'] ?? null) ? $payload['posicoes'] : [];
            $stmtPosition = $conn->prepare(
                'INSERT INTO fotografico_posicao (versao_id, codigo, x_percentual, y_percentual, direcao_graus, altura_padrao_m, pavimento_referencia, observacao, anotacao_json, criado_por, atualizado_por, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtCapture = $conn->prepare(
                'INSERT INTO fotografico_captura (posicao_id, periodo_id, prioridade, altura_efetiva_m, observacao) VALUES (?, ?, ?, ?, ?)'
            );
            $stmtLink = $conn->prepare('INSERT INTO fotografico_captura_imagem (captura_id, plano_imagem_id) VALUES (?, ?)');
            foreach ($positions as $positionIndex => $position) {
                $code = trim((string) ($position['codigo'] ?? ''));
                if ($code === '') {
                    foto_response(false, null, 'POSICAO_INVALIDA', 'Toda posicao precisa de um codigo.', 422);
                }
                $x = max(0, min(100, (float) ($position['x_percentual'] ?? ($position['anotacao']['x'] ?? 50))));
                $y = max(0, min(100, (float) ($position['y_percentual'] ?? ($position['anotacao']['y'] ?? 50))));
                $direction = ($position['direcao_graus'] ?? '') !== '' ? (float) $position['direcao_graus'] : null;
                $height = ($position['altura_padrao_m'] ?? '') !== '' ? (float) $position['altura_padrao_m'] : null;
                $floor = trim((string) ($position['pavimento_referencia'] ?? '')) ?: null;
                $note = trim((string) ($position['observacao'] ?? '')) ?: null;
                $annotation = is_array($position['anotacao'] ?? null) ? fotografico_json_encode($position['anotacao']) : null;
                $order = $positionIndex + 1;
                $stmtPosition->bind_param('isddddsssiii', $versionId, $code, $x, $y, $direction, $height, $floor, $note, $annotation, $actorId, $actorId, $order);
                $stmtPosition->execute();
                $positionId = (int) $conn->insert_id;
                foreach ((array) ($position['capturas'] ?? []) as $capture) {
                    $periodCode = strtoupper((string) ($capture['periodo_codigo'] ?? ''));
                    if (!isset($periods[$periodCode])) {
                        foto_response(false, null, 'PERIODO_INVALIDO', 'Periodo fotografico invalido.', 422);
                    }
                    $periodId = $periods[$periodCode];
                    $priority = max(1, (int) ($capture['prioridade'] ?? 1));
                    $captureHeight = ($capture['altura_efetiva_m'] ?? '') !== '' ? (float) $capture['altura_efetiva_m'] : null;
                    $captureNote = trim((string) ($capture['observacao'] ?? '')) ?: null;
                    $stmtCapture->bind_param('iiids', $positionId, $periodId, $priority, $captureHeight, $captureNote);
                    $stmtCapture->execute();
                    $captureId = (int) $conn->insert_id;
                    foreach (array_unique(array_map('intval', (array) ($capture['plano_imagem_ids'] ?? []))) as $itemId) {
                        if ($itemId <= 0) {
                            continue;
                        }
                        $stmtLink->bind_param('ii', $captureId, $itemId);
                        $stmtLink->execute();
                    }
                }
            }
            $stmtPosition->close();
            $stmtCapture->close();
            $stmtLink->close();
            fotografico_evento($conn, $planId, 'RASCUNHO_SALVO', $plan['status'], 'EM_ELABORACAO', $actorId, 'Fotografico/api.php', ['versao_id' => $versionId]);
            foto_sincronizar_prontidao_execucao($conn, $planId, $actorId, 'Fotografico/api.php');
        } elseif (in_array($action, ['pin_create', 'pin_update', 'pin_delete'], true)) {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para alterar pins.');
            if (!in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'Crie uma revisao antes de alterar o mapa publicado.', 422);
            }
            $versionId = (int) ($payload['versao_id'] ?? 0);
            $stmt = $conn->prepare("SELECT mapa_anexo_id FROM fotografico_plano_versao WHERE id = ? AND plano_id = ? AND status = 'RASCUNHO' FOR UPDATE");
            $stmt->bind_param('ii', $versionId, $planId);
            $stmt->execute();
            $version = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$version || (int) ($version['mapa_anexo_id'] ?? 0) <= 0) {
                foto_response(false, null, 'MAPA_OBRIGATORIO', 'Envie a imagem do mapa antes de criar pins.', 422);
            }
            $pinId = (int) ($payload['pin_id'] ?? 0);
            if ($action === 'pin_delete') {
                $stmt = $conn->prepare('SELECT id FROM fotografico_posicao WHERE id = ? AND versao_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $pinId, $versionId);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_row()) {
                    foto_response(false, null, 'PIN_INVALIDO', 'Pin nao encontrado.', 422);
                }
                $stmt->close();
                $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM fotografico_captura c JOIN fotografico_captura_imagem ci ON ci.captura_id = c.id WHERE c.posicao_id = ?');
                $stmt->bind_param('i', $pinId);
                $stmt->execute();
                $linked = (int) $stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                if ($linked > 0 && empty($payload['confirmar_exclusao'])) {
                    foto_response(false, ['vinculos' => $linked], 'CONFIRMAR_EXCLUSAO', 'Este pin possui imagens vinculadas. Confirme a exclusao.', 409);
                }
                $stmt = $conn->prepare('DELETE FROM fotografico_posicao WHERE id = ?');
                $stmt->bind_param('i', $pinId);
                $stmt->execute();
                $stmt->close();
                fotografico_evento($conn, $planId, 'PIN_EXCLUIDO', null, null, $actorId, 'Fotografico/api.php', ['pin_id' => $pinId]);
            } else {
                $x = max(0, min(100, (float) ($payload['x_percentual'] ?? 50)));
                $y = max(0, min(100, (float) ($payload['y_percentual'] ?? 50)));
                $note = trim((string) ($payload['observacao'] ?? '')) ?: null;
                if ($action === 'pin_create') {
                    $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 2) AS UNSIGNED)), 0) + 1 AS numero FROM fotografico_posicao WHERE versao_id = " . $versionId);
                    $number = (int) ($result->fetch_assoc()['numero'] ?? 1);
                    $code = 'P' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                    $stmt = $conn->prepare('INSERT INTO fotografico_posicao (versao_id, codigo, x_percentual, y_percentual, observacao, criado_por, atualizado_por, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isddsiii', $versionId, $code, $x, $y, $note, $actorId, $actorId, $number);
                    $stmt->execute();
                    $pinId = (int) $conn->insert_id;
                    $stmt->close();
                    fotografico_evento($conn, $planId, 'PIN_CRIADO', null, null, $actorId, 'Fotografico/api.php', ['pin_id' => $pinId, 'codigo' => $code]);
                } else {
                    $stmt = $conn->prepare('SELECT x_percentual, y_percentual FROM fotografico_posicao WHERE id = ? AND versao_id = ? FOR UPDATE');
                    $stmt->bind_param('ii', $pinId, $versionId);
                    $stmt->execute();
                    $before = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$before) {
                        foto_response(false, null, 'PIN_INVALIDO', 'Pin nao encontrado.', 422);
                    }
                    $stmt = $conn->prepare('UPDATE fotografico_posicao SET x_percentual = ?, y_percentual = ?, observacao = ?, atualizado_por = ? WHERE id = ?');
                    $stmt->bind_param('ddsii', $x, $y, $note, $actorId, $pinId);
                    $stmt->execute();
                    $stmt->close();
                    $event = ((float) $before['x_percentual'] !== $x || (float) $before['y_percentual'] !== $y) ? 'PIN_MOVIDO' : 'PIN_ATUALIZADO';
                    fotografico_evento($conn, $planId, $event, null, null, $actorId, 'Fotografico/api.php', ['pin_id' => $pinId, 'x' => $x, 'y' => $y]);
                }
            }
            if ($action === 'pin_update' && array_key_exists('capturas', $payload)) {
                $periods = foto_period_map($conn);
                $captures = is_array($payload['capturas']) ? $payload['capturas'] : [];
                $stmt = $conn->prepare('DELETE FROM fotografico_captura WHERE posicao_id = ?');
                $stmt->bind_param('i', $pinId);
                $stmt->execute();
                $stmt->close();
                $stmtCapture = $conn->prepare('INSERT INTO fotografico_captura (posicao_id, periodo_id, prioridade, observacao) VALUES (?, ?, ?, ?)');
                $stmtLink = $conn->prepare('INSERT INTO fotografico_captura_imagem (captura_id, plano_imagem_id) SELECT ?, id FROM fotografico_plano_imagem WHERE id = ? AND versao_id = ? AND decisao = \'INCLUIDA\'');
                foreach ($captures as $capture) {
                    $periodCode = strtoupper(trim((string) ($capture['periodo_codigo'] ?? '')));
                    if (!isset($periods[$periodCode])) {
                        foto_response(false, null, 'PERIODO_INVALIDO', 'Periodo fotografico invalido.', 422);
                    }
                    $priority = max(1, (int) ($capture['prioridade'] ?? 1));
                    $note = trim((string) ($capture['observacao'] ?? '')) ?: null;
                    $periodId = $periods[$periodCode];
                    $stmtCapture->bind_param('iiis', $pinId, $periodId, $priority, $note);
                    $stmtCapture->execute();
                    $captureId = (int) $conn->insert_id;
                    foreach (array_unique(array_map('intval', (array) ($capture['plano_imagem_ids'] ?? []))) as $imageId) {
                        if ($imageId > 0) {
                            $stmtLink->bind_param('iii', $captureId, $imageId, $versionId);
                            $stmtLink->execute();
                        }
                    }
                }
                $stmtCapture->close();
                $stmtLink->close();
                fotografico_evento($conn, $planId, 'CAPTURAS_ATUALIZADAS', null, null, $actorId, 'Fotografico/api.php', ['pin_id' => $pinId]);
            }
            $stmt = $conn->prepare('UPDATE fotografico_plano SET iniciado_em = COALESCE(iniciado_em, NOW()), lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            foto_sincronizar_prontidao_execucao($conn, $planId, $actorId, 'Fotografico/api.php');
        } elseif ($action === 'publish') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para publicar este plano.');
            if (!in_array($plan['status'], ['PLANO_A_FAZER', 'EM_ELABORACAO', 'PRONTO_EXECUCAO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'O plano nao esta em elaboracao.', 422);
            }
            $versionId = (int) ($payload['versao_id'] ?? 0);
            $stmt = $conn->prepare("SELECT id FROM fotografico_plano_versao WHERE id = ? AND plano_id = ? AND status = 'RASCUNHO' FOR UPDATE");
            $stmt->bind_param('ii', $versionId, $planId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_row()) {
                foto_response(false, null, 'VERSAO_INVALIDA', 'Rascunho nao encontrado.', 422);
            }
            $stmt->close();
            $readiness = foto_verificar_prontidao_execucao($conn, $planId);
            if ((int) ($readiness['versao_id'] ?? 0) !== $versionId || !$readiness['pronto']) {
                foto_response(false, ['bloqueios' => $readiness['bloqueios'] ?? []], 'PLANO_INCOMPLETO', 'Resolva os bloqueios de prontidao antes de publicar o plano.', 422);
            }
            $stmt = $conn->prepare('SELECT local, maps_url FROM obra WHERE idobra = ?');
            $stmt->bind_param('i', $plan['obra_id']);
            $stmt->execute();
            $location = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $conn->query("UPDATE fotografico_plano_versao SET status = 'SUBSTITUIDA' WHERE plano_id = $planId AND status = 'PUBLICADA'");
            $stmt = $conn->prepare("UPDATE fotografico_plano_versao SET status = 'PUBLICADA', publicado_por = ?, publicado_em = NOW(), endereco_snapshot = ?, maps_url_snapshot = ? WHERE id = ?");
            $stmt->bind_param('issi', $actorId, $location['local'], $location['maps_url'], $versionId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status = 'PRONTO_EXECUCAO', publicado_em = COALESCE(publicado_em, NOW()), lock_version = lock_version + 1 WHERE id = ?");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_complete_sla($conn, $planId, 'CRIACAO');
            fotografico_start_execution_sla($conn, $planId, new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')));
            fotografico_evento($conn, $planId, 'PLANO_PUBLICADO', $plan['status'], 'PRONTO_EXECUCAO', $actorId, 'Fotografico/api.php', ['versao_id' => $versionId]);
            fotografico_notificar_colaborador($conn, $planId, (int) $plan['responsavel_execucao_id'], 'EXECUCAO_ATRIBUIDA_V' . $versionId, 'Fotografico pronto para executar', 'O plano foi publicado e a execucao foi atribuida a voce.');
        } elseif ($action === 'create_revision') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para revisar este plano.');
            if (!in_array($plan['status'], ['PRONTO_EXECUCAO', 'EM_CONFERENCIA'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'A revisao exige um plano publicado.', 422);
            }
            $stmt = $conn->prepare("SELECT * FROM fotografico_plano_versao WHERE plano_id = ? AND status = 'PUBLICADA' ORDER BY numero DESC LIMIT 1");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $source = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$source) {
                foto_response(false, null, 'VERSAO_NAO_ENCONTRADA', 'Versao publicada nao encontrada.', 422);
            }
            $newNumber = (int) $source['numero'] + 1;
            $reason = trim((string) ($payload['motivo'] ?? '')) ?: 'Revisao do plano';
            $stmt = $conn->prepare("INSERT INTO fotografico_plano_versao (plano_id, numero, status, motivo, mapa_anexo_id, criado_por) VALUES (?, ?, 'RASCUNHO', ?, ?, ?)");
            $sourceMapId = $source['mapa_anexo_id'] !== null ? (int) $source['mapa_anexo_id'] : null;
            $stmt->bind_param('iisii', $planId, $newNumber, $reason, $sourceMapId, $actorId);
            $stmt->execute();
            $newVersionId = (int) $conn->insert_id;
            $stmt->close();
            $oldVersionId = (int) $source['id'];
            $stmt = $conn->prepare("INSERT INTO fotografico_plano_imagem (versao_id, imagem_id, decisao, motivo_exclusao, pavimento_referencia, observacao_tecnica, ordem) SELECT ?, imagem_id, decisao, motivo_exclusao, pavimento_referencia, observacao_tecnica, ordem FROM fotografico_plano_imagem WHERE versao_id = ?");
            $stmt->bind_param('ii', $newVersionId, $oldVersionId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("SELECT id, codigo, x_percentual, y_percentual, direcao_graus, altura_padrao_m, pavimento_referencia, observacao, anotacao_json, ordem FROM fotografico_posicao WHERE versao_id = ? ORDER BY id");
            $stmt->bind_param('i', $oldVersionId);
            $stmt->execute();
            $oldPositions = foto_fetch_all($stmt->get_result());
            $stmt->close();
            foreach ($oldPositions as $oldPosition) {
                $stmt = $conn->prepare("INSERT INTO fotografico_posicao (versao_id, codigo, x_percentual, y_percentual, direcao_graus, altura_padrao_m, pavimento_referencia, observacao, anotacao_json, criado_por, atualizado_por, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isddddsssiii', $newVersionId, $oldPosition['codigo'], $oldPosition['x_percentual'], $oldPosition['y_percentual'], $oldPosition['direcao_graus'], $oldPosition['altura_padrao_m'], $oldPosition['pavimento_referencia'], $oldPosition['observacao'], $oldPosition['anotacao_json'], $actorId, $actorId, $oldPosition['ordem']);
                $stmt->execute();
                $newPositionId = (int) $conn->insert_id;
                $stmt->close();
                $oldPositionId = (int) $oldPosition['id'];
                $stmt = $conn->prepare('SELECT * FROM fotografico_captura WHERE posicao_id = ? ORDER BY id');
                $stmt->bind_param('i', $oldPositionId);
                $stmt->execute();
                $oldCaptures = foto_fetch_all($stmt->get_result());
                $stmt->close();
                foreach ($oldCaptures as $oldCapture) {
                    $stmt = $conn->prepare('INSERT INTO fotografico_captura (posicao_id, periodo_id, prioridade, altura_efetiva_m, observacao) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('iiids', $newPositionId, $oldCapture['periodo_id'], $oldCapture['prioridade'], $oldCapture['altura_efetiva_m'], $oldCapture['observacao']);
                    $stmt->execute();
                    $newCaptureId = (int) $conn->insert_id;
                    $stmt->close();
                    $oldCaptureId = (int) $oldCapture['id'];
                    $stmt = $conn->prepare("INSERT INTO fotografico_captura_imagem (captura_id, plano_imagem_id) SELECT ?, ni.id FROM fotografico_captura_imagem ci JOIN fotografico_plano_imagem oi ON oi.id = ci.plano_imagem_id JOIN fotografico_plano_imagem ni ON ni.versao_id = ? AND ni.imagem_id = oi.imagem_id WHERE ci.captura_id = ?");
                    $stmt->bind_param('iii', $newCaptureId, $newVersionId, $oldCaptureId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status = 'EM_ELABORACAO', lock_version = lock_version + 1 WHERE id = ?");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'REVISAO_CRIADA', $plan['status'], 'EM_ELABORACAO', $actorId, 'Fotografico/api.php', ['versao_id' => $newVersionId, 'motivo' => $reason]);
        } elseif ($action === 'submit_execution') {
            foto_assert_capability(foto_can_execute($conn, $plan), 'Somente o responsavel pela execucao pode enviar o material.');
            if ($plan['status'] !== 'PRONTO_EXECUCAO') {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'O plano nao esta pronto para execucao.', 422);
            }
            $executedAt = trim((string) ($payload['executado_em'] ?? ''));
            if ($executedAt === '') {
                foto_response(false, null, 'DATA_OBRIGATORIA', 'Informe a data e hora da execucao.', 422);
            }
            $materialUrl = trim((string) ($payload['material_url'] ?? ''));
            if ($materialUrl === '' || !filter_var($materialUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($materialUrl), 'https://')) {
                foto_response(false, null, 'LINK_DRIVE_OBRIGATORIO', 'Informe um link HTTPS valido da pasta no Drive.', 422);
            }
            $note = trim((string) ($payload['observacao'] ?? '')) ?: null;
            $stmt = $conn->prepare("SELECT id FROM fotografico_plano_versao WHERE plano_id = ? AND status = 'PUBLICADA' ORDER BY numero DESC LIMIT 1");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $published = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$published) {
                foto_response(false, null, 'VERSAO_NAO_ENCONTRADA', 'Plano publicado nao encontrado.', 422);
            }
            $versionId = (int) $published['id'];
            $stmt = $conn->prepare('SELECT COALESCE(MAX(tentativa), 0) + 1 AS tentativa FROM fotografico_execucao WHERE plano_id = ?');
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $attempt = (int) $stmt->get_result()->fetch_assoc()['tentativa'];
            $stmt->close();
            $executorId = $plan['responsavel_execucao_id'] !== null ? (int) $plan['responsavel_execucao_id'] : null;
            $plannedDate = $plan['data_planejada'] ?: null;
            $stmt = $conn->prepare("INSERT INTO fotografico_execucao (plano_id, versao_id, tentativa, responsavel_id, enviado_por, data_planejada, executado_em, material_url, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiiiissss', $planId, $versionId, $attempt, $executorId, $actorId, $plannedDate, $executedAt, $materialUrl, $note);
            $stmt->execute();
            $executionId = (int) $conn->insert_id;
            $stmt->close();
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status = 'EM_CONFERENCIA', lock_version = lock_version + 1 WHERE id = ?");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'MATERIAL_SUBMETIDO', 'PRONTO_EXECUCAO', 'EM_CONFERENCIA', $actorId, 'Fotografico/api.php', ['execucao_id' => $executionId]);
            if ((int) ($plan['responsavel_plano_id'] ?? 0) > 0) {
                fotografico_notificar_colaborador($conn, $planId, (int) $plan['responsavel_plano_id'], 'CONFERENCIA_EXEC_' . $executionId, 'Material fotografico para conferencia', 'O material foi enviado e precisa ser conferido.');
            }
        } elseif ($action === 'review') {
            foto_assert_capability(foto_can_edit($conn, $plan), 'Sem permissao para conferir este material.');
            if ($plan['status'] !== 'EM_CONFERENCIA') {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'O plano nao esta em conferencia.', 422);
            }
            $executionId = (int) ($payload['execucao_id'] ?? 0);
            $decision = strtoupper(trim((string) ($payload['decisao'] ?? '')));
            $allowedDecisions = ['APROVADO', 'APROVADO_COM_RESSALVAS', 'COMPLEMENTO_NECESSARIO', 'REPROVADO'];
            if (!in_array($decision, $allowedDecisions, true)) {
                foto_response(false, null, 'DECISAO_INVALIDA', 'Selecione uma decisao de conferencia valida.', 422);
            }
            $consideration = trim((string) ($payload['consideracao'] ?? ''));
            if ($decision !== 'APROVADO' && $consideration === '') {
                foto_response(false, null, 'CONSIDERACAO_OBRIGATORIA', 'Informe a consideracao da conferencia.', 422);
            }
            $stmt = $conn->prepare("SELECT id FROM fotografico_execucao WHERE id = ? AND plano_id = ? AND resultado = 'EM_CONFERENCIA' FOR UPDATE");
            $stmt->bind_param('ii', $executionId, $planId);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_row()) {
                foto_response(false, null, 'EXECUCAO_INVALIDA', 'Tentativa pendente nao encontrada.', 422);
            }
            $stmt->close();
            $concludes = in_array($decision, ['APROVADO', 'APROVADO_COM_RESSALVAS'], true);
            $result = match ($decision) {
                'APROVADO' => 'APROVADA',
                'APROVADO_COM_RESSALVAS' => 'APROVADA_COM_RESSALVAS',
                'COMPLEMENTO_NECESSARIO' => 'COMPLEMENTO',
                default => 'REPROVADA',
            };
            $nextStatus = $concludes ? 'CONCLUIDO' : 'PRONTO_EXECUCAO';
            $stmt = $conn->prepare('INSERT INTO fotografico_execucao_conferencia (execucao_id, decisao, consideracao, status_anterior, status_resultante, conferido_por) VALUES (?, ?, ?, ?, ?, ?)');
            $previousStatus = 'EM_CONFERENCIA';
            $stmt->bind_param('issssi', $executionId, $decision, $consideration, $previousStatus, $nextStatus, $actorId);
            $stmt->execute();
            $conferenceId = (int) $conn->insert_id;
            $stmt->close();
            $stmt = $conn->prepare('UPDATE fotografico_execucao SET resultado = ?, conferido_por = ?, conferido_em = NOW() WHERE id = ?');
            $stmt->bind_param('sii', $result, $actorId, $executionId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('UPDATE fotografico_plano SET status = ?, concluido_em = CASE WHEN ? = \'CONCLUIDO\' THEN NOW() ELSE concluido_em END, lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('ssi', $nextStatus, $nextStatus, $planId);
            $stmt->execute();
            $stmt->close();
            if ($concludes) {
                fotografico_complete_sla($conn, $planId, 'EXECUCAO');
                $stmt = $conn->prepare("UPDATE fotografico_pendencia SET status = 'RESOLVIDA', resolvido_por = ?, resolvido_em = NOW() WHERE plano_id = ? AND codigo = 'COMPLEMENTO_MATERIAL' AND status = 'ABERTA'");
                $stmt->bind_param('ii', $actorId, $planId);
                $stmt->execute();
                $stmt->close();
            } else {
                $title = $decision === 'REPROVADO' ? 'Material fotografico reprovado' : 'Complemento fotografico solicitado';
                $stmt = $conn->prepare("INSERT INTO fotografico_pendencia (plano_id, codigo, titulo, detalhes, status, responsavel_id, criado_por) VALUES (?, 'COMPLEMENTO_MATERIAL', ?, ?, 'ABERTA', ?, ?)");
                $executorId = $plan['responsavel_execucao_id'] !== null ? (int) $plan['responsavel_execucao_id'] : null;
                $stmt->bind_param('issii', $planId, $title, $consideration, $executorId, $actorId);
                $stmt->execute();
                $stmt->close();
            }
            fotografico_evento($conn, $planId, 'CONFERENCIA_REALIZADA', 'EM_CONFERENCIA', $nextStatus, $actorId, 'Fotografico/api.php', ['execucao_id' => $executionId, 'conferencia_id' => $conferenceId, 'decisao' => $decision]);
            if (!$concludes && (int) ($plan['responsavel_execucao_id'] ?? 0) > 0) {
                fotografico_notificar_colaborador($conn, $planId, (int) $plan['responsavel_execucao_id'], 'RETORNO_EXEC_' . $executionId, 'Retorno da conferencia fotografica', $title . ': ' . $consideration);
            }
        } elseif ($action === 'hold_open') {
            foto_assert_capability(foto_manager($conn), 'Somente gestor ou administrador pode abrir HOLD manual.');
            if (in_array($plan['status'], ['HOLD', 'CONCLUIDO', 'CANCELADO'], true)) {
                foto_response(false, null, 'TRANSICAO_INVALIDA', 'Nao e possivel abrir HOLD neste estado.', 422);
            }
            $code = strtoupper(trim((string) ($payload['codigo'] ?? '')));
            $allowed = ['CLIMA', 'INFORMACAO_INCOMPLETA', 'ALTERACAO_PLANO', 'REAGENDAMENTO'];
            if (!in_array($code, $allowed, true)) {
                foto_response(false, null, 'MOTIVO_INVALIDO', 'Motivo de HOLD invalido.', 422);
            }
            $details = trim((string) ($payload['detalhes'] ?? '')) ?: null;
            $responsible = (int) ($payload['responsavel_id'] ?? 0) ?: null;
            $previous = (string) $plan['status'];
            $stmt = $conn->prepare("INSERT INTO fotografico_hold (plano_id, codigo, detalhes, origem, responsavel_id, aberto_por, status_retorno, afeta_sla) VALUES (?, ?, ?, 'MANUAL', ?, ?, ?, 1)");
            $stmt->bind_param('issiis', $planId, $code, $details, $responsible, $actorId, $previous);
            $stmt->execute();
            $holdId = (int) $conn->insert_id;
            $stmt->close();
            $stmt = $conn->prepare("INSERT IGNORE INTO fotografico_sla_pausa (sla_id, hold_id, iniciado_em) SELECT id, ?, NOW() FROM fotografico_sla WHERE plano_id = ? AND completed_at IS NULL AND resultado = 'EM_ANDAMENTO'");
            $stmt->bind_param('ii', $holdId, $planId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status = 'HOLD', status_antes_hold = ?, lock_version = lock_version + 1 WHERE id = ?");
            $stmt->bind_param('si', $previous, $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'HOLD_ABERTO', $previous, 'HOLD', $actorId, 'Fotografico/api.php', ['hold_id' => $holdId, 'codigo' => $code]);
        } elseif ($action === 'hold_close') {
            foto_assert_capability(foto_manager($conn), 'Somente gestor ou administrador pode encerrar HOLD manual.');
            $holdId = (int) ($payload['hold_id'] ?? 0);
            $stmt = $conn->prepare("SELECT status_retorno FROM fotografico_hold WHERE id = ? AND plano_id = ? AND encerrado_em IS NULL AND origem = 'MANUAL' FOR UPDATE");
            $stmt->bind_param('ii', $holdId, $planId);
            $stmt->execute();
            $hold = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$hold) {
                foto_response(false, null, 'HOLD_INVALIDO', 'HOLD aberto nao encontrado.', 422);
            }
            $returnStatus = (string) $hold['status_retorno'];
            $stmt = $conn->prepare('UPDATE fotografico_hold SET encerrado_por = ?, encerrado_em = NOW() WHERE id = ?');
            $stmt->bind_param('ii', $actorId, $holdId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE fotografico_sla_pausa sp JOIN fotografico_sla s ON s.id = sp.sla_id SET sp.encerrado_em = NOW(), sp.duracao_segundos = TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()), s.total_paused_seconds = s.total_paused_seconds + TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()), s.due_at_effective = DATE_ADD(s.due_at_effective, INTERVAL TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()) SECOND) WHERE sp.hold_id = ? AND sp.encerrado_em IS NULL");
            $stmt->bind_param('i', $holdId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('UPDATE fotografico_plano SET status = ?, status_antes_hold = NULL, lock_version = lock_version + 1 WHERE id = ?');
            $stmt->bind_param('si', $returnStatus, $planId);
            $stmt->execute();
            $stmt->close();
            fotografico_evento($conn, $planId, 'HOLD_ENCERRADO', 'HOLD', $returnStatus, $actorId, 'Fotografico/api.php', ['hold_id' => $holdId]);
        } else {
            foto_response(false, null, 'ACAO_INVALIDA', 'Acao desconhecida.', 404);
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    fotografico_notify_update('plan.updated', ['plan_id' => $planId, 'action' => $action, 'client_event_id' => (string) ($payload['client_event_id'] ?? '')]);
    foto_response(true, foto_get_detail($conn, $planId));
} catch (Throwable $e) {
    error_log('[Fotografico/api] ' . $e->getMessage());
    foto_response(false, null, 'ERRO_INTERNO', $e->getMessage(), 500);
}
