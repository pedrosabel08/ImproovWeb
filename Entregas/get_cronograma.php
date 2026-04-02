<?php
/**
 * get_cronograma.php
 * Gera cronograma de conclusão para uma ou mais entregas.
 * 
 * POST JSON: { entrega_ids: [1,2,3] }
 *   - entrega_ids ordenados por prioridade (índice 0 = maior prioridade)
 * 
 * Retorna: {
 *   entregas: [
 *     {
 *       entrega_id, nomenclatura, nome_etapa, data_prevista,
 *       estimativa_conclusao, is_atrasado,
 *       tarefas: [
 *         { imagem_nome, funcao_nome, colaborador_nome, colaborador_id,
 *           data_inicio, data_fim, is_critical, is_fallback }
 *       ],
 *       gargalo: { colaborador_nome, quantidade }
 *     }
 *   ],
 *   colaboradores: [ { id, nome } ]
 * }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$entrega_ids = $input['entrega_ids'] ?? [];

if (!is_array($entrega_ids) || count($entrega_ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'entrega_ids é obrigatório']);
    exit;
}

// Sanitize IDs
$entrega_ids = array_map('intval', $entrega_ids);
$entrega_ids = array_filter($entrega_ids, function ($id) {
    return $id > 0; });

if (count($entrega_ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'IDs inválidos']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($entrega_ids), '?'));
$types = str_repeat('i', count($entrega_ids));

// 1) Buscar informações das entregas
$sql_entregas = "SELECT e.id, e.obra_id, e.status_id, e.data_prevista, 
                        o.nomenclatura, s.nome_status AS nome_etapa
                 FROM entregas e
                 JOIN obra o ON e.obra_id = o.idobra
                 JOIN status_imagem s ON e.status_id = s.idstatus
                 WHERE e.id IN ($placeholders)";

$stmt = $conn->prepare($sql_entregas);
$stmt->bind_param($types, ...$entrega_ids);
$stmt->execute();
$res = $stmt->get_result();

$entregas_info = [];
while ($row = $res->fetch_assoc()) {
    $entregas_info[intval($row['id'])] = $row;
}
$stmt->close();

// 2) Buscar imagens pendentes por entrega (itens que NÃO estão entregues)
$sql_itens = "SELECT ei.entrega_id, ei.imagem_id, i.imagem_nome
              FROM entregas_itens ei
              JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
              WHERE ei.entrega_id IN ($placeholders)
                AND ei.status IN ('Pendente', 'Entrega pendente')
              ORDER BY ei.entrega_id, ei.id";

$stmt = $conn->prepare($sql_itens);
$stmt->bind_param($types, ...$entrega_ids);
$stmt->execute();
$res = $stmt->get_result();

$itens_por_entrega = [];
$all_imagem_ids = [];
while ($row = $res->fetch_assoc()) {
    $eid = intval($row['entrega_id']);
    $iid = intval($row['imagem_id']);
    $itens_por_entrega[$eid][] = [
        'imagem_id' => $iid,
        'imagem_nome' => $row['imagem_nome']
    ];
    $all_imagem_ids[] = $iid;
}
$stmt->close();

$all_imagem_ids = array_unique($all_imagem_ids);

if (count($all_imagem_ids) === 0) {
    // Nenhuma imagem pendente
    $result_entregas = [];
    foreach ($entrega_ids as $eid) {
        if (!isset($entregas_info[$eid]))
            continue;
        $info = $entregas_info[$eid];
        $result_entregas[] = [
            'entrega_id' => $eid,
            'nomenclatura' => $info['nomenclatura'],
            'nome_etapa' => $info['nome_etapa'],
            'data_prevista' => $info['data_prevista'],
            'estimativa_conclusao' => date('Y-m-d'),
            'is_atrasado' => false,
            'tarefas' => [],
            'gargalo' => null
        ];
    }
    echo json_encode(['entregas' => $result_entregas, 'colaboradores' => []]);
    exit;
}

// 3) Buscar funções pendentes por imagem
$img_placeholders = implode(',', array_fill(0, count($all_imagem_ids), '?'));
$img_types = str_repeat('i', count($all_imagem_ids));

$sql_funcoes = "SELECT fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status,
                       f.nome_funcao, c.nome_colaborador
                FROM funcao_imagem fi
                JOIN funcao f ON fi.funcao_id = f.idfuncao
                LEFT JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
                WHERE fi.imagem_id IN ($img_placeholders)
                  AND fi.status NOT IN ('Finalizado', 'Aprovado', 'Aprovado com ajustes')
                ORDER BY fi.imagem_id, fi.funcao_id";

$stmt = $conn->prepare($sql_funcoes);
$stmt->bind_param($img_types, ...$all_imagem_ids);
$stmt->execute();
$res = $stmt->get_result();

$funcoes_por_imagem = [];
while ($row = $res->fetch_assoc()) {
    $iid = intval($row['imagem_id']);
    $funcoes_por_imagem[$iid][] = [
        'funcao_id' => intval($row['funcao_id']),
        'funcao_nome' => $row['nome_funcao'],
        'colaborador_id' => $row['colaborador_id'] ? intval($row['colaborador_id']) : null,
        'colaborador_nome' => $row['nome_colaborador'] ?: 'Sem responsável'
    ];
}
$stmt->close();

// 4) Buscar lista de colaboradores (para edição inline)
$sql_colabs = "SELECT idcolaborador AS id, nome_colaborador AS nome 
               FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador";
$res_colabs = $conn->query($sql_colabs);
$colaboradores = [];
while ($row = $res_colabs->fetch_assoc()) {
    $colaboradores[] = $row;
}

// 5) Simulação de fila — duração fixa de 1 dia por tarefa
$hoje = new DateTime('today');

// Montar lista de tarefas na ordem de prioridade
// Cada tarefa: entrega_id, imagem_id, imagem_nome, funcao_id, funcao_nome, 
//              colaborador_id, colaborador_nome, depends_on (imagem_id+funcao_id da função anterior)
$task_list = [];

foreach ($entrega_ids as $eid) {
    if (!isset($itens_por_entrega[$eid]))
        continue;
    foreach ($itens_por_entrega[$eid] as $item) {
        $iid = $item['imagem_id'];
        if (!isset($funcoes_por_imagem[$iid]))
            continue;

        $funcs = $funcoes_por_imagem[$iid];
        $prev_key = null;

        foreach ($funcs as $func) {
            $task_key = $iid . '_' . $func['funcao_id'];
            $colab_id = $func['colaborador_id'] ?? 0; // 0 = sem responsável

            $task_list[] = [
                'key' => $task_key,
                'entrega_id' => $eid,
                'imagem_id' => $iid,
                'imagem_nome' => $item['imagem_nome'],
                'funcao_id' => $func['funcao_id'],
                'funcao_nome' => $func['funcao_nome'],
                'colaborador_id' => $colab_id,
                'colaborador_nome' => $func['colaborador_nome'],
                'depends_on' => $prev_key // chave da tarefa anterior na mesma imagem
            ];

            $prev_key = $task_key;
        }
    }
}

// Simular fila
$colab_next_day = []; // colaborador_id => próximo dia livre (int, 0 = hoje)
$task_end_day = [];   // task_key => dia de conclusão (int offset from hoje)

$scheduled = [];

foreach ($task_list as &$task) {
    $colab_id = $task['colaborador_id'];

    // Dia mais cedo que o colaborador está livre
    $colab_day = $colab_next_day[$colab_id] ?? 0;

    // Dia mais cedo que a dependência termina
    $dep_day = 0;
    if ($task['depends_on'] !== null && isset($task_end_day[$task['depends_on']])) {
        $dep_day = $task_end_day[$task['depends_on']];
    }

    $start_day = max($colab_day, $dep_day);
    $end_day = $start_day + 1; // duração fixa = 1 dia

    // Atualizar estado
    $colab_next_day[$colab_id] = $end_day;
    $task_end_day[$task['key']] = $end_day;

    $task['start_day'] = $start_day;
    $task['end_day'] = $end_day;
}
unset($task);

// 6) Agrupar resultados por entrega e detectar gargalo + caminho crítico
$result_entregas = [];

foreach ($entrega_ids as $eid) {
    if (!isset($entregas_info[$eid]))
        continue;
    $info = $entregas_info[$eid];

    // Filtrar tarefas desta entrega
    $tarefas_entrega = array_filter($task_list, function ($t) use ($eid) {
        return $t['entrega_id'] === $eid;
    });

    if (count($tarefas_entrega) === 0) {
        $result_entregas[] = [
            'entrega_id' => $eid,
            'nomenclatura' => $info['nomenclatura'],
            'nome_etapa' => $info['nome_etapa'],
            'data_prevista' => $info['data_prevista'],
            'estimativa_conclusao' => date('Y-m-d'),
            'is_atrasado' => false,
            'tarefas' => [],
            'gargalo' => null
        ];
        continue;
    }

    // Encontrar o dia máximo de conclusão
    $max_end = 0;
    foreach ($tarefas_entrega as $t) {
        if ($t['end_day'] > $max_end) {
            $max_end = $t['end_day'];
        }
    }

    $data_estimativa = clone $hoje;
    $data_estimativa->modify('+' . $max_end . ' days');
    $estimativa_str = $data_estimativa->format('Y-m-d');

    $is_atrasado = $info['data_prevista'] && $estimativa_str > $info['data_prevista'];

    // Detectar gargalo: colaborador com mais tarefas
    $colab_count = [];
    foreach ($tarefas_entrega as $t) {
        $cid = $t['colaborador_id'];
        $cname = $t['colaborador_nome'];
        if (!isset($colab_count[$cid])) {
            $colab_count[$cid] = ['nome' => $cname, 'qty' => 0];
        }
        $colab_count[$cid]['qty']++;
    }

    // O gargalo é quem tem a tarefa com o end_day mais alto
    $gargalo = null;
    $critical_colab = null;
    foreach ($tarefas_entrega as $t) {
        if ($t['end_day'] === $max_end) {
            $critical_colab = $t['colaborador_id'];
            break;
        }
    }
    if ($critical_colab !== null && isset($colab_count[$critical_colab])) {
        $gargalo = [
            'colaborador_nome' => $colab_count[$critical_colab]['nome'],
            'quantidade' => $colab_count[$critical_colab]['qty']
        ];
    }

    // Identificar tarefas críticas (que terminam no dia max_end)
    // Rastrear caminho crítico: última tarefa + suas dependências
    $critical_keys = [];
    foreach ($tarefas_entrega as $t) {
        if ($t['end_day'] === $max_end) {
            // Rastrear chain backwards
            $key = $t['key'];
            $critical_keys[$key] = true;
            // Encontrar dependências na chain
            $dep = $t['depends_on'];
            while ($dep !== null) {
                $critical_keys[$dep] = true;
                // Encontrar a tarefa com essa key
                $found = false;
                foreach ($tarefas_entrega as $t2) {
                    if ($t2['key'] === $dep) {
                        $dep = $t2['depends_on'];
                        $found = true;
                        break;
                    }
                }
                if (!$found)
                    break;
            }
        }
    }

    // Formatar tarefas
    $tarefas_out = [];
    foreach ($tarefas_entrega as $t) {
        $dt_inicio = clone $hoje;
        $dt_inicio->modify('+' . $t['start_day'] . ' days');
        $dt_fim = clone $hoje;
        $dt_fim->modify('+' . ($t['end_day'] - 1) . ' days'); // end_day é exclusive

        $tarefas_out[] = [
            'imagem_id' => $t['imagem_id'],
            'imagem_nome' => $t['imagem_nome'],
            'funcao_id' => $t['funcao_id'],
            'funcao_nome' => $t['funcao_nome'],
            'colaborador_id' => $t['colaborador_id'],
            'colaborador_nome' => $t['colaborador_nome'],
            'data_inicio' => $dt_inicio->format('Y-m-d'),
            'data_fim' => $dt_fim->format('Y-m-d'),
            'is_critical' => isset($critical_keys[$t['key']]),
            'is_fallback' => ($t['colaborador_id'] === 0)
        ];
    }

    $result_entregas[] = [
        'entrega_id' => $eid,
        'nomenclatura' => $info['nomenclatura'],
        'nome_etapa' => $info['nome_etapa'],
        'data_prevista' => $info['data_prevista'],
        'estimativa_conclusao' => $estimativa_str,
        'is_atrasado' => $is_atrasado,
        'tarefas' => $tarefas_out,
        'gargalo' => $gargalo
    ];
}

echo json_encode(['entregas' => $result_entregas, 'colaboradores' => $colaboradores]);
