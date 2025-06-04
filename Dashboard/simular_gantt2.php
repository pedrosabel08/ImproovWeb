<?php
include '../conexao.php';

$obra_id = 58; // Altere para o ID da obra que deseja simular

$grupos = [
    "Fachada" => [
        "Modelagem" => 7,
        "Composição" => 1,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Imagem Externa" => [
        "Caderno" => 0.5,
        "Filtro de assets" => 0.5,
        "Modelagem" => 7,
        "Composição" => 1,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Imagem Interna" => [
        "Caderno" => 0.5,
        "Filtro de assets" => 0.5,
        "Modelagem" => 0.5,
        "Composição" => 0.5,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Unidade" => [
        "Caderno" => 0.5,
        "Filtro de assets" => 0.5,
        "Modelagem" => 0.5,
        "Composição" => 0.5,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Planta Humanizada" => [
        "Planta Humanizada" => 1
    ]
];

simularGantt($conn, $obra_id, $grupos);

function simularGantt($conn, $obra_id, $grupos)
{
    $data_inicio_gantt = '2025-05-23';

    // Busca todos os tipos de imagem válidos para a obra
    $stmt = $conn->prepare("SELECT DISTINCT tipo_imagem FROM imagens_cliente_obra WHERE obra_id = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00'");
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tiposComRecebimento = [];
    while ($row = $result->fetch_assoc()) {
        $tiposComRecebimento[] = $row['tipo_imagem'];
    }

    $gruposFiltrados = array_filter($grupos, function ($grupo) use ($tiposComRecebimento) {
        return in_array($grupo, $tiposComRecebimento);
    }, ARRAY_FILTER_USE_KEY);

    // Junta todas as imagens de todos os grupos
    $imagensTodas = [];
    foreach ($gruposFiltrados as $grupo => $etapas) {
        if ($grupo === "Planta Humanizada") continue;
        $stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id, recebimento_arquivos, tipo_imagem FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00'");
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['grupo'] = $grupo;
            $imagensTodas[] = $row;
        }
    }
    usort($imagensTodas, function ($a, $b) {
        return strcmp($a['recebimento_arquivos'], $b['recebimento_arquivos']);
    });

    // 1. Processa Caderno e Filtro de assets apenas para grupos que possuem essas etapas
    $etapasConjunto = ['Caderno', 'Filtro de assets'];
    $datasEtapas = [];
    $data_atual = $data_inicio_gantt;
    $colaborador_id = 19;
    $limite = buscarLimiteColaborador($conn, $colaborador_id, 'Caderno');

    $gruposComCadernoFiltro = array_filter($grupos, function ($etapas) {
        return isset($etapas['Caderno']) && isset($etapas['Filtro de assets']);
    });
    $imagensCadernoFiltro = array_filter($imagensTodas, function ($img) use ($gruposComCadernoFiltro) {
        return isset($gruposComCadernoFiltro[$img['grupo']]);
    });
    $fila = array_values($imagensCadernoFiltro);

    $data_atual_etapa = $data_atual;
    while (count($fila) > 0) {
        $lote = array_splice($fila, 0, $limite);
        foreach ($lote as $img) {
            foreach (['Caderno', 'Filtro de assets'] as $etapa) {
                $datasEtapas[$img['id']][$etapa] = [
                    'data_inicio' => $data_atual_etapa,
                    'data_fim' => $data_atual_etapa,
                    'colaborador_id' => $colaborador_id,
                    'grupo' => $img['grupo']
                ];
                // Salva no banco
                inserirGanttEPessoa($conn, $obra_id, $img['id'], $img['grupo'], $etapa, 1, $data_atual_etapa, $data_atual_etapa, $colaborador_id);
                $nomeColaborador = buscarNomeColaborador($conn, $colaborador_id);
                echo "Simular: Colaborador = $nomeColaborador ($colaborador_id) | {$img['grupo']} | imagem {$img['id']} | $etapa: $data_atual_etapa - $data_atual_etapa\n";
            }
        }
        $data_atual_etapa = adicionarDiasUteis($data_atual_etapa, 1);
    }
    $data_atual = $data_atual_etapa;

    // 2. Modelagem em lote para Fachada e Imagem Externa
    foreach (['Fachada', 'Imagem Externa'] as $grupoLote) {
        if (!isset($gruposFiltrados[$grupoLote])) continue;
        $stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id, recebimento_arquivos FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00'");
        $stmt->bind_param('is', $obra_id, $grupoLote);
        $stmt->execute();
        $result = $stmt->get_result();
        $imagensLote = [];
        while ($row = $result->fetch_assoc()) {
            $imagensLote[] = $row;
        }
        if (empty($imagensLote)) continue;

        $maxFiltroFim = null;
        foreach ($imagensLote as $img) {
            $filtroFim = $datasEtapas[$img['id']]['Filtro de assets']['data_fim'] ?? $img['recebimento_arquivos'] ?? $data_inicio_gantt;
            if ($filtroFim && ($maxFiltroFim === null || $filtroFim > $maxFiltroFim)) {
                $maxFiltroFim = $filtroFim;
            }
        }
        if (!$maxFiltroFim) continue;
        $data_inicio_modelagem = adicionarDiasUteis($maxFiltroFim, 1);

        $diasModelagem = $grupos[$grupoLote]['Modelagem'];
        $data_fim_modelagem = ($diasModelagem < 1) ? $data_inicio_modelagem : adicionarDiasUteis($data_inicio_modelagem, $diasModelagem - 1);

        foreach ($imagensLote as $img) {
            $datasEtapas[$img['id']]['Modelagem'] = [
                'data_inicio' => $data_inicio_modelagem,
                'data_fim' => $data_fim_modelagem,
                'colaborador_id' => 16,
                'grupo' => $grupoLote
            ];
            inserirGanttEPessoa($conn, $obra_id, $img['id'], $grupoLote, 'Modelagem', $diasModelagem, $data_inicio_modelagem, $data_fim_modelagem, 16);
            $nomeColaborador = buscarNomeColaborador($conn, 16);
            echo "Simular: Colaborador = $nomeColaborador (16) | $grupoLote | imagem {$img['id']} | Modelagem: $data_inicio_modelagem - $data_fim_modelagem\n";
        }
    }

    // 3. Demais etapas (sequencial)
    foreach ($gruposFiltrados as $grupo => $etapas) {
        if ($grupo === "Planta Humanizada") continue;
        $stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id, recebimento_arquivos FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00'");
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        $imagens = [];
        while ($row = $result->fetch_assoc()) {
            $imagens[] = $row;
        }
        usort($imagens, function ($a, $b) {
            return strcmp($a['recebimento_arquivos'], $b['recebimento_arquivos']);
        });

        foreach ($imagens as $img) {
            $colaboradorModelagem = null;

            // INICIALIZA: início das etapas após Caderno/Filtro (ou data do Gantt)
            $fimFiltro = $datasEtapas[$img['id']]['Filtro de assets']['data_fim'] ?? null;
            if (!$fimFiltro) {
                $fimFiltro = $datasEtapas[$img['id']]['Caderno']['data_fim'] ?? $img['recebimento_arquivos'] ?? $data_inicio_gantt;
            }
            $data_inicio_etapa = adicionarDiasUteis($fimFiltro, 1);

            foreach ($etapas as $etapa => $dias) {
                // Pular modelagem de Fachada e Externa (já processado em lote)
                if ($etapa === 'Modelagem' && in_array($grupo, ['Fachada', 'Imagem Externa'])) continue;
                // Pular Caderno/Filtro (já processado em lote)
                if (in_array($etapa, ['Caderno', 'Filtro de assets'])) continue;

                // --- DEPENDÊNCIAS ESPECÍFICAS ---
                // Composição de Fachada só começa após Modelagem e só colaborador 22, respeitando limite diário
                if ($etapa === 'Composição' && $grupo === 'Fachada') {
                    $data_modelagem_fim = $datasEtapas[$img['id']]['Modelagem']['data_fim'];
                    $limite = buscarLimiteColaborador($conn, 22, $etapa);

                    // Procura o próximo dia útil em que o colaborador não atingiu o limite
                    $data_tentativa = adicionarDiasUteis($data_modelagem_fim, 1);
                    while (($agendaColaborador[22][$etapa][$data_tentativa] ?? 0) >= $limite) {
                        $data_tentativa = adicionarDiasUteis($data_tentativa, 1);
                    }
                    $data_inicio_etapa = $data_tentativa;
                    $idsPermitidos = [22];
                    // ATUALIZA O CONTROLE LOCAL IMEDIATAMENTE
                    $agendaColaborador[22][$etapa][$data_inicio_etapa] = ($agendaColaborador[22][$etapa][$data_inicio_etapa] ?? 0) + 1;
                }
                // Finalização de Fachada só colaborador 8, respeitando limite diário
                elseif ($etapa === 'Finalização' && $grupo === 'Fachada') {
                    $limite = buscarLimiteColaborador($conn, 8, $etapa);
                    $data_tentativa = $data_inicio_etapa;
                    while (($agendaColaborador[8][$etapa][$data_tentativa] ?? 0) >= $limite) {
                        $data_tentativa = adicionarDiasUteis($data_tentativa, 1);
                    }
                    $data_inicio_etapa = $data_tentativa;
                    $idsPermitidos = [8];
                }
                // Composição de Imagem Externa só começa após Modelagem e só colaborador 22, respeitando limite diário
                elseif ($etapa === 'Composição' && $grupo === 'Imagem Externa') {
                    $data_modelagem_fim = $datasEtapas[$img['id']]['Modelagem']['data_fim'];
                    $limite = buscarLimiteColaborador($conn, 22, $etapa);

                    $data_tentativa = adicionarDiasUteis($data_modelagem_fim, 1);
                    while (($agendaColaborador[22][$etapa][$data_tentativa] ?? 0) >= $limite) {
                        $data_tentativa = adicionarDiasUteis($data_tentativa, 1);
                    }
                    $data_inicio_etapa = $data_tentativa;
                    $idsPermitidos = [22];
                    $agendaColaborador[22][$etapa][$data_inicio_etapa] = ($agendaColaborador[22][$etapa][$data_inicio_etapa] ?? 0) + 1;
                }

                // Finalização de Imagem Externa só pode ser feita por 8 ou 20, respeitando limite diário
                elseif ($etapa === 'Finalização' && $grupo === 'Imagem Externa') {
                    // Tenta primeiro o 8, depois o 20, ambos respeitando limite
                    $possiveis = [8, 20];
                    $data_tentativa = $data_inicio_etapa;
                    $colabEscolhido = null;
                    foreach ($possiveis as $cid) {
                        $limite = buscarLimiteColaborador($conn, $cid, $etapa);
                        if (($agendaColaborador[$cid][$etapa][$data_tentativa] ?? 0) < $limite) {
                            $colabEscolhido = $cid;
                            break;
                        }
                    }
                    // Se ambos cheios, avança o dia até achar um disponível
                    while (!$colabEscolhido) {
                        $data_tentativa = adicionarDiasUteis($data_tentativa, 1);
                        foreach ($possiveis as $cid) {
                            $limite = buscarLimiteColaborador($conn, $cid, $etapa);
                            if (($agendaColaborador[$cid][$etapa][$data_tentativa] ?? 0) < $limite) {
                                $colabEscolhido = $cid;
                                break 2;
                            }
                        }
                    }
                    $data_inicio_etapa = $data_tentativa;
                    $idsPermitidos = [$colabEscolhido];
                }
                // Composição de Imagem Externa só começa após Modelagem
                elseif ($etapa === 'Composição' && $grupo === 'Imagem Externa') {
                    $data_inicio_etapa = adicionarDiasUteis($datasEtapas[$img['id']]['Modelagem']['data_fim'], 1);
                }

                // --- REGRAS DE COLABORADOR GERAIS ---
                if (!isset($idsPermitidos)) {
                    if ($etapa === 'Modelagem' && in_array($grupo, ['Fachada', 'Imagem Externa'])) {
                        $idsPermitidos = [16];
                    } elseif ($etapa === 'Modelagem' && in_array($grupo, ['Imagem Interna', 'Unidade'])) {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        $idsPermitidos = array_filter($idsPermitidos, fn($c) => $c !== 16);
                        $idsPermitidos = array_values($idsPermitidos);
                        shuffle($idsPermitidos);
                    } elseif ($etapa === 'Modelagem') {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        shuffle($idsPermitidos);
                    } elseif ($etapa === 'Composição') {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        if (in_array($colaboradorModelagem, [5, 32])) {
                            $idsPermitidos = array_filter($idsPermitidos, fn($c) => $c !== 5 && $c !== 32);
                            $idsPermitidos = array_values($idsPermitidos);
                        }
                        shuffle($idsPermitidos);
                    } elseif ($etapa === 'Finalização' && in_array($grupo, ['Imagem Interna', 'Unidade'])) {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        $idsPermitidos = array_filter($idsPermitidos, fn($c) => $c !== 8);
                        $idsPermitidos = array_values($idsPermitidos);
                        shuffle($idsPermitidos);
                    } elseif ($etapa === 'Finalização') {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        shuffle($idsPermitidos);
                    } elseif ($etapa === 'Pós-Produção') {
                        if (!isset($colaboradoresPorEtapaTipo['__posproducao'])) {
                            $colaboradoresPorEtapaTipo['__posproducao'] = buscarPrimeiroColaborador($conn, $etapa, true);
                        }
                        $idsPermitidos = [$colaboradoresPorEtapaTipo['__posproducao']];
                    } else {
                        $idsPermitidos = buscarTodosColaboradores($conn, $etapa);
                        shuffle($idsPermitidos);
                    }
                }

                // --- DISPONIBILIDADE ---
                $disponibilidade = verificarDisponibilidadeColaborador(
                    $conn,
                    $etapa,
                    $data_inicio_etapa,
                    ($dias < 1 ? 1 : $dias),
                    $idsPermitidos
                );
                $data_aloc = $disponibilidade['data_inicio'];
                $colaboradorEscolhido = $disponibilidade['colaborador_id'];

                if ($disponibilidade['freelancer']) {
                    echo "Alerta: Freelancer necessário para $etapa da imagem {$img['id']} em $data_aloc\n";
                }

                // Calcula a data final da etapa conforme os dias definidos no $grupos
                if ($dias < 1) {
                    $data_fim = $data_aloc;
                } else {
                    $data_fim = adicionarDiasUteis($data_aloc, $dias - 1);
                }

                // Salva a etapa para dependência futura
                $datasEtapas[$img['id']][$etapa] = [
                    'data_inicio' => $data_aloc,
                    'data_fim' => $data_fim,
                    'colaborador_id' => $colaboradorEscolhido,
                    'grupo' => $grupo
                ];

                inserirGanttEPessoa($conn, $obra_id, $img['id'], $grupo, $etapa, $dias, $data_aloc, $data_fim, $colaboradorEscolhido);


                // Para a próxima etapa, começa 1 dia útil após o fim da etapa atual
                $data_inicio_etapa = adicionarDiasUteis($data_fim, 1);

                // Marca a alocação na agenda local (opcional, para controle interno)
                if ($colaboradorEscolhido) {
                    $agendaColaborador[$colaboradorEscolhido][$etapa][$data_aloc] = ($agendaColaborador[$colaboradorEscolhido][$etapa][$data_aloc] ?? 0) + 1;
                }

                $nomeColaborador = buscarNomeColaborador($conn, $colaboradorEscolhido);
                echo "Simular: Colaborador = $nomeColaborador ($colaboradorEscolhido) | $grupo | imagem {$img['id']} | $etapa: $data_aloc - $data_fim\n";
                if ($etapa === 'Modelagem') {
                    $colaboradorModelagem = $colaboradorEscolhido;
                }

                // Limpa para próxima etapa
                unset($idsPermitidos);
            }
        }
    }
}

