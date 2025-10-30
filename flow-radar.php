<?php
header('Content-Type: application/json');
// Flow Radar - Buscar oportunidades para um colaborador e função
// Tenta incluir arquivos de conexão conhecidos do projeto e garante que a
// função conectarBanco() esteja disponível antes de usá-la.
if (file_exists(__DIR__ . '/conexao.php')) {
    include_once __DIR__ . '/conexao.php';
}

// fallback para conexaoMain.php (alguns arquivos usam esse nome)
if (!function_exists('conectarBanco') && file_exists(__DIR__ . '/conexaoMain.php')) {
    include_once __DIR__ . '/conexaoMain.php';
}

if (!function_exists('conectarBanco')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Função conectarBanco() não encontrada. Verifique os arquivos de conexão.']);
    exit;
}

$conn = conectarBanco();

$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
$funcao_id = isset($_GET['funcao_id']) ? (int)$_GET['funcao_id'] : 0;

if (!$colaborador_id || !$funcao_id) {
    echo json_encode(['error' => 'Parâmetros obrigatórios: colaborador_id e funcao_id']);
    exit;
}

try {
    // 1) Estatísticas rápidas do colaborador
    // Tarefas concluídas hoje (baseado em historico de aprovações)
    $sql_done_today = "SELECT COUNT(DISTINCT h.funcao_imagem_id) AS concluidas
        FROM historico_aprovacoes h
        WHERE h.colaborador_id = ? AND DATE(h.data_aprovacao) = CURDATE() AND h.status_novo IN ('Aprovado','Aprovado com Ajustes','Finalizado')";

    $stmt = $conn->prepare($sql_done_today);
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $concluidasHoje = (int)($res['concluidas'] ?? 0);
    $stmt->close();

    // Ajustes pendentes (tarefas atribuídas ao colaborador com status de ajuste)
    $sql_ajustes = "SELECT COUNT(*) AS ajustes FROM funcao_imagem WHERE colaborador_id = ? AND (status = 'Ajuste' OR status = 'Aprovado com Ajustes')";
    $stmt = $conn->prepare($sql_ajustes);
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $ajustesPendentes = (int)($res['ajustes'] ?? 0);
    $stmt->close();

    // Taxa de aprovação (simples: proporção de aprovações no historico do colaborador)
    $sql_taxa = "SELECT
        ROUND( (SUM(CASE WHEN h.status_novo IN ('Aprovado','Aprovado com Ajustes','Finalizado') THEN 1 ELSE 0 END) / GREATEST(COUNT(*),1)) * 100 ) AS taxa
        FROM historico_aprovacoes h
        JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
        WHERE fi.colaborador_id = ?";

    $stmt = $conn->prepare($sql_taxa);
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $taxaAprovacao = (int)($res['taxa'] ?? 0);
    $stmt->close();

    // 2) Buscar oportunidades: função atual, sem colaborador, função anterior aprovada
    // Critérios:
    // - fi.funcao_id = :funcao_id
    // - fi.colaborador_id IS NULL OR 0
    // - obra ativa (o.status_obra = 0)
    // - existir funcao anterior aprovada

    // Seleciona a partir de imagens_cliente_obra para incluir casos em que ainda
    // não existe um registro em funcao_imagem para a próxima função.
    $sql = "SELECT
        fi.idfuncao_imagem,
        COALESCE(fi.colaborador_id, 0) AS colaborador_id,
        i.idimagens_cliente_obra AS imagem_id,
        i.imagem_nome,
        o.nome_obra,
        COALESCE(fi.prazo, '9999-12-31') AS prazo,
        COALESCE(pc.prioridade, 3) AS prioridade,
        (
            SELECT h.status_novo
            FROM historico_aprovacoes h
            WHERE h.funcao_imagem_id = (
                SELECT fp.idfuncao_imagem
                FROM funcao_imagem fp
                WHERE fp.imagem_id = i.idimagens_cliente_obra AND fp.funcao_id < ?
                ORDER BY fp.funcao_id DESC
                LIMIT 1
            )
            ORDER BY h.data_aprovacao DESC
            LIMIT 1
        ) AS status_anterior
    FROM imagens_cliente_obra i
    JOIN obra o ON i.obra_id = o.idobra
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = i.idimagens_cliente_obra AND fi.funcao_id = ?
    LEFT JOIN prioridade_funcao pc ON pc.funcao_imagem_id = fi.idfuncao_imagem
    WHERE o.status_obra = 0
      AND i.tipo_imagem <> 'Planta Humanizada'
      AND EXISTS (
          SELECT 1 FROM funcao_imagem fp2
          WHERE fp2.imagem_id = i.idimagens_cliente_obra AND fp2.funcao_id < ?
          AND EXISTS (
              SELECT 1 FROM historico_aprovacoes h2
              WHERE h2.funcao_imagem_id = fp2.idfuncao_imagem
                AND h2.status_novo IN ('Aprovado','Aprovado com Ajustes','Finalizado')
          )
      )
      -- incluir quando não há registro de funcao_imagem para a próxima função
      AND (fi.idfuncao_imagem IS NULL OR fi.colaborador_id IS NULL OR fi.colaborador_id = 0)
    ORDER BY prioridade ASC, prazo ASC
    LIMIT 100";

    $stmt = $conn->prepare($sql);
    // bind três vezes: usado em subquery e no LEFT JOIN e na condição EXISTS
    $stmt->bind_param('iii', $funcao_id, $funcao_id, $funcao_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $oportunidades = [];
    while ($row = $result->fetch_assoc()) {
        $oportunidades[] = [
            'idfuncao_imagem' => (int)$row['idfuncao_imagem'],
            'imagem_id' => (int)$row['imagem_id'],
            'imagem_nome' => $row['imagem_nome'],
            'obra' => $row['nome_obra'],
            'prazo' => $row['prazo'],
            'prioridade' => (int)$row['prioridade'],
            'status_anterior' => $row['status_anterior'] ?? null
        ];
    }
    $stmt->close();

    echo json_encode([
        'colaborador_id' => $colaborador_id,
        'funcao_id' => $funcao_id,
        'stats' => [
            'ocioso_horas' => null, // cálculo de horas ociosas pode ser implementado posteriormente
            'tarefas_concluidas_hoje' => $concluidasHoje,
            'ajustes_pendentes' => $ajustesPendentes,
            'taxa_aprovacao' => $taxaAprovacao
        ],
        'oportunidades_count' => count($oportunidades),
        'oportunidades' => $oportunidades
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar oportunidades', 'message' => $e->getMessage()]);
    exit;
}

?>
