<?php

function entregas_preparar_registro_item(mysqli $conn): mysqli_stmt
{
    // A data guarda a primeira entrega; o status também pode voltar a pendente
    // quando uma nova versão da imagem precisa ser entregue.
    return $conn->prepare("UPDATE entregas_itens
        SET status = CASE
                WHEN status IN ('Pendente', 'Entrega pendente') OR data_entregue IS NULL THEN ?
                ELSE status
            END,
            data_entregue = COALESCE(data_entregue, NOW())
        WHERE id = ? AND entrega_id = ?");
}
