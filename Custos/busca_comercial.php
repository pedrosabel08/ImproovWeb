<?php
include '../conexao.php';

if (isset($_GET['obra_id'])) {
    $obra_id = (int) $_GET['obra_id'];

    $stmt = $conn->prepare("SELECT
    img.imagem_nome,
    ic.numero_contrato,
    ic.valor AS valor_comercial_bruto,
    ic.imposto,
    ic.valor_imposto,
    ic.comissao_comercial,
    ic.valor_comissao_comercial,
    (ic.valor - ic.valor_imposto - ic.valor_comissao_comercial) AS valor_comercial_liquido,
    (
        SELECT SUM(fi.valor)
        FROM funcao_imagem fi
        WHERE fi.imagem_id = img.idimagens_cliente_obra
    ) AS valor_producao_total
FROM imagem_comercial ic
JOIN imagens_cliente_obra img ON ic.imagem_id = img.idimagens_cliente_obra
WHERE img.obra_id = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }

    echo json_encode($dados);

    $stmt->close();
} else {
    echo json_encode([]);
}

$conn->close();
