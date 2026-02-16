<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
// Retorna os dados para o painel diário: mostrar?, contagens e últimas telas
if (session_status() === PHP_SESSION_NONE) session_start();

// tenta conexão
include __DIR__ . '/../conexao.php';

$usuario_id = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : 0;
$colaborador_id = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : 0;

// default response
$resp = [
    'show' => true,
    'ajustes' => 0,
    'atrasadas' => 0,
    'hoje' => 0,
    'renders' => 0,
    'recent_pages' => []
];

// Verifica se o painel já foi mostrado hoje usando a data do banco (CURDATE())
// Fazemos esta comparação no MySQL para evitar discrepâncias de timezone/clock entre PHP e o servidor de banco.
$query = "SELECT last_panel_shown_date,
    (CASE
        WHEN last_panel_shown_date IS NOT NULL AND DATE(last_panel_shown_date) = CURDATE() THEN 1
        ELSE 0
    END) AS seen_today
    FROM logs_usuarios WHERE usuario_id = ? LIMIT 1";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (isset($row['seen_today']) && intval($row['seen_today']) === 1) {
            $resp['show'] = false;
        }
    }
    $stmt->close();
}

// contagens de funcao_imagem
$sql_ajuste = "SELECT COUNT(*) as c FROM funcao_imagem WHERE status = 'Ajuste' AND colaborador_id = ?";
if ($stmt = $conn->prepare($sql_ajuste)) {
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $resp['ajustes'] = intval($r['c'] ?? 0);
    $stmt->close();
}

$sql_atrasadas = "SELECT COUNT(*) as c FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ? AND prazo < CURDATE()";
if ($stmt = $conn->prepare($sql_atrasadas)) {
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $resp['atrasadas'] = intval($r['c'] ?? 0);
    $stmt->close();
}

$sql_hoje = "SELECT COUNT(*) as c FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ? AND prazo = CURDATE()";
if ($stmt = $conn->prepare($sql_hoje)) {
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $resp['hoje'] = intval($r['c'] ?? 0);
    $stmt->close();
}

// renders (reaproveita a lógica de verifica_render.php)
$sql_rend = "SELECT COUNT(*) AS total FROM render_alta WHERE responsavel_id = ? AND status IN ('Em aprovação')";
if ($stmt = $conn->prepare($sql_rend)) {
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $resp['renders'] = intval($r['total'] ?? 0);
    $stmt->close();
}

// últimas páginas visitadas: queremos as 3 últimas telas distintas (pela coluna `tela`)
// Estratégia: para cada `tela` pega-se o registro mais recente (MAX(created_at)),
// então ordena por created_at desc e limita a 3.
$sql_pages = "SELECT t.tela, t.url, t.created_at
    FROM logs_usuarios_historico t
    INNER JOIN (
        SELECT tela, MAX(created_at) AS max_at
        FROM logs_usuarios_historico
        WHERE usuario_id = ?
        GROUP BY tela
    ) m ON t.tela = m.tela AND t.created_at = m.max_at
    WHERE t.usuario_id = ?
    ORDER BY t.created_at DESC
    LIMIT 3
";
if ($stmt = $conn->prepare($sql_pages)) {
    // bind usuario_id twice (subquery + outer WHERE)
    $stmt->bind_param('ii', $usuario_id, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $resp['recent_pages'][] = [
            'tela' => $row['tela'],
            'url' => $row['url'],
            'at' => $row['created_at']
        ];
    }
    $stmt->close();
}

echo json_encode($resp);
