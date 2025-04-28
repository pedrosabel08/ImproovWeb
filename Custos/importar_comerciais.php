<?php
// Inclui o arquivo de conexão
include '../conexao.php';

// Função para buscar o id pelo nome
function buscarImagemId($conn, $imagemNome)
{
    $stmt = $conn->prepare("SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE imagem_nome = ?");
    $stmt->bind_param("s", $imagemNome);
    $stmt->execute();
    $result = $stmt->get_result();
    $resultado = $result->fetch_assoc();
    $stmt->close();
    return $resultado ? $resultado['idimagens_cliente_obra'] : null;
}

// Ler o CSV
if (($handle = fopen('CAP_TIV - Custos.csv', 'r')) !== false) {
    fgetcsv($handle); // Ignorar a primeira linha (cabeçalho)

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        $numero_contrato = $data[0];
        $imagem_nome = $data[1];
        $valor = $data[2];
        $imposto = $data[3];
        $valor_imposto = $data[4];
        $comissao_comercial = $data[5];
        $valor_comissao_comercial = $data[6];

        $imagem_id = buscarImagemId($conn, trim($imagem_nome));

        if ($imagem_id) {
            // Faz o INSERT na tabela imagem_comercial
            $stmt = $conn->prepare("INSERT INTO imagem_comercial 
                (numero_contrato, imagem_id, valor, imposto, valor_imposto, comissao_comercial, valor_comissao_comercial) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                "sidsdds",
                $numero_contrato,
                $imagem_id,
                $valor,
                $imposto,
                $valor_imposto,
                $comissao_comercial,
                $valor_comissao_comercial
            );

            $stmt->execute();
            $stmt->close();

            echo "Inserido: {$imagem_nome} (ID: {$imagem_id})\n";
        } else {
            echo "Imagem não encontrada: {$imagem_nome}\n";
        }
    }
    fclose($handle);
} else {
    echo "Erro ao abrir o arquivo CSV.";
}

// Fecha a conexão
$conn->close();