function inserirGanttEPessoa($conn, $obra_id, $imagem_id, $grupo, $etapa, $dias, $data_inicio, $data_fim, $colaborador_id)
{
    $stmtGantt = $conn->prepare("INSERT INTO gantt_prazos 
        (obra_id, imagem_id, tipo_imagem, etapa, dias, data_inicio, data_fim)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim), dias=VALUES(dias)");
    $diasInt = (int)ceil($dias);
    $stmtGantt->bind_param(
        "iississ",
        $obra_id,
        $imagem_id,
        $grupo,
        $etapa,
        $diasInt,
        $data_inicio,
        $data_fim
    );
    $stmtGantt->execute();
    if (!$stmtGantt->execute()) {
        echo "Erro ao inserir em gantt_prazos: " . $stmtGantt->error . "\n";
        var_dump([
            $obra_id,
            $imagem_id,
            $grupo,
            $etapa,
            $diasInt,
            $data_inicio,
            $data_fim
        ]);
    }

    $gantt_id = $stmtGantt->insert_id;
    if ($gantt_id == 0) {
        $stmtBusca = $conn->prepare("SELECT id FROM gantt_prazos WHERE obra_id=? AND tipo_imagem=? AND imagem_id=? AND etapa=?");
        $stmtBusca->bind_param("isis", $obra_id, $grupo, $imagem_id, $etapa);
        $stmtBusca->execute();
        $resBusca = $stmtBusca->get_result();
        $rowBusca = $resBusca->fetch_assoc();
        $gantt_id = $rowBusca['id'] ?? 0;
    }
    if ($colaborador_id) {
        $stmtColab = $conn->prepare("INSERT IGNORE INTO etapa_colaborador (gantt_id, colaborador_id) VALUES (?, ?)");
        $stmtColab->bind_param("ii", $gantt_id, $colaborador_id);
        $stmtColab->execute();
    }
}

