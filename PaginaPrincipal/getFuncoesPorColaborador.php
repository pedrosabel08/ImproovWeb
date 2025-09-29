<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
date_default_timezone_set('America/Sao_Paulo');

// ====================
// FUNÇÕES (SEM FILTROS)
// ====================
$sql = "SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.imagem_nome,
    fi.status,
    fi.prazo,
    f.nome_funcao,
    fi.observacao,
    pc.prioridade,
    fi.idfuncao_imagem,
    fi.funcao_id,
    ico.obra_id,
    o.nomenclatura,
    ico.prazo AS imagem_prazo,
    ico.idimagens_cliente_obra AS idimagem,
    si.nome_status,
    TIMESTAMPDIFF(
        MINUTE,
        (SELECT la.data FROM log_alteracoes la
         WHERE la.funcao_imagem_id = fi.idfuncao_imagem
           AND la.status_novo = 'Em andamento'
         ORDER BY la.data ASC LIMIT 1),
        (SELECT la.data FROM log_alteracoes la
         WHERE la.funcao_imagem_id = fi.idfuncao_imagem
           AND la.status_novo = 'Em aprovação'
         ORDER BY la.data ASC LIMIT 1)
    ) AS tempo_em_andamento,
    (
        SELECT COUNT(*)
        FROM comentarios_imagem ci
        JOIN historico_aprovacoes_imagens hi2
          ON ci.ap_imagem_id = hi2.id
        WHERE hi2.funcao_imagem_id = fi.idfuncao_imagem
          AND hi2.indice_envio = (
              SELECT MAX(hi3.indice_envio)
              FROM historico_aprovacoes_imagens hi3
              WHERE hi3.funcao_imagem_id = fi.idfuncao_imagem
          )
    ) AS comentarios_ultima_versao,
    (
        SELECT MAX(hi.indice_envio)
        FROM historico_aprovacoes_imagens hi
        WHERE hi.funcao_imagem_id = fi.idfuncao_imagem
    ) AS indice_envio_atual
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN status_imagem si ON ico.status_id = si.idstatus
JOIN obra o ON o.idobra = ico.obra_id
JOIN funcao f ON fi.funcao_id = f.idfuncao
JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
WHERE fi.colaborador_id = ?
  AND o.status_obra = 0
ORDER BY prazo DESC, imagem_id, obra_id,
    FIELD(fi.status,
          'Não iniciado','HOLD','Em andamento','Ajuste',
          'Em aprovação','Aprovado com ajustes','Aprovado','Finalizado')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaboradorId);
$stmt->execute();
$result = $stmt->get_result();
$funcoes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ====================
// TAREFAS
// ====================
$sqlTarefas = "SELECT 
    id,
    titulo,
    descricao,
    prazo,
    status,
    prioridade,
    CASE 
        WHEN prazo < CURDATE() AND status <> 'Finalizado' THEN 'Atrasada'
        ELSE 'Dentro do prazo'
    END AS situacao
FROM tarefas
WHERE colaborador_id = ?
ORDER BY prazo ASC;";
$stmtTarefas = $conn->prepare($sqlTarefas);
$stmtTarefas->bind_param("i", $colaboradorId);
$stmtTarefas->execute();
$resultTarefas = $stmtTarefas->get_result();
$tarefas = $resultTarefas->fetch_all(MYSQLI_ASSOC);
$stmtTarefas->close();

// ====================
// MÉDIA TEMPO EM ANDAMENTO
// ====================
$sqlMedia = "SELECT 
    f.nome_funcao,
    fi.funcao_id,
    ROUND(AVG(TIMESTAMPDIFF(
        MINUTE,
        (SELECT la1.data FROM log_alteracoes la1
         WHERE la1.funcao_imagem_id = fi.idfuncao_imagem
           AND la1.status_novo = 'Em andamento'
         ORDER BY la1.data ASC LIMIT 1),
        (SELECT la2.data FROM log_alteracoes la2
         WHERE la2.funcao_imagem_id = fi.idfuncao_imagem
           AND la2.status_novo = 'Em aprovação'
         ORDER BY la2.data ASC LIMIT 1)
    ))) AS media_tempo
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN obra o ON o.idobra = ico.obra_id
JOIN funcao f ON f.idfuncao = fi.funcao_id
WHERE fi.colaborador_id = ? 
  AND o.status_obra = 0
GROUP BY fi.funcao_id";

