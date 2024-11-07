<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
$colunaFiltro = isset($_GET['colunaFiltro']) ? intval($_GET['colunaFiltro']) : 0;
$tipoImagemFiltro = isset($_GET['tipoImagemFiltro']) ? $conn->real_escape_string($_GET['tipoImagemFiltro']) : '';

$sql = "SELECT i.idimagens_cliente_obra, c.nome_cliente, o.nome_obra, i.imagem_nome, s.nome_status, i.tipo_imagem, i.antecipada, o.status_obra
        FROM imagens_cliente_obra i
        JOIN cliente c ON i.cliente_id = c.idcliente
        JOIN obra o ON i.obra_id = o.idobra
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        GROUP BY i.idimagens_cliente_obra";

if ($filtro) {
    $colunas = ['nome_cliente', 'nome_obra', 'imagem_nome', 'nome_status'];
    $coluna = $colunas[$colunaFiltro];
    $sql .= " HAVING LOWER($coluna) LIKE LOWER('%$filtro%')";
}

if ($tipoImagemFiltro) {
    $sql .= " HAVING LOWER(tipo_imagem) = LOWER('$tipoImagemFiltro')";
}

$result = $conn->query($sql);

if (!$result) {
    die("Erro na consulta SQL: " . $conn->error);
}

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
}

header('Content-Type: application/json');
echo json_encode($dados);
$conn->close();