function buscarNomeColaborador($conn, $colaborador_id)
{
    $stmt = $conn->prepare("SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ?");
    $stmt->bind_param("i", $colaborador_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['nome_colaborador'] ?? '';
}

// Função para buscar o primeiro colaborador disponível para uma etapa
function buscarPrimeiroColaborador($conn, $etapa, $apenasAtivo = false)
{
    $query = "SELECT fc.colaborador_id
        FROM funcao_colaborador fc
        JOIN funcao f ON fc.funcao_id = f.idfuncao
        JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
        WHERE f.nome_funcao = ? ";
    if ($apenasAtivo) {
        $query .= "AND c.ativo = 1 ";
    }
    $query .= "ORDER BY fc.colaborador_id ASC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $etapa);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['colaborador_id'] ?? null;
}

// Função para buscar o limite do colaborador para uma etapa
function buscarLimiteColaborador($conn, $colaborador_id, $etapa)
{
    $query = "SELECT f.limite
        FROM funcao_colaborador fc
        JOIN funcao f ON fc.funcao_id = f.idfuncao
        WHERE fc.colaborador_id = ? AND f.nome_funcao = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $colaborador_id, $etapa);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['limite'] ?? 1;
}

// Função para adicionar dias úteis (segunda a sexta-feira)
function adicionarDiasUteis($dataInicial, $diasUteis)
{
    $diasAdicionados = 0;
    $data = strtotime($dataInicial);

    $feriadosFixos = [
        '01-01',
        '04-21',
        '05-01',
        '09-07',
        '10-12',
        '11-02',
        '11-15',
        '12-25',
    ];

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime("+1 day", $data);
        $diaSemana = date('N', $data);
        $mesDia = date('m-d', $data);

        if ($diaSemana >= 6) continue;
        if (in_array($mesDia, $feriadosFixos)) continue;

        $diasAdicionados++;
    }

    return date('Y-m-d', $data);
}

