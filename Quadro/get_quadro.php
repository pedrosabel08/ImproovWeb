<?php
header('Content-Type: application/json');

include '../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

$sql = "SELECT
    f.idfuncao,
    f.nome_funcao,
    o.idobra,
    o.nomenclatura,
    o.nome_obra,
    COUNT(*) AS total,
    SUM(CASE WHEN fi.status = 'Não iniciado'       THEN 1 ELSE 0 END) AS nao_iniciado,
    SUM(CASE WHEN fi.status = 'Em andamento'        THEN 1 ELSE 0 END) AS em_andamento,
    SUM(CASE WHEN fi.status = 'Em aprovação'        THEN 1 ELSE 0 END) AS em_aprovacao,
    SUM(CASE WHEN fi.status = 'Ajuste'              THEN 1 ELSE 0 END) AS ajuste,
    SUM(CASE WHEN fi.status = 'Aprovado com ajustes' THEN 1 ELSE 0 END) AS aprovado_ajustes,
    SUM(CASE WHEN fi.status = 'Aprovado'            THEN 1 ELSE 0 END) AS aprovado,
    SUM(CASE WHEN fi.status = 'HOLD'                THEN 1 ELSE 0 END) AS hold,
    MIN(COALESCE(pf.prioridade, 3))                                     AS prioridade_alta,
    MIN(fi.prazo)                                                        AS proximo_prazo,
    GROUP_CONCAT(DISTINCT c.nome_colaborador ORDER BY c.nome_colaborador SEPARATOR '|||') AS colaboradores,
    ico.tipo_imagem                                                      AS tipos_imagem,
    (
        SELECT COALESCE(NULLIF(hi.caminho_imagem, ''), hi.imagem)
        FROM historico_aprovacoes_imagens hi
        INNER JOIN funcao_imagem fi2 ON hi.funcao_imagem_id = fi2.idfuncao_imagem
        WHERE fi2.funcao_id    = f.idfuncao
          AND fi2.imagem_id IN (
              SELECT ico2.idimagens_cliente_obra
              FROM   imagens_cliente_obra ico2
              WHERE  ico2.obra_id      = o.idobra
                AND  ico2.tipo_imagem  = ico.tipo_imagem
          )
          AND COALESCE(hi.caminho_imagem, '') NOT LIKE '%imagem_%'
        ORDER BY hi.id DESC
        LIMIT 1
    ) AS ultima_imagem
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id  = ico.idimagens_cliente_obra
JOIN obra                  o   ON ico.obra_id  = o.idobra
JOIN funcao                f   ON fi.funcao_id = f.idfuncao
LEFT JOIN prioridade_funcao pf ON fi.idfuncao_imagem = pf.funcao_imagem_id
LEFT JOIN colaborador       c  ON fi.colaborador_id  = c.idcolaborador
WHERE o.status_obra = 0
  AND fi.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes',  'Não iniciado', 'HOLD') AND fi.colaborador_id NOT IN (30, 15)
GROUP BY f.idfuncao, f.nome_funcao, o.idobra, o.nomenclatura, o.nome_obra, ico.tipo_imagem
ORDER BY
    FIELD(f.idfuncao, 1, 8, 2, 3, 4, 5, 6) ASC,
    MIN(COALESCE(pf.prioridade, 3)) ASC,
    IF(MIN(fi.prazo) IS NULL, 1, 0),
    MIN(fi.prazo) ASC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['error' => $conn->error]);
    $conn->close();
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$conn->close();
echo json_encode($data);
