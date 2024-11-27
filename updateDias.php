<?php
include 'conexao.php';

$sql = "UPDATE imagens_cliente_obra i
        JOIN obra o 
            ON i.obra_id = o.idobra
        SET i.dias_trabalhados = DATEDIFF(CURDATE(), i.data_inicio)
        WHERE o.data_final IS NULL;
        ";

if ($conn->query($sql) === TRUE) {
    echo "Dias trabalhados atualizados com sucesso.";
} else {
    echo "Erro ao atualizar dias trabalhados: " . $conn->error;
}
$conn->close();