$stmtMedia = $conn->prepare($sqlMedia);
$stmtMedia->bind_param("i", $colaboradorId);
$stmtMedia->execute();
$resultMedia = $stmtMedia->get_result();
$mediaTemposPorFuncao = [];
while ($row = $resultMedia->fetch_assoc()) {
    $mediaTemposPorFuncao[$row['funcao_id']] = intval($row['media_tempo']);
}
$stmtMedia->close();

// ====================
// Função para calcular tempo por status
// ====================
function calcularTempo($logs, $statusAtual)
{
    $tempoCalculado = null;
    switch ($statusAtual) {
        case 'Em aprovação':
            $dataAprovacao = null;

            // Ordena os logs por data decrescente (mais recente primeiro)
            usort($logs, function ($a, $b) {
                return strtotime($b['data']) <=> strtotime($a['data']);
            });

            // Busca a primeira ocorrência de "Em aprovação" mais recente
            foreach ($logs as $log) {
                if ($log['status_novo'] === 'Em aprovação') {
                    $dataAprovacao = new DateTime($log['data']);
                    break;
                }
            }

            if ($dataAprovacao) {
                $diff = $dataAprovacao->diff(new DateTime()); // tempo até agora
                $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
            }
            break;

        case 'Em andamento':
            foreach ($logs as $log) {
                if ($log['status_novo'] === 'Não iniciado') {
                    $dataInicio = new DateTime($log['data']);
                    $diff = $dataInicio->diff(new DateTime());
                    $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
                    break;
                }
            }
            break;

        case 'Ajuste':
        case 'HOLD':
            foreach ($logs as $log) {
                if ($log['status_novo'] === $statusAtual) {
                    $dataStatus = new DateTime($log['data']);
                    $diff = $dataStatus->diff(new DateTime());
                    $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
                }
            }
            break;

        case 'Finalizado':
        case 'Aprovado':
        case 'Aprovado com ajustes':
            if (count($logs) > 1) {
                $primeira = new DateTime($logs[0]['data']);
                $ultima   = new DateTime(end($logs)['data']);
                $diff = $primeira->diff($ultima);
                $tempoCalculado = $diff->days * 1440 + $diff->h * 60 + $diff->i;
            }
            break;

        default:
            $tempoCalculado = null;
            break;
    }

    return $tempoCalculado;
}

// ====================
// Consulta única para logs de todas funções
// ====================
$funcaoImagemIds = array_column($funcoes, 'idfuncao_imagem');
$logsPorFuncao = [];

if (count($funcaoImagemIds) > 0) {
    $inIds = implode(',', array_fill(0, count($funcaoImagemIds), '?'));
    $sqlLogsAll = "SELECT funcao_imagem_id, status_novo, data 
                   FROM log_alteracoes 
                   WHERE funcao_imagem_id IN ($inIds) 
                   ORDER BY data ASC";
    $stmtLogsAll = $conn->prepare($sqlLogsAll);
    $types = str_repeat('i', count($funcaoImagemIds));
    $stmtLogsAll->bind_param($types, ...$funcaoImagemIds);
    $stmtLogsAll->execute();
    $resultLogsAll = $stmtLogsAll->get_result();
    while ($row = $resultLogsAll->fetch_assoc()) {
        $logsPorFuncao[$row['funcao_imagem_id']][] = $row;
    }
    $stmtLogsAll->close();
}

// ====================
// Ajusta Funções (liberação, ordem, etc.)
// ====================
$imagemIds = array_unique(array_column($funcoes, 'imagem_id')); // <- unique para evitar placeholders duplicados
$todasFuncoes = [];

if (count($imagemIds) > 0) {
    $inImagem = implode(',', array_fill(0, count($imagemIds), '?'));
    $sqlTodasFuncoes = "SELECT imagem_id, funcao_id, status, prazo
                        FROM funcao_imagem
                        WHERE imagem_id IN ($inImagem)";
    $stmtTodas = $conn->prepare($sqlTodasFuncoes);
    $typesTodas = str_repeat('i', count($imagemIds));
    $stmtTodas->bind_param($typesTodas, ...$imagemIds);
    $stmtTodas->execute();
    $resultTodas = $stmtTodas->get_result();
    while ($row = $resultTodas->fetch_assoc()) {
        $todasFuncoes[$row['imagem_id']][$row['funcao_id']] = $row;
    }
    $stmtTodas->close();
}

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