function verificarDisponibilidadeColaborador($conn, $etapa, $data_inicio, $dias, $idsPermitidos = null)
{
    $tentativas = 0;
    $data_tentativa = $data_inicio;

    while ($tentativas < 5) {
        $disponivel = colaboradorDisponivel($conn, $etapa, $data_tentativa, $dias, $idsPermitidos);
        if (!$disponivel['freelancer'] && $disponivel['colaborador_id']) {
            return [
                'data_inicio' => $data_tentativa,
                'freelancer' => false,
                'colaborador_id' => $disponivel['colaborador_id']
            ];
        }
        $data_tentativa = adicionarDiasUteis($data_tentativa, 1);
        $tentativas++;
    }

    // Alocar freelancer
    return ['data_inicio' => $data_tentativa, 'freelancer' => true, 'colaborador_id' => null];
}

function colaboradorDisponivel($conn, $etapa, $data_inicio, $dias, $idsPermitidos = null)
{
    $data_fim = adicionarDiasUteis($data_inicio, $dias);

    if ($idsPermitidos) {
        // Testa cada colaborador permitido (já embaralhado)
        foreach ($idsPermitidos as $colaborador_id) {
            // Buscar limite do colaborador para a etapa
            $limite = buscarLimiteColaborador($conn, $colaborador_id, $etapa);

            $stmt_tarefas = $conn->prepare("SELECT COUNT(*) AS total
                FROM gantt_prazos gp
                INNER JOIN etapa_colaborador ec ON ec.gantt_id = gp.id
                WHERE gp.etapa = ? AND ec.colaborador_id = ? AND (
                    (gp.data_inicio BETWEEN ? AND ?) OR
                    (gp.data_fim BETWEEN ? AND ?) OR
                    (? BETWEEN gp.data_inicio AND gp.data_fim)
                )");
            $stmt_tarefas->bind_param("sisssss", $etapa, $colaborador_id, $data_inicio, $data_fim, $data_inicio, $data_fim, $data_inicio);
            $stmt_tarefas->execute();
            $result_tarefas = $stmt_tarefas->get_result();
            $tarefa = $result_tarefas->fetch_assoc();

            if ($tarefa['total'] < $limite) {
                return [
                    'data_inicio' => $data_inicio,
                    'freelancer' => false,
                    'colaborador_id' => $colaborador_id
                ];
            }
        }
    } else {
        // Se não há restrição, busca todos do banco (como antes)
        $query = "SELECT fc.colaborador_id, f.limite
            FROM funcao_colaborador fc
            JOIN funcao f ON fc.funcao_id = f.idfuncao
            JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
            WHERE f.nome_funcao = ? AND c.ativo = 1 AND c.idcolaborador NOT IN (15, 30)
            ORDER BY RAND()";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $etapa);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $colaborador_id = $row['colaborador_id'];
            $limite = $row['limite'];

            $stmt_tarefas = $conn->prepare("SELECT COUNT(*) AS total
                FROM gantt_prazos gp
                INNER JOIN etapa_colaborador ec ON ec.gantt_id = gp.id
                WHERE gp.etapa = ? AND ec.colaborador_id = ? AND (
                    (gp.data_inicio BETWEEN ? AND ?) OR
                    (gp.data_fim BETWEEN ? AND ?) OR
                    (? BETWEEN gp.data_inicio AND gp.data_fim)
                )");
            $stmt_tarefas->bind_param("sisssss", $etapa, $colaborador_id, $data_inicio, $data_fim, $data_inicio, $data_fim, $data_inicio);
            $stmt_tarefas->execute();
            $result_tarefas = $stmt_tarefas->get_result();
            $tarefa = $result_tarefas->fetch_assoc();

            if ($tarefa['total'] < $limite) {
                return [
                    'data_inicio' => $data_inicio,
                    'freelancer' => false,
                    'colaborador_id' => $colaborador_id
                ];
            }
        }
    }

    return [
        'data_inicio' => $data_inicio,
        'freelancer' => true,
        'colaborador_id' => null
    ];
}

function enviarAlertaFreelancer($etapa, $imagem_id)
{
    // Implementar lógica para enviar alerta sobre alocação de freelancer
}

function buscarTodosColaboradores($conn, $etapa)
{
    $query = "SELECT fc.colaborador_id
        FROM funcao_colaborador fc
        JOIN funcao f ON fc.funcao_id = f.idfuncao
        JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
        WHERE f.nome_funcao = ? AND c.ativo = 1 AND c.idcolaborador NOT IN (15, 30)
        ORDER BY fc.colaborador_id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $etapa);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['colaborador_id'];
    }
    return $ids;
}
