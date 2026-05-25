<?php

/**
 * Atividade/data.php
 * AJAX endpoint — Analytics de Uso do Sistema
 * Retorna JSON para os diferentes painéis da tela de Atividade.
 */
require_once __DIR__ . '/../config/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autenticação
if (empty($_SESSION['idusuario']) || empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged']);
    exit;
}

// Restrito ao nível 1 (admin)
if (empty($_SESSION['nivel_acesso']) || (int)$_SESSION['nivel_acesso'] !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'access_denied']);
    exit;
}

include __DIR__ . '/../conexao.php';

$action      = trim($_GET['action'] ?? '');
$periodo     = trim($_GET['periodo'] ?? 'today');
$usuario_id  = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$tela_filtro = trim($_GET['tela'] ?? '');
$status_flt  = trim($_GET['status'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 50;
$offset      = ($page - 1) * $per_page;

// Determina data inicial conforme período
switch ($periodo) {
    case 'week':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $date_from = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'today':
    default:
        $date_from = date('Y-m-d');
        break;
}

// ================================================================
// Helper: executa prepared statement com bind dinâmico e retorna result
// ================================================================
function exec_stmt($conn, $sql, $types, $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// ================================================================
// ACTIONS
// ================================================================
switch ($action) {

    // ------------------------------------------------------------
    // KPIs
    // ------------------------------------------------------------
    case 'kpis':
        $out = [];

        // 1. Usuários online agora (atividade nos últimos 5 min)
        $res = $conn->query(
            "SELECT COUNT(DISTINCT usuario_id) AS cnt
             FROM logs_usuarios
             WHERE ultima_atividade >= NOW() - INTERVAL 5 MINUTE
               AND usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))"
        );
        $out['online_agora'] = (int)(($res ? $res->fetch_assoc() : [])['cnt'] ?? 0);

        // 2. Sessões ativas hoje (usuários únicos com registro hoje)
        $res = exec_stmt(
            $conn,
            "SELECT COUNT(DISTINCT usuario_id) AS cnt
             FROM logs_usuarios_historico
             WHERE DATE(created_at) = CURDATE()
               AND usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))",
            '',
            []
        );
        $out['sessoes_hoje'] = (int)(($res ? $res->fetch_assoc() : [])['cnt'] ?? 0);

        // 3. Tela mais acessada hoje
        $res = exec_stmt(
            $conn,
            "SELECT tela, COUNT(*) AS cnt
             FROM logs_usuarios_historico
             WHERE DATE(created_at) = CURDATE()
               AND usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))
             GROUP BY tela
             ORDER BY cnt DESC
             LIMIT 1",
            '',
            []
        );
        $row = $res ? $res->fetch_assoc() : null;
        $out['tela_mais_acessada']      = $row['tela'] ?? '—';
        $out['tela_mais_acessada_cnt']  = (int)($row['cnt'] ?? 0);

        // 4. Usuário mais ativo hoje
        $res = exec_stmt(
            $conn,
            "SELECT h.usuario_id, u.nome_usuario, COUNT(*) AS cnt
             FROM logs_usuarios_historico h
             LEFT JOIN usuario u ON u.idusuario = h.usuario_id
             WHERE DATE(h.created_at) = CURDATE()
               AND h.usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))
             GROUP BY h.usuario_id, u.nome_usuario
             ORDER BY cnt DESC
             LIMIT 1",
            '',
            []
        );
        $row = $res ? $res->fetch_assoc() : null;
        $out['usuario_mais_ativo']     = $row['nome_usuario'] ?? '—';
        $out['usuario_mais_ativo_cnt'] = (int)($row['cnt'] ?? 0);

        // 5. Total de acessos hoje
        $res = exec_stmt(
            $conn,
            "SELECT COUNT(*) AS cnt
             FROM logs_usuarios_historico
             WHERE DATE(created_at) = CURDATE()
               AND usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))",
            '',
            []
        );
        $out['total_acessos_hoje'] = (int)(($res ? $res->fetch_assoc() : [])['cnt'] ?? 0);

        echo json_encode($out);
        break;

    // ------------------------------------------------------------
    // ONLINE — todos os colaboradores ativos com seu status atual
    // Subquery em logs_usuarios garante 1 linha por usuário (sem duplicatas)
    // ------------------------------------------------------------
    case 'online':
        $where  = ['c.ativo = 1', 'c.idcolaborador NOT IN (15,30,38)'];
        $params = [];
        $types  = '';

        // Filtro por colaborador específico
        if ($usuario_id > 0) {
            $where[]  = 'c.idcolaborador = ?';
            $params[] = $usuario_id;
            $types   .= 'i';
        }

        // Filtro de status (aplicado no outer query via HAVING-like condition)
        if ($status_flt === 'online') {
            $where[] = 'latest.ultima_atividade >= NOW() - INTERVAL 5 MINUTE';
        } elseif ($status_flt === 'ausente') {
            $where[] = 'latest.ultima_atividade >= NOW() - INTERVAL 15 MINUTE';
            $where[] = 'latest.ultima_atividade < NOW() - INTERVAL 5 MINUTE';
        }

        $wc  = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT
                    c.nome_colaborador,
                    latest.tela_atual,
                    latest.ultima_atividade,
                    CASE
                        WHEN latest.ultima_atividade >= NOW() - INTERVAL 5 MINUTE  THEN 'online'
                        WHEN latest.ultima_atividade >= NOW() - INTERVAL 15 MINUTE THEN 'ausente'
                        WHEN latest.ultima_atividade IS NOT NULL                    THEN 'offline'
                        ELSE 'nunca'
                    END AS status
                FROM colaborador c
                LEFT JOIN (
                    SELECT
                        u.idcolaborador,
                        ANY_VALUE(l.tela_atual) AS tela_atual,
                        MAX(l.ultima_atividade)  AS ultima_atividade
                    FROM logs_usuarios l
                    INNER JOIN usuario u ON u.idusuario = l.usuario_id
                    GROUP BY u.idcolaborador
                ) latest ON latest.idcolaborador = c.idcolaborador
                $wc
                ORDER BY
                    CASE
                        WHEN latest.ultima_atividade >= NOW() - INTERVAL 5 MINUTE  THEN 0
                        WHEN latest.ultima_atividade >= NOW() - INTERVAL 15 MINUTE THEN 1
                        ELSE 2
                    END,
                    c.nome_colaborador ASC";

        $res  = exec_stmt($conn, $sql, $types, $params);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        echo json_encode(['rows' => $rows, 'ts' => date('H:i:s')]);
        break;

    // ------------------------------------------------------------
    // TELAS — uso de telas agrupado (logs_usuarios_historico)
    // ------------------------------------------------------------
    case 'telas':
        $where  = ['DATE(created_at) >= ?', 'usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))'];
        $params = [$date_from];
        $types  = 's';

        if ($usuario_id > 0) {
            $where[]  = 'usuario_id = ?';
            $params[] = $usuario_id;
            $types   .= 'i';
        }

        if ($tela_filtro !== '') {
            $where[]  = 'tela LIKE ?';
            $params[] = '%' . $tela_filtro . '%';
            $types   .= 's';
        }

        $wc  = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT
                    tela,
                    COUNT(*) AS total_acessos,
                    COUNT(DISTINCT usuario_id) AS usuarios_unicos,
                    MAX(created_at) AS ultimo_acesso
                FROM logs_usuarios_historico
                $wc
                GROUP BY tela
                ORDER BY total_acessos DESC
                LIMIT 100";

        $res  = exec_stmt($conn, $sql, $types, $params);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        echo json_encode(['rows' => $rows]);
        break;

    // ------------------------------------------------------------
    // HISTÓRICO — navegação recente paginada
    // ------------------------------------------------------------
    case 'historico':
        $where  = ['DATE(h.created_at) >= ?', 'h.usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))'];
        $params = [$date_from];
        $types  = 's';

        if ($usuario_id > 0) {
            $where[]  = 'h.usuario_id = ?';
            $params[] = $usuario_id;
            $types   .= 'i';
        }

        if ($tela_filtro !== '') {
            $where[]  = 'h.tela LIKE ?';
            $params[] = '%' . $tela_filtro . '%';
            $types   .= 's';
        }

        $wc = 'WHERE ' . implode(' AND ', $where);

        // Total para paginação
        $res_c = exec_stmt(
            $conn,
            "SELECT COUNT(*) AS cnt FROM logs_usuarios_historico h $wc",
            $types,
            $params
        );
        $total = (int)(($res_c ? $res_c->fetch_assoc() : [])['cnt'] ?? 0);

        // Dados paginados
        $sql = "SELECT
                    h.id,
                    COALESCE(u.nome_usuario, CONCAT('ID ', h.usuario_id)) AS nome_usuario,
                    h.tela,
                    h.ip,
                    h.created_at
                FROM logs_usuarios_historico h
                LEFT JOIN usuario u ON u.idusuario = h.usuario_id
                $wc
                ORDER BY h.created_at DESC
                LIMIT ? OFFSET ?";

        $params_p = array_merge($params, [$per_page, $offset]);
        $types_p  = $types . 'ii';

        $res  = exec_stmt($conn, $sql, $types_p, $params_p);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        echo json_encode([
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => max(1, (int)ceil($total / $per_page)),
        ]);
        break;

    // ------------------------------------------------------------
    // ATIVOS — usuários mais ativos no período
    // ------------------------------------------------------------
    case 'ativos':
        $where  = ['DATE(h.created_at) >= ?', 'h.usuario_id NOT IN (SELECT idusuario FROM usuario WHERE idcolaborador IN (15,30,38))'];
        $params = [$date_from];
        $types  = 's';

        if ($usuario_id > 0) {
            $where[]  = 'h.usuario_id = ?';
            $params[] = $usuario_id;
            $types   .= 'i';
        }

        $wc = 'WHERE ' . implode(' AND ', $where);

        // Para a subquery de tela mais acessada, precisamos do date_from novamente
        $sql = "SELECT
                    h.usuario_id,
                    COALESCE(u.nome_usuario, CONCAT('ID ', h.usuario_id)) AS nome_usuario,
                    COUNT(*) AS total_acessos,
                    MAX(h.created_at) AS ultima_atividade,
                    (
                        SELECT h2.tela
                        FROM logs_usuarios_historico h2
                        WHERE h2.usuario_id = h.usuario_id
                          AND DATE(h2.created_at) >= ?
                        GROUP BY h2.tela
                        ORDER BY COUNT(*) DESC
                        LIMIT 1
                    ) AS tela_mais_acessada
                FROM logs_usuarios_historico h
                LEFT JOIN usuario u ON u.idusuario = h.usuario_id
                $wc
                GROUP BY h.usuario_id, u.nome_usuario
                ORDER BY total_acessos DESC
                LIMIT 50";

        // Primeiro param é para a subquery (date_from), depois os do WHERE
        $params_a = array_merge([$date_from], $params);
        $types_a  = 's' . $types;

        $res  = exec_stmt($conn, $sql, $types_a, $params_a);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        echo json_encode(['rows' => $rows]);
        break;

    // ------------------------------------------------------------
    // LISTA DE USUÁRIOS — para o select de filtro
    // ------------------------------------------------------------
    case 'usuarios':
        $res  = $conn->query(
            "SELECT idusuario AS id, nome_usuario AS nome
             FROM usuario
             ORDER BY nome_usuario"
        );
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        echo json_encode(['rows' => $rows]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'invalid_action']);
        break;
}

$conn->close();
