<?php
include 'conexao.php';
require_once __DIR__ . '/../helpers/funcao_imagem_prazo_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $imagem_id = isset($_POST['imagem_id']) ? $_POST['imagem_id'] : null;
    $colaborador_id = 1; 
    $funcao_id = 8; 
    $prazo = isset($_POST['prazo']) ? $_POST['prazo'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO funcao_imagem (idimagem, colaborador_id, funcao_id, prazo, status) VALUES (?, ?, ?, NULL, ?)");
        $stmt->bind_param("iiis", $imagem_id, $colaborador_id, $funcao_id, $status);

        if (!$stmt->execute()) {
            throw new RuntimeException('Erro: ' . $stmt->error);
        }
        $funcaoImagemId = (int) $stmt->insert_id;
        $stmt->close();

        if ($prazo !== null && $prazo !== '') {
            funcao_imagem_prazo_atualizar($conn, $funcaoImagemId, $prazo, [
                'origem' => 'arquitetura_inserir_assets',
                'status_novo' => $status,
            ]);
        }

        $conn->commit();
        echo "Dados inseridos com sucesso!";
    } catch (Throwable $e) {
        $conn->rollback();
        echo "Erro: " . $e->getMessage();
    }
}

$conn->close();
