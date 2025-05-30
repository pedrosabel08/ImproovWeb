<?php
// Configurações da conexão

include '../conexao.php';

// Array de ordem das funções
$ordemFuncoes = [
    1 => 'Caderno',
    8 => 'Filtro de assets',
    2 => 'Modelagem',
    3 => 'Composição',
    9 => 'Pré-Finalização',
    4 => 'Finalização',
    5 => 'Pós-produção',
    6 => 'Alteração',
    7 => 'Planta Humanizada'
];

// 1) Buscar todos os registros com joins para obter nome da obra e nome da imagem
$sql = "SELECT 
    gp.imagem_id,
    gp.etapa,
    gp.data_inicio,
    gp.data_fim,
    ico.imagem_nome,
    o.nomenclatura,
    fi.status AS status_funcao_atual,
    fi.funcao_id AS funcao_id_atual,
    fi.colaborador_id,
    c.nome_colaborador
FROM gantt_prazos gp
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = gp.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
LEFT JOIN funcao_imagem fi ON fi.imagem_id = gp.imagem_id 
   AND fi.funcao_id = (
        SELECT idfuncao FROM funcao WHERE nome_funcao = gp.etapa LIMIT 1
   )
INNER JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
WHERE fi.colaborador_id = 6
ORDER BY gp.imagem_id, gp.etapa";

$result = $conn->query($sql);
if (!$result) {
    die("Erro na consulta: " . $conn->error);
}

$dados = [];
while ($row = $result->fetch_assoc()) {
    $imagemId = $row['imagem_id'];
    if (!isset($dados[$imagemId])) {
        $dados[$imagemId] = [
            'imagem_id' => $imagemId,
            'imagem_nome' => $row['imagem_nome'],
            'nomenclatura' => $row['nomenclatura'],
            'nome_colaborador' => $row['nome_colaborador'],
            'etapas' => []
        ];
    }

    $dados[$imagemId]['etapas'][] = [
        'etapa' => $row['etapa'],
        'data_inicio' => $row['data_inicio'],
        'data_fim' => $row['data_fim'],
        'status_funcao_atual' => $row['status_funcao_atual'],
        'funcao_id_atual' => $row['funcao_id_atual'],
        'colaborador_id' => $row['colaborador_id']
    ];
}
// Agora para cada registro, buscar status da função anterior (se existir), seguindo a ordem de $ordemFuncoes

foreach ($dados as &$imagem) {
    foreach ($imagem['etapas'] as &$etapa) {
        $funcaoAtualId = (int)$etapa['funcao_id_atual'];
        $imagemId = (int)$imagem['imagem_id'];

        $ordemIds = array_keys($ordemFuncoes);
        $indiceAtual = array_search($funcaoAtualId, $ordemIds);

        $statusAnterior = null;
        $funcaoAnteriorId = null;

        if ($indiceAtual !== false && $indiceAtual > 0) {
            for ($i = $indiceAtual - 1; $i >= 0; $i--) {
                $funcaoAnteriorId = $ordemIds[$i];
                $sqlAnterior = "SELECT status FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1";
                $stmtAnterior = $conn->prepare($sqlAnterior);
                $stmtAnterior->bind_param('ii', $imagemId, $funcaoAnteriorId);
                $stmtAnterior->execute();
                $resultAnterior = $stmtAnterior->get_result();

                if ($rowAnterior = $resultAnterior->fetch_assoc()) {
                    $statusAnterior = $rowAnterior['status'];
                    $stmtAnterior->close();
                    break;
                }
                $stmtAnterior->close();
            }
        }

        $etapa['status_funcao_anterior'] = $statusAnterior;
        $etapa['funcao_anterior_id'] = $funcaoAnteriorId;
    }
}

// Pronto, $dados tem tudo: obra, imagem, etapa, datas, status da função atual e status da função anterior

header('Content-Type: application/json');
echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conn->close();
