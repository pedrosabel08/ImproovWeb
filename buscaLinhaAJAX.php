<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idImagemSelecionada = $_GET['ajid'];

    // Consulta para buscar funções, colaboradores, prazos e status
    $sqlFuncao = "SELECT 
                    img.imagem_nome,
                    f.nome_funcao, 
                    col.idcolaborador AS colaborador_id, 
                    col.nome_colaborador, 
                    fi.prazo, 
                    fi.status
                 FROM funcao_imagem fi
                 JOIN colaborador col ON fi.colaborador_id = col.idcolaborador
                 JOIN funcao f ON fi.funcao_id = f.idfuncao
                 JOIN imagens_cliente_obra img ON fi.imagem_id = img.idimagens_cliente_obra
                 WHERE fi.imagem_id = $idImagemSelecionada";

    $resultFuncao = $conn->query($sqlFuncao);

    $funcoes = array();
    if ($resultFuncao->num_rows > 0) {
        while ($rowFuncao = $resultFuncao->fetch_assoc()) {
            $funcoes[] = $rowFuncao;
        }
    }

    echo json_encode($funcoes);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