$funcoesFinal = [];
$ordemIds = array_keys($ordemFuncoes);

// ====================
// Descobre a primeira função REAL de cada imagem (USANDO todasFuncoes)
// ====================
$primeiraFuncaoImagem = [];
foreach ($todasFuncoes as $img => $listaFuncoes) {
    $menorPos = PHP_INT_MAX;
    $primeira = null;
    foreach ($listaFuncoes as $funcaoId => $dados) {
        $pos = array_search($funcaoId, $ordemIds);
        if ($pos !== false && $pos < $menorPos) {
            $menorPos = $pos;
            $primeira = $funcaoId;
        }
    }
    if ($primeira !== null) {
        $primeiraFuncaoImagem[$img] = $primeira;
    }
}

// ====================
// Agora processa as funções (usando primeiraFuncaoImagem calculada corretamente)
// ====================
foreach ($funcoes as $funcao) {
    $funcaoAtualId = $funcao['funcao_id'];
    $imagemId      = $funcao['imagem_id'];
    $indiceAtual   = array_search($funcaoAtualId, $ordemIds);

    $statusAnterior   = null;
    $liberada         = false;
    $funcaoAnteriorId = null;
    $prazoAnterior    = null;

    // Se for Alteração (funcao_id == 6), sempre libera
    if ($funcaoAtualId == 6) {
        $liberada = true;
    }
    // Se esta é a primeira função REAL da imagem, libera sempre
    elseif (isset($primeiraFuncaoImagem[$imagemId]) && $primeiraFuncaoImagem[$imagemId] == $funcaoAtualId) {
        $liberada = true;
    }
    // Caso contrário, aplica a regra normal (procura anterior EXISTENTE na ordem oficial)
    elseif ($indiceAtual !== false && $indiceAtual > 0 && isset($todasFuncoes[$imagemId])) {
        for ($i = $indiceAtual - 1; $i >= 0; $i--) {
            $funcaoAnteriorId = $ordemIds[$i];
            if (isset($todasFuncoes[$imagemId][$funcaoAnteriorId])) {
                $rowAnterior    = $todasFuncoes[$imagemId][$funcaoAnteriorId];
                $statusAnterior = $rowAnterior['status'];
                $prazoAnterior  = $rowAnterior['prazo'];
                if (in_array($statusAnterior, ['Finalizado', 'Aprovado', 'Aprovado com ajustes'])) {
                    $liberada = true;
                }
                break;
            }
        }
    }

    // Calcular tempo por status usando logs já consultados
    $funcaoId       = $funcao['idfuncao_imagem'];
    $logs           = isset($logsPorFuncao[$funcaoId]) ? $logsPorFuncao[$funcaoId] : [];
    $tempoCalculado = calcularTempo($logs, $funcao['status']);

    $funcoesFinal[] = [
        'imagem_id'                  => $funcao['imagem_id'],
        'imagem_nome'                => $funcao['imagem_nome'],
        'status'                     => $funcao['status'],
        'prazo'                      => $funcao['prazo'],
        'nome_funcao'                => $funcao['nome_funcao'],
        'prioridade'                 => $funcao['prioridade'],
        'funcao_id'                  => $funcao['funcao_id'],
        'nome_status'                  => $funcao['nome_status'],
        'status_funcao_anterior'     => $statusAnterior,
        'prazo_funcao_anterior'      => $prazoAnterior,
        'liberada'                   => $liberada,
        'funcaoAnteriorId'           => $funcaoAnteriorId,
        'obra_id'                    => $funcao['obra_id'],
        'nomenclatura'               => $funcao['nomenclatura'],
        'idfuncao_imagem'            => $funcao['idfuncao_imagem'],
        'tempo_em_andamento'         => $funcao['tempo_em_andamento'],
        'imagem_prazo'               => $funcao['imagem_prazo'],
        'comentarios_ultima_versao'  => $funcao['comentarios_ultima_versao'],
        'indice_envio_atual'         => $funcao['indice_envio_atual'],
        'observacao'                 => $funcao['observacao'],
        'tempo_calculado'            => $tempoCalculado
    ];
}

// ====================
// RESPONSE FINAL ÚNICO
// ====================
$response = [
    "funcoes"                 => $funcoesFinal,
    "tarefas"                 => $tarefas,
    "media_tempo_em_andamento" => $mediaTemposPorFuncao
];

echo json_encode($response);

$conn->close();
