<?php

include 'conexao.php';

// 1️⃣ Atualizar status "Aprovado" para "Finalizado"
$sql1 = "UPDATE funcao_imagem SET status = 'Finalizado' WHERE status = 'Aprovado'";
if ($conn->query($sql1) === TRUE) {
    echo "Status 'Aprovado' atualizado para 'Finalizado'.\n";
} else {
    echo "Erro na atualização de 'Finalizado': " . $conn->error . "\n";
}

// // 2️⃣ Atualizar status "Finalizado" para "Arquivado" se tiver mais de 3 dias
// $sql2 = "UPDATE render_alta 
//     SET status = 'Arquivado' 
//     WHERE status = 'Finalizado'";
// if ($conn->query($sql2) === TRUE) {
//     echo "Status 'Finalizado' atualizado para 'Arquivado' para registros com mais de 3 dias.\n";
// } else {
//     echo "Erro na atualização de 'Arquivado': " . $conn->error . "\n";
// }

// Fechar conexão
$conn->close();
