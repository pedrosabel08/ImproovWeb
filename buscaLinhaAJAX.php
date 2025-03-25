<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");
// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idImagemSelecionada = $_GET['ajid'];

    // Consulta para buscar funções, colaboradores, prazos e nome do status
    $sqlFuncao = "SELECT 
    img.clima, 
    img.imagem_nome,
    f.nome_funcao, 
    col.idcolaborador AS colaborador_id, 
    col.nome_colaborador, 
    fi.prazo, 
    fi.status,
    fi.observacao,
    fi.check_funcao,
    fi.idfuncao_imagem AS id,
    (SELECT c.nome_colaborador 
     FROM historico_aprovacoes h 
     JOIN colaborador c ON h.responsavel = c.idcolaborador 
     WHERE h.funcao_imagem_id = fi.idfuncao_imagem 
     ORDER BY h.id DESC 
     LIMIT 1) AS responsavel_aprovacao
    -- GROUP_CONCAT(sh.descricao SEPARATOR ',') AS descricao
FROM imagens_cliente_obra img
LEFT JOIN funcao_imagem fi ON img.idimagens_cliente_obra = fi.imagem_id
LEFT JOIN colaborador col ON fi.colaborador_id = col.idcolaborador
LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
-- LEFT JOIN status_hold sh ON sh.imagem_id = img.idimagens_cliente_obra
WHERE img.idimagens_cliente_obra = $idImagemSelecionada";

    $resultFuncao = $conn->query($sqlFuncao);

    $funcoes = array();
    if ($resultFuncao->num_rows > 0) {
        while ($rowFuncao = $resultFuncao->fetch_assoc()) {
            $funcoes[] = $rowFuncao;
        }
    }

    // Consulta para buscar o status_id da imagem
    $sqlStatusImagem = "SELECT ico.status_id AS status_id
                        FROM imagens_cliente_obra ico
                        WHERE ico.idimagens_cliente_obra = $idImagemSelecionada";

    $resultStatusImagem = $conn->query($sqlStatusImagem);

    $statusImagem = null;
    if ($resultStatusImagem->num_rows > 0) {
        $rowStatusImagem = $resultStatusImagem->fetch_assoc();
        $statusImagem = $rowStatusImagem['status_id'];
    }

    // Adicionar o status_id da imagem ao array de funções
    $response = array(
        'funcoes' => $funcoes,
        'status_id' => $statusImagem
    );

    echo json_encode($response);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
