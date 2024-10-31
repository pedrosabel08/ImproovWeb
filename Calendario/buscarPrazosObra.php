<?php
// Inclua a conexão ao banco de dados aqui
include '../conexao.php'; // Arquivo de conexão

if (isset($_POST['obraId'])) {
    $obraId = $_POST['obraId'];

    // Consulta para buscar prazos da obra
    $query = "SELECT obra_prazo.prazo, obra_prazo.tipo_entrega, obra.nome_obra , obra_prazo.assunto_entrega
    FROM obra_prazo 
    JOIN obra ON obra_prazo.obra_id = obra.idobra
	where obra_prazo.obra_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $obraId);
    $stmt->execute();
    $result = $stmt->get_result();

    $prazos = [];
    while ($row = $result->fetch_assoc()) {
        $prazos[] = $row;
    }

    // Retorna os dados no formato JSON
    echo json_encode($prazos);
}
?>
