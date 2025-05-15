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

    $diasUteisContados = []; // Array para armazenar os dias úteis

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
        $dataFormatada = date('Y-m-d', $data);
        $ano = date('Y', $data);

        if ($diaSemana >= 6) continue;
        if (in_array($mesDia, $feriadosFixos)) continue;
        if (in_array($dataFormatada, feriadosMoveis($ano))) continue;

        $diasAdicionados++;
        $diasUteisContados[] = $dataFormatada;
    }


    return end($diasUteisContados); // Retorna o último dia útil
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

// Verificar se existe alguma data de recebimento válida para os outros tipos (base para Planta Humanizada)
$stmt = $conn->prepare("SELECT MAX(a.data_recebimento) AS maior_recebimento 
    FROM arquivos AS a
    WHERE a.obra_id = ? 
      AND a.data_recebimento IS NOT NULL 
      AND a.data_recebimento != '0000-00-00'
");
$stmt->bind_param('i', $obraId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$maiorRecebimento = $row['maior_recebimento'] ?? null;

if ($maiorRecebimento) {
    // Buscar os dias úteis da obra
    $queryObra = "SELECT dias_uteis FROM obra WHERE idobra = ?";
    $stmtObra = $conn->prepare($queryObra);
    $stmtObra->bind_param('i', $obraId);
    $stmtObra->execute();
    $resultObra = $stmtObra->get_result();
    $obra = $resultObra->fetch_assoc();
    $diasUteis = $obra['dias_uteis'] ?? 0;

    // Calcular novo prazo para Planta Humanizada
    $prazoPlantaHumanizada = adicionarDiasUteis($maiorRecebimento, $diasUteis);

    // Atualizar imagens_cliente_obra para o tipo Planta Humanizada
    $updatePH = $conn->prepare("UPDATE imagens_cliente_obra 
        SET recebimento_arquivos = ?, prazo = ? 
        WHERE obra_id = ? AND tipo_imagem = 'Planta Humanizada'
    ");
    $updatePH->bind_param('ssi', $maiorRecebimento, $prazoPlantaHumanizada, $obraId);
    $updatePH->execute();
}

// Agora que os prazos foram atualizados, geramos o Gantt
function gerarGantt($conn, $obra_id, $grupos)
{
    // Buscar os tipos de imagem que possuem data de recebimento
    $stmt = $conn->prepare("SELECT DISTINCT tipo_imagem FROM imagens_cliente_obra WHERE obra_id = ? AND recebimento_arquivos IS NOT NULL");
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

    $maiorDataCaderno = null;

    foreach ($gruposFiltrados as $grupo => $etapas) {
        if ($grupo === "Planta Humanizada") continue; // Ignora por enquanto

        // Obter todos os imagem_id do tipo atual
        $stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ?");
        $stmt->bind_param('is', $obra_id, $grupo);
        $stmt->execute();
        $result = $stmt->get_result();
        $imagens_ids = [];
        while ($row = $result->fetch_assoc()) {
            $imagens_ids[] = $row['id'];
        }

        foreach ($imagens_ids as $imagem_id) {
            // Buscar data de recebimento específica para essa imagem
            $stmt = $conn->prepare("SELECT recebimento_arquivos FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00' LIMIT 1");
            $stmt->bind_param('i', $imagem_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $data_recebimento_imagem = $row['recebimento_arquivos'] ?? null;

            if (!$data_recebimento_imagem) continue; // Pula se não tiver data válida

            $data_inicio = $data_recebimento_imagem;

            foreach ($etapas as $etapa => $dias) {
                $diasCalculados = $dias;
                if ($etapa === "Modelagem" && ($grupo === "Fachada" || $grupo === "Imagem Externa")) {
                    $diasCalculados = $dias;
                }

                $data_fim = adicionarDiasUteis($data_inicio, $diasCalculados);

                $stmt = $conn->prepare("INSERT INTO gantt_prazos (obra_id, tipo_imagem, imagem_id, etapa, dias, data_inicio, data_fim)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                            dias = VALUES(dias),
                                            data_inicio = VALUES(data_inicio),
                                            data_fim = VALUES(data_fim)");
                $stmt->bind_param('isissss', $obra_id, $grupo, $imagem_id, $etapa, $diasCalculados, $data_inicio, $data_fim);

                if (!$stmt->execute()) {
                    echo "Erro ao inserir Gantt para $grupo - $etapa - imagem_id $imagem_id: " . $stmt->error;
                }

                // Se a etapa for "Caderno", registrar a maior data_fim (considerando todas as imagens)
                if ($etapa === "Caderno") {
                    if (!$maiorDataCaderno || $data_fim > $maiorDataCaderno) {
                        $maiorDataCaderno = $data_fim;
                    }
                }

                $data_inicio = adicionarDiasUteis($data_fim, 1);
            }
        }
    }

    // Agora processa Planta Humanizada, se tiver recebido arquivos
    if (in_array("Planta Humanizada", $tiposComRecebimento) && isset($grupos['Planta Humanizada'])) {
        $stmt = $conn->prepare("SELECT recebimento_arquivos FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = 'Planta Humanizada' AND recebimento_arquivos IS NOT NULL AND recebimento_arquivos != '0000-00-00' LIMIT 1");
        $stmt->bind_param('i', $obra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $data_recebimento_planta = $row['recebimento_arquivos'] ?? null;

        if ($data_recebimento_planta && $maiorDataCaderno) {
            $stmt = $conn->prepare("SELECT idimagens_cliente_obra as id FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = 'Planta Humanizada'");
            $stmt->bind_param('i', $obra_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $imagem_ids_planta = [];
            while ($row = $result->fetch_assoc()) {
                $imagem_ids_planta[] = $row['id'];
            }

            foreach ($imagem_ids_planta as $imagem_id) {
                $data_inicio = adicionarDiasUteis($maiorDataCaderno, 1); // Começa após o último caderno

                foreach ($grupos['Planta Humanizada'] as $etapa => $dias) {
                    $data_fim = adicionarDiasUteis($data_inicio, $dias);
                    $tipo_imagem = 'Planta Humanizada';

                    $stmt = $conn->prepare("INSERT INTO gantt_prazos (obra_id, imagem_id, tipo_imagem, etapa, dias, data_inicio, data_fim)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)
                                            ON DUPLICATE KEY UPDATE
                                                dias = VALUES(dias),
                                                data_inicio = VALUES(data_inicio),
                                                data_fim = VALUES(data_fim)");
                    $stmt->bind_param('iississ', $obra_id, $imagem_id, $tipo_imagem, $etapa, $dias, $data_inicio, $data_fim);

                    if (!$stmt->execute()) {
                        echo "Erro ao inserir Gantt para Planta Humanizada (imagem $imagem_id) - $etapa: " . $stmt->error;
                    }

                    $data_inicio = adicionarDiasUteis($data_fim, 1); // Próxima etapa começa depois da anterior
                }
            }
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

// Agora chamamos a função **uma única vez** após processar todas as imagens
gerarGantt($conn, $obraId, $grupos);
