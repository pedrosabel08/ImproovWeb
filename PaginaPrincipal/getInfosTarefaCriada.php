<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // --- Parâmetro ---
    $idTarefaSelecionada = isset($_GET['idtarefa']) ? (int) $_GET['idtarefa'] : 0;

    // ==========================================================
    // 1) Funções da imagem (mantém sua lógica atual)
    // ==========================================================
    $sqlTarefas = "SELECT 
            t.*, c.nome_colaborador 
        FROM tarefas t
        LEFT JOIN colaborador c ON t.colaborador_id = c.idcolaborador
        WHERE t.id = $idTarefaSelecionada
    ";
    $resultTarefas = $conn->query($sqlTarefas);
    $tarefa = [];
    if ($resultTarefas && $resultTarefas->num_rows > 0) {
        while ($row = $resultTarefas->fetch_assoc()) {
            $tarefa[] = $row;
        }
    }

    // ==========================================================
    // Resposta final
    // ==========================================================
    echo json_encode([
        "tarefa"       => $tarefa,
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
