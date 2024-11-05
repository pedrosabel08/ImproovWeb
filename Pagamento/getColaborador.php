<?php
header('Content-Type: application/json');

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$mesNumero = isset($_GET['mes_id']) ? intval($_GET['mes_id']) : null;

if ($colaboradorId == 1) {
    // Consulta para colaborador 1 usando funcao_imagem e acompanhamento
    $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        f.nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento

    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    WHERE 
        fi.colaborador_id = ?
        AND fi.status <> 'Não iniciado'";

    if ($mesNumero) {
        $sql .= " AND MONTH(fi.prazo) = ?";
    }

    $sql .= " UNION ALL
    SELECT 
        ac.colaborador_id,
        'acompanhamento' AS origem,
        ac.idacompanhamento AS identificador,
        NULL AS imagem_id,
        o.nome_obra AS imagem_nome,
        NULL AS funcao_id,
        'Acompanhamento' AS nome_funcao,
        NULL AS status,
        NULL AS prazo,
        ac.pagamento,
        ac.valor,
        ac.data_pagamento

    FROM 
        acompanhamento ac
    JOIN 
        obra o ON o.idobra = ac.obra_id
    WHERE 
        ac.colaborador_id = ?";

    if ($mesNumero) {
        $sql .= " AND MONTH(ac.data) = ?";
    }
} elseif ($colaboradorId == 13) {
    // Consulta para colaborador 13 usando tabela animacao e acompanhamento

    $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        f.nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento

    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    WHERE 
        fi.colaborador_id = ?
        AND fi.status <> 'Não iniciado'";

    if ($mesNumero) {
        $sql .= " AND MONTH(fi.prazo) = ?";
    }

    $sql .= " UNION ALL
    SELECT 
        an.colaborador_id,
        'animacao' AS origem,
        an.idanimacao AS identificador,
        an.imagem_id,
        ico.imagem_nome,
        NULL AS funcao_id,
        'Animação' AS nome_funcao,
        an.status_anima as status,
        an.data_anima as prazo,
        an.pagamento,
        an.valor,
        an.data_pagamento
    FROM 
        animacao an
    JOIN 
        imagem_animacao ico ON an.imagem_id = ico.idimagem_animacao
    WHERE 
        an.colaborador_id = ?";

    if ($mesNumero) {
        $sql .= " AND MONTH(an.data_anima) = ?";
    }
} else {
    // Consulta padrão para outros colaboradores
    $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        f.nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento

    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    WHERE 
        fi.colaborador_id = ?
        AND fi.status <> 'Não iniciado'";

    if ($mesNumero) {
        $sql .= " AND MONTH(fi.prazo) = ?";
    }
}

// Preparar a consulta
$stmt = $conn->prepare($sql);

// Verificar se a consulta foi preparada corretamente
if (!$stmt) {
    die(json_encode(["error" => "Falha ao preparar a consulta: " . $conn->error]));
}

// Bind de parâmetros conforme necessário
if ($colaboradorId == 1 || $colaboradorId == 13) {
    if ($mesNumero) {
        $stmt->bind_param('iiii', $colaboradorId, $mesNumero, $colaboradorId, $mesNumero);
    } else {
        $stmt->bind_param('ii', $colaboradorId, $colaboradorId);
    }
} else {
    if ($mesNumero) {
        $stmt->bind_param('ii', $colaboradorId, $mesNumero);
    } else {
        $stmt->bind_param('i', $colaboradorId);
    }
}

$stmt->execute();
$result = $stmt->get_result();

$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

echo json_encode($funcoes);

$stmt->close();
$conn->close();
