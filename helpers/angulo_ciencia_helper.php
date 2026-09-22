<?php

/** Comunicação de ângulo é independente de ciclo operacional. */
function flow_angulo_ciencia_registrar(
    mysqli $conn,
    int $funcaoImagemId,
    int $historicoImagemId,
    int $colaboradorId,
    ?int $escolhidoPorColaboradorId
): void {
    if ($funcaoImagemId <= 0 || $historicoImagemId <= 0 || $colaboradorId <= 0) {
        return;
    }
    $stmt = $conn->prepare(
        'INSERT INTO funcao_imagem_angulo_ciencia
            (funcao_imagem_id, historico_imagem_id, colaborador_id, escolhido_por_colaborador_id)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE escolhido_em = VALUES(escolhido_em),
             escolhido_por_colaborador_id = VALUES(escolhido_por_colaborador_id),
             visualizado_em = NULL'
    );
    $stmt->bind_param('iiii', $funcaoImagemId, $historicoImagemId, $colaboradorId, $escolhidoPorColaboradorId);
    $stmt->execute();
    $stmt->close();
}

function flow_angulo_ciencia_marcar_visualizado(mysqli $conn, int $funcaoImagemId, int $colaboradorId): bool
{
    $stmt = $conn->prepare(
        'UPDATE funcao_imagem_angulo_ciencia
            SET visualizado_em = NOW()
          WHERE funcao_imagem_id = ?
            AND colaborador_id = ?
            AND visualizado_em IS NULL'
    );
    $stmt->bind_param('ii', $funcaoImagemId, $colaboradorId);
    $stmt->execute();
    $alteradas = $stmt->affected_rows > 0;
    $stmt->close();
    return $alteradas;
}
