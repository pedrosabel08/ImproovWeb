<?php
header('Content-Type: application/json');

include '../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

$funcao_id = intval($_GET['funcao_id'] ?? 0);
$obra_id   = intval($_GET['obra_id']   ?? 0);

if ($funcao_id <= 0 || $obra_id <= 0) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT
    fi.idfuncao_imagem,
    ico.imagem_nome,
    ico.tipo_imagem,
    fi.status,
    fi.prazo,
    COALESCE(pc.prioridade, 3) AS prioridade,
    c.nome_colaborador,
    TIMESTAMPDIFF(MINUTE,
        (SELECT la.data FROM log_alteracoes la
         WHERE la.funcao_imagem_id = fi.idfuncao_imagem
           AND la.status_novo = 'Em andamento'
         ORDER BY la.data ASC LIMIT 1),
        NOW()
    ) AS tempo_em_andamento,
    (
        SELECT MAX(hi.imagem)
        FROM   historico_aprovacoes_imagens hi
        WHERE  hi.funcao_imagem_id = fi.idfuncao_imagem
          AND  COALESCE(hi.caminho_imagem, '') NOT LIKE '%imagem_%'
    ) AS ultima_imagem,
    (
        SELECT MAX(hi2.indice_envio)
        FROM   historico_aprovacoes_imagens hi2
        WHERE  hi2.funcao_imagem_id = fi.idfuncao_imagem
    ) AS indice_envio,
    (
        SELECT COUNT(*)
        FROM   comentarios_imagem ci
        JOIN   historico_aprovacoes_imagens hai
               ON ci.ap_imagem_id = hai.id
        WHERE  hai.funcao_imagem_id = fi.idfuncao_imagem
          AND  hai.indice_envio = (
               SELECT MAX(hi3.indice_envio)
               FROM   historico_aprovacoes_imagens hi3
               WHERE  hi3.funcao_imagem_id = fi.idfuncao_imagem
          )
    ) AS comentarios_ultima_versao
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id        = ico.idimagens_cliente_obra
LEFT JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
LEFT JOIN colaborador       c  ON fi.colaborador_id  = c.idcolaborador
WHERE fi.funcao_id = ?
  AND ico.obra_id  = ?
  AND fi.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes',  'Não iniciado', 'HOLD') AND fi.colaborador_id NOT IN (30, 15)
ORDER BY
    FIELD(fi.status,
          'Em andamento', 'Ajuste', 'Em aprovação',
          'Aprovado com ajustes', 'Aprovado', 'Não iniciado', 'HOLD'),
    ico.imagem_nome ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $funcao_id, $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();
$conn->close();
echo json_encode($data);
