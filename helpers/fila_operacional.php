<?php

function operacional_fetch_finalization_queue_total(mysqli $conn): int
{
    $sql = "
        SELECT COUNT(DISTINCT queue.imagem_id) AS total
        FROM (
            SELECT ifp.imagem_id
            FROM imagem_funcao_planejada ifp
            INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
            INNER JOIN obra o ON o.idobra = ico.obra_id
            WHERE ifp.funcao_id = 4
              AND ifp.status = 'TODO'
              AND ifp.funcao_imagem_id IS NULL
              AND (ico.tipo_imagem IS NULL OR LOWER(TRIM(ico.tipo_imagem)) != 'planta humanizada')
              AND ico.obra_id != 74
              AND o.status_obra = 0

            UNION

            SELECT fi.imagem_id
            FROM funcao_imagem fi
            INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
            INNER JOIN obra o ON o.idobra = ico.obra_id
            WHERE fi.funcao_id = 4
              AND LOWER(TRIM(fi.status)) IN ('não iniciado', 'nao iniciado')
              AND (ico.tipo_imagem IS NULL OR LOWER(TRIM(ico.tipo_imagem)) != 'planta humanizada')
              AND (ico.status_id IS NULL OR ico.status_id != 1)
              AND ico.obra_id != 74
              AND o.status_obra = 0
              AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (21, 15, 7, 34))

            UNION

            SELECT ico.idimagens_cliente_obra AS imagem_id
            FROM imagens_cliente_obra ico
            INNER JOIN obra o ON o.idobra = ico.obra_id
            INNER JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
                AND fi.funcao_id = 4
            WHERE ico.status_id = 1
              AND LOWER(TRIM(ico.tipo_imagem)) != 'planta humanizada'
              AND ico.obra_id != 74
              AND o.status_obra = 0
              AND (fi.colaborador_id IS NULL OR fi.colaborador_id NOT IN (21, 15, 7, 34))
              AND NOT EXISTS (
                  SELECT 1
                  FROM imagem_funcao_planejada ifp_active
                  WHERE ifp_active.imagem_id = ico.idimagens_cliente_obra
                    AND ifp_active.funcao_id = 4
                    AND ifp_active.status = 'TODO'
                    AND ifp_active.funcao_imagem_id IS NULL
              )
        ) queue
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    return (int) ($result->fetch_assoc()['total'] ?? 0);
}
