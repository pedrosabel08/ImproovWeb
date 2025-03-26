<?php
include '../conexao.php'; // Conexão com o banco

// Recebe os dados enviados via AJAX
$data = json_decode(file_get_contents("php://input"), true);
$obraId = $data["obraId"];
$tipos = $data["tipos"];
$dataArquivo = $data["data_arquivos"]; // Data inicial enviada pelo JSON

header('Content-Type: application/json'); // Define o tipo de resposta como JSON

// Mapear os nomes dos tipos de imagem para os valores esperados no banco de dados
$tipoImagemMap = [
    "Fachada" => "fachada",
    "Imagem Interna" => "internas_comuns",
    "Imagem Externa" => "imagens_externas",
    "Unidades" => "unidades",
    "Planta Humanizada" => "ph"
];

// Converter os tipos recebidos para os valores esperados no banco
$tiposConvertidos = array_map(function ($tipo) use ($tipoImagemMap) {
    return $tipoImagemMap[$tipo] ?? $tipo; // Retorna o valor mapeado ou o original se não encontrado
}, $tipos);

// Buscar o campo dias_uteis da tabela obra
$sqlObra = "SELECT dias_uteis FROM obra WHERE idobra = ?";
$stmtObra = $conn->prepare($sqlObra);
$stmtObra->bind_param("i", $obraId);
$stmtObra->execute();
$resultObra = $stmtObra->get_result();
$obra = $resultObra->fetch_assoc();

if (!$obra) {
    echo json_encode([
        "success" => false,
        "message" => "Obra não encontrada!"
    ]);
    exit;
}

$diasUteis = $obra['dias_uteis'];

// Função para adicionar dias úteis a uma data
function adicionarDiasUteis($dataInicial, $dias)
{
    $data = strtotime($dataInicial);
    $adicionados = 0;

    while ($adicionados < $dias) {
        $data = strtotime('+1 day', $data);
        $diaSemana = date('N', $data); // 1 (segunda-feira) a 7 (domingo)

        if ($diaSemana < 6) { // Ignorar sábado (6) e domingo (7)
            $adicionados++;
        }
    }

    return date('Y-m-d', $data);
}

// Calcular o novo prazo com base nos dias úteis e na data inicial (dataArquivo)
$novoPrazo = adicionarDiasUteis($dataArquivo, $diasUteis);

// Atualizar o prazo e o recebimento_arquivos das imagens com os tipos selecionados
foreach ($tiposConvertidos as $tipo) {
    // Atualizar o prazo
    $sqlPrazo = "UPDATE imagens_cliente_obra SET prazo = ? WHERE obra_id = ? AND tipo_imagem = ?";
    $stmtPrazo = $conn->prepare($sqlPrazo);
    $stmtPrazo->bind_param("sis", $novoPrazo, $obraId, $tipo);
    $stmtPrazo->execute();

    // Atualizar o recebimento_arquivos
    $sqlRecebimento = "UPDATE imagens_cliente_obra SET recebimento_arquivos = ? WHERE obra_id = ? AND tipo_imagem = ?";
    $stmtRecebimento = $conn->prepare($sqlRecebimento);
    $stmtRecebimento->bind_param("sis", $dataArquivo, $obraId, $tipo);
    $stmtRecebimento->execute();
}

// Retornar a resposta como JSON
echo json_encode([
    "success" => true,
    "message" => "Prazo e recebimento atualizados com sucesso!",
    "novoPrazo" => $novoPrazo
]);
