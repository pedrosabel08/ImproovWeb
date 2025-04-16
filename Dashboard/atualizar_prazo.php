<?php
include '../conexao.php'; // Inclui o arquivo de conexão

// Receber os dados do AJAX
$data = json_decode(file_get_contents('php://input'), true);

// Verifica se os dados foram recebidos corretamente
if (!isset($data['obraId']) || !isset($data['tiposSelecionados'])) {
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
    if (!$stmt) {
        die("Erro ao preparar statement: " . $conn->error);
    }
    $stmt->bind_param('is', $obraId, $tipoImagem);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingArquivo = $result->fetch_assoc();

    // Atribuir os valores dos subtipos a variáveis (default: 0)
    $dwg = isset($subtipos['DWG']) ? 1 : 0;
    $pdf = isset($subtipos['PDF']) ? 1 : 0;
    $trid = isset($subtipos['3D ou Referências/Mood']) ? 1 : 0;
    $paisagismo = isset($subtipos['Paisagismo']) ? 1 : 0;
    $luminotecnico = isset($subtipos['Luminotécnico']) ? 1 : 0;
    $unidadesDefinidas = isset($subtipos['Unidades Definidas']) ? 1 : 0;

    if ($existingArquivo) {
        // Atualizar os dados existentes
        $arquivoId = $existingArquivo['id'];
        $updateStmt = $conn->prepare("UPDATE arquivos
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
        $insertStmt = $conn->prepare("INSERT INTO arquivos (obra_id, tipo, dwg, pdf, trid, paisagismo, luminotecnico, unidades_definidas, data_recebimento)
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

    // Lista de feriados fixos (formato MM-DD)
    $feriadosFixos = [
        '01-01', // Confraternização Universal
        '04-21', // Tiradentes
        '05-01', // Dia do Trabalho
        '09-07', // Independência do Brasil
        '10-12', // Nossa Senhora Aparecida
        '11-02', // Finados
        '11-15', // Proclamação da República
        '12-25', // Natal
    ];

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime("+1 day", $data);
        $diaSemana = date('N', $data);
        $mesDia = date('m-d', $data);
        $ano = date('Y', $data);

        // Verifica se é final de semana
        if ($diaSemana >= 6) continue;

        // Verifica se é feriado fixo
        if (in_array($mesDia, $feriadosFixos)) continue;

        // Verifica se é feriado móvel (tipo Páscoa, Corpus Christi...)
        if (in_array(date('Y-m-d', $data), feriadosMoveis($ano))) continue;

        $diasAdicionados++;
    }

    return date('Y-m-d', $data);
}

function feriadosMoveis($ano)
{
    // Cálculo da Páscoa
    $pascoa = easter_date($ano);
    $dataPascoa = date('Y-m-d', $pascoa);

    // Feriados móveis com base na Páscoa
    $feriados = [
        $dataPascoa, // Páscoa
        date('Y-m-d', strtotime('-2 days', $pascoa)), // Sexta-feira Santa
        date('Y-m-d', strtotime('+60 days', $pascoa)), // Corpus Christi
        date('Y-m-d', strtotime('+47 days', $pascoa)), // Ascensão
        date('Y-m-d', strtotime('-48 days', $pascoa)), // Carnaval (terça-feira)
        date('Y-m-d', strtotime('-49 days', $pascoa)), // Segunda de Carnaval (opcional)
    ];

    return $feriados;
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
        (a.tipo = 'Unidade' AND a.dwg = 1 AND a.pdf = 1 AND (a.trid = 1 OR a.unidades_definidas = 1) AND a.luminotecnico = 1)
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
    JOIN arquivos AS a 
        ON ico.obra_id = a.obra_id 
        AND ico.tipo_imagem = a.tipo 
        AND a.data_recebimento = ?
    SET 
        ico.recebimento_arquivos = ?,
        ico.prazo = ?
    WHERE a.tipo = ? AND a.obra_id = ?
    ");
    $updatePrazoStmt->bind_param('ssssi', $dataRecebimento, $dataRecebimento, $novoPrazo, $tipoImagem, $obraId);
    $updatePrazoStmt->execute();
}

// Agora que os prazos foram atualizados, geramos o Gantt
function gerarGantt($conn, $obra_id, $grupos)
{
    // Buscar os tipos de imagem que possuem data de recebimento
    $stmt = $conn->prepare("SELECT DISTINCT tipo_imagem FROM imagens_cliente_obra WHERE obra_id = ? AND recebimento_arquivos IS NOT NULL");
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Criar uma lista de tipos de imagem que possuem dados de recebimento
    $tiposComRecebimento = [];
    while ($row = $result->fetch_assoc()) {
        $tiposComRecebimento[] = $row['tipo_imagem'];
    }

    // Filtrar os grupos para processar apenas os tipos com recebimento
    $gruposFiltrados = array_filter($grupos, function ($grupo) use ($tiposComRecebimento) {
        return in_array($grupo, $tiposComRecebimento);
    }, ARRAY_FILTER_USE_KEY);

    foreach ($gruposFiltrados as $grupo => $etapas) {
        // Buscar a data de recebimento para o tipo de imagem
        $stmt = $conn->prepare("SELECT recebimento_arquivos FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00' LIMIT 1");
        if (!$stmt) {
            die("Erro ao preparar statement: " . $conn->error);
        }
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $data_recebimento = $row['recebimento_arquivos'] ?? null;

        if (!$data_recebimento) {
            continue; // Pular se não houver data de recebimento
        }

        // Buscar quantas imagens existem para multiplicar a finalização
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ?");
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        $quantidade_imagens = $result->fetch_assoc()['total'] ?? 1; // Pelo menos 1

        $data_inicio = $data_recebimento;

        foreach ($etapas as $etapa => $dias) {
            // Verificar se é "Modelagem" para "Fachada" ou "Imagem Externa"
            if ($etapa === "Modelagem" && ($grupo === "Fachada" || $grupo === "Imagem Externa")) {
                // Não multiplicar os dias para "Modelagem" de "Fachada" ou "Imagem Externa"
                $diasCalculados = $dias;
            } else {
                // Multiplicar os dias da finalização pelo número de imagens
                $diasCalculados = $dias * $quantidade_imagens;
            }

            // Calcular data final somando apenas dias úteis
            $data_fim = adicionarDiasUteis($data_inicio, $diasCalculados);

            // Inserir na tabela `gantt_prazos`
            $stmt = $conn->prepare("INSERT INTO gantt_prazos (obra_id, tipo_imagem, etapa, dias, data_inicio, data_fim) 
                VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                die("Erro ao preparar statement: " . $conn->error);
            }
            $stmt->bind_param('ississ', $obra_id, $grupo, $etapa, $diasCalculados, $data_inicio, $data_fim);
            $stmt->execute();

            // A próxima etapa começa no dia seguinte ao término da anterior
            $data_inicio = adicionarDiasUteis($data_fim, 1);
        }
    }
}

// Definição das etapas e seus prazos
$grupos = [
    "Fachada" => [
        "Modelagem" => 7,
        "Finalização" => 2,
        "Pós-Produção" => 0.2
    ],
    "Imagem Externa" => [
        "Caderno" => 0.5,
        "Modelagem" => 7,
        "Composição" => 1,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Imagem Interna" => [
        "Caderno" => 0.5,
        "Modelagem" => 0.5,
        "Composição" => 1,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ],
    "Unidade" => [
        "Caderno" => 0.5,
        "Modelagem" => 0.5,
        "Composição" => 1,
        "Finalização" => 1,
        "Pós-Produção" => 0.2
    ]
];

// Agora chamamos a função **uma única vez** após processar todas as imagens
gerarGantt($conn, $obraId, $grupos);


echo "Dados atualizados com sucesso!";
