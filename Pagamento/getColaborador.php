<?php
header('Content-Type: application/json');

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$mesNumero = isset($_GET['mes_id']) ? intval($_GET['mes_id']) : null;

// Construir a consulta SQL
$sql = "SELECT
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome,
        fi.status,
        fi.prazo,
        f.nome_funcao,
        fi.idfuncao_imagem,
        fi.pagamento,
        fi.valor
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN obra o on ico.obra_id = o.idobra
        JOIN funcao f on fi.funcao_id = f.idfuncao
        WHERE fi.colaborador_id = ? AND fi.status <> 'Não iniciado'";

if ($mesNumero) {
    $sql .= " AND MONTH(fi.prazo) = ?";
}

$stmt = $conn->prepare($sql);

if ($mesNumero) {
    $stmt->bind_param('ii', $colaboradorId, $mesNumero);
} else {
    $stmt->bind_param('i', $colaboradorId);
}

$stmt->execute();
$result = $stmt->get_result();

$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

echo json_encode($funcoes);

$stmt->close();
$conn->close();
