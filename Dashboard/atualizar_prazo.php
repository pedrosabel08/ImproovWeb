<?php
include '../conexao.php'; // Inclui o arquivo de conexão

// Receber os dados do AJAX
$data = json_decode(file_get_contents('php://input'), true);

// Verifica se os dados foram recebidos corretamente
if (!isset($data['obraId']) || !isset($data['tiposSelecionados']) || !isset($data['dataArquivos'])) {
    echo "Dados inválidos!";
    exit;
}

// Extrair dados recebidos
$obraId = $data['obraId'];
$tiposSelecionados = $data['tiposSelecionados'];

foreach ($tiposSelecionados as $tipo) {
    $tipoImagem = $tipo['tipo'];
    $dataRecebimento = $tipo['dataRecebimento'];
    $subtipos = $tipo['subtipos'];

    // Verificar se o tipo de imagem já existe para essa obra
    $stmt = $conn->prepare("SELECT id FROM arquivos WHERE obra_id = ? AND tipo = ?");
    $stmt->bind_param('is', $obraId, $tipoImagem);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingArquivo = $result->fetch_assoc();

    // Atribuir os valores dos subtipos a variáveis (default: 0)
    $dwg = isset($subtipos['DWG']) ? 1 : 0;
    $pdf = isset($subtipos['PDF']) ? 1 : 0;
    $trid = isset($subtipos['3D']) || isset($subtipos['Referências/Mood']) ? 1 : 0;
    $paisagismo = isset($subtipos['Paisagismo']) ? 1 : 0;
    $luminotecnico = isset($subtipos['Luminotécnico']) ? 1 : 0;
    $unidadesDefinidas = isset($subtipos['Unidades Definidas']) ? 1 : 0;

    if ($existingArquivo) {
        // Atualizar os dados existentes
        $arquivoId = $existingArquivo['id'];
        $updateStmt = $conn->prepare("
            UPDATE arquivos
            SET data_recebimento = ?, dwg = ?, pdf = ?, trid = ?, paisagismo = ?, luminotecnico = ?, unidades_definidas = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param(
            'siiiiiii',
            $dataRecebimento,
            $dwg,
            $pdf,
            $trid,
            $paisagismo,
            $luminotecnico,
            $unidadesDefinidas,
            $arquivoId
        );
        $updateStmt->execute();
    } else {
        // Inserir um novo registro
        $insertStmt = $conn->prepare("
            INSERT INTO arquivos (obra_id, tipo, dwg, pdf, trid, paisagismo, luminotecnico, unidades_definidas, data_recebimento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param(
            'isiiiiiss',
            $obraId,
            $tipoImagem,
            $dwg,
            $pdf,
            $trid,
            $paisagismo,
            $luminotecnico,
            $unidadesDefinidas,
            $dataRecebimento
        );
        $insertStmt->execute();
    }
}

// Função para adicionar dias úteis (segunda a sexta-feira)
function adicionarDiasUteis($dataInicial, $diasUteis)
{
    $diasAdicionados = 0;
    $data = strtotime($dataInicial);

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime("+1 day", $data);

        // Verificar se o novo dia é útil (segunda a sexta)
        if (date('N', $data) < 6) {
            $diasAdicionados++;
        }
    }

    return date('Y-m-d', $data);
}

// Preparar o SQL para buscar a última data de recebimento por tipo de imagem
$queryTipoData = "SELECT a.tipo, MAX(a.data_recebimento) AS ultima_data 
FROM arquivos AS a
WHERE a.obra_id = ? 
    AND a.data_recebimento IS NOT NULL 
    AND a.data_recebimento != '0000-00-00'
    AND (
        (a.tipo = 'Fachada' AND a.dwg = 1 AND a.pdf = 1 AND a.trid = 1 AND a.paisagismo = 1)
        OR
        (a.tipo = 'Imagem Externa' AND a.dwg = 1 AND a.pdf = 1 AND (a.trid = 1 OR a.paisagismo = 1))
        OR
        (a.tipo = 'Imagem Interna' AND a.dwg = 1 AND a.pdf = 1 AND (a.trid = 1 OR a.luminotecnico = 1))
        OR
        (a.tipo = 'Unidades' AND a.dwg = 1 AND a.pdf = 1 AND (a.trid = 1 OR a.unidades_definidas = 1) AND a.luminotecnico = 1)
    )
GROUP BY a.tipo
";
$stmt = $conn->prepare($queryTipoData);
$stmt->bind_param('i', $obraId);
$stmt->execute();
$result = $stmt->get_result();

// Atualizar os prazos e datas de recebimento para cada tipo de imagem
while ($row = $result->fetch_assoc()) {
    $tipoImagem = $row['tipo'];
    $dataRecebimento = $row['ultima_data'];

    // Buscar os dias úteis da obra
    $queryObra = "SELECT dias_uteis FROM obra WHERE idobra = ?";
    $stmtObra = $conn->prepare($queryObra);
    $stmtObra->bind_param('i', $obraId);
    $stmtObra->execute();
    $resultObra = $stmtObra->get_result();
    $obra = $resultObra->fetch_assoc();
    $diasUteis = $obra['dias_uteis'] ?? 0;

    // Calcular o novo prazo (adicionando dias úteis)
    $novoPrazo = adicionarDiasUteis($dataRecebimento, $diasUteis);

    // Atualizar a tabela `imagens_cliente_obra`
    $updatePrazoStmt = $conn->prepare("UPDATE imagens_cliente_obra AS ico
        JOIN arquivos AS a ON ico.obra_id = a.obra_id
        SET 
            ico.recebimento_arquivos = ?,
            ico.prazo = ?
        WHERE a.tipo = ? AND a.obra_id = ?
    ");
    $updatePrazoStmt->bind_param('sssi', $dataRecebimento, $novoPrazo, $tipoImagem, $obraId);
    $updatePrazoStmt->execute();
}

// Agora que os prazos foram atualizados, geramos o Gantt
function gerarGantt($conn, $obra_id, $grupos)
{
    // Buscar a data de recebimento de arquivos
    $stmt = $conn->prepare("SELECT recebimento_arquivos FROM imagens_cliente_obra WHERE obra_id = ?");
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data_recebimento = $row['recebimento_arquivos'] ?? null;

    if (!$data_recebimento) {
        echo "Nenhuma data de recebimento encontrada para obra $obra_id.";
        return;
    }

    foreach ($grupos as $grupo => $etapas) {
        // Buscar quantas imagens existem para multiplicar a finalização
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ?");
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        $quantidade_imagens = $result->fetch_assoc()['total'] ?? 1; // Pelo menos 1

        $data_inicio = $data_recebimento;

        foreach ($etapas as $etapa => $dias) {
            // Multiplicar os dias da finalização pelo número de imagens
            if (strpos($etapa, 'Finalização') !== false) {
                $dias *= $quantidade_imagens;
            }

            // Calcular data final somando apenas dias úteis
            $data_fim = adicionarDiasUteis($data_inicio, $dias);

            // Inserir na tabela `gantt_prazos`
            $stmt = $conn->prepare("
                INSERT INTO gantt_prazos (obra_id, grupo, etapa, dias, data_inicio, data_fim) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ississ', $obra_id, $grupo, $etapa, $dias, $data_inicio, $data_fim);
            $stmt->execute();

            // A próxima etapa começa no dia seguinte ao término da anterior
            $data_inicio = adicionarDiasUteis($data_fim, 1);
        }
    }
}

// Definição das etapas e seus prazos
$grupos = [
    "Fachada" => [
        "Modelagem Fachada" => 7,
        "Finalização Fachada" => 2,
        "Pós-Produção Fachada" => 1
    ],
    "Imagem Externa" => [
        "Cadernos imagens Externas" => 1,
        "Modelagem Imagens Externas" => 7,
        "Finalização Imagens Externas" => 1,
        "Pós-Produção Imagens Externas" => 1
    ],
    "Imagem Interna" => [
        "Cadernos imagens internas comuns" => 1,
        "Modelagem imagens internas comuns" => 1,
        "Finalização imagens internas comuns" => 1,
        "Pós-Produção imagens internas comuns" => 1
    ],
    "Unidades" => [
        "Cadernos imagens internas unidades" => 1,
        "Modelagem imagens internas unidades" => 1,
        "Finalização imagens internas unidades" => 1,
        "Pós-Produção imagens internas unidades" => 1
    ]
];

// Agora chamamos a função **uma única vez** após processar todas as imagens
gerarGantt($conn, $obraId, $grupos);


echo "Dados atualizados com sucesso!";
