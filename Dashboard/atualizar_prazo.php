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
$dataArquivos = $data['dataArquivos'];
$tiposSelecionados = $data['tiposSelecionados'];

// Processar cada tipo de imagem
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

    if ($existingArquivo) {
        // Se já existe, vamos atualizar os dados
        $arquivoId = $existingArquivo['id'];

        // Atribuir os valores dos subtipos a variáveis
        $dwg = $subtipos['DWG'] ?? false;
        $pdf = $subtipos['PDF'] ?? false;
        $trid = $subtipos['3D'] ?? false;
        $paisagismo = $subtipos['Paisagismo'] ?? false;
        $luminotecnico = $subtipos['Luminotécnico'] ?? false;
        $unidadesDefinidas = $subtipos['Unidades Definidas'] ?? false;

        // Atualizar os subtipos e data de recebimento
        $updateStmt = $conn->prepare("
            UPDATE arquivos
            SET data_recebimento = ?, dwg = ?, pdf = ?, trid = ?, paisagismo = ?, luminotecnico = ?, unidades_definidas = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param(
            'sssssssi',
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
        // Caso contrário, insira um novo registro
        $dwg = $subtipos['DWG'] ?? false;
        $pdf = $subtipos['PDF'] ?? false;
        $trid = $subtipos['3D ou Referências/Mood'] ?? false;
        $paisagismo = $subtipos['Paisagismo'] ?? false;
        $luminotecnico = $subtipos['Luminotécnico'] ?? false;
        $unidadesDefinidas = $subtipos['Unidades Definidas'] ?? false;

        $insertStmt = $conn->prepare("
            INSERT INTO arquivos (obra_id, tipo, dwg, pdf, trid, paisagismo, luminotecnico, unidades_definidas, data_recebimento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param(
            'issssssss',
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

echo "Dados atualizados com sucesso!";
