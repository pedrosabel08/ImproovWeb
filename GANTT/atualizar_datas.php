<?php

include '../conexao.php';

$data = json_decode(file_get_contents("php://input"), true);

$tipoImagem = $data['tipoImagem'];
$imagemId = (int)$data['imagemId'];
$etapas = $data['etapas'];

if (!is_array($etapas)) {
    echo json_encode(['success' => false, 'message' => 'Formato de etapas inválido.']);
    exit;
}

// Verificação de conflito para cada etapa
$datasOcupadasGeral = []; // Para acumular datas ocupadas por colaborador e função, evitando repetir

foreach ($etapas as $etapa) {
    $colaboradorId = isset($etapa['etapa_colaborador_id']) ? (int)$etapa['etapa_colaborador_id'] : null;
    $inicio = $etapa['data_inicio'];
    $fim = $etapa['data_fim'];
    $funcaoId = isset($etapa['funcao_id']) ? (int)$etapa['funcao_id'] : null;

    if ($colaboradorId && $funcaoId) {
        // Busca o limite da função
        $stmtLimite = $conn->prepare("SELECT limite FROM funcao WHERE idfuncao = ?");
        $stmtLimite->bind_param("i", $funcaoId);
        $stmtLimite->execute();
        $resultLimite = $stmtLimite->get_result();
        $rowLimite = $resultLimite->fetch_assoc();
        $limite = isset($rowLimite['limite']) ? (int)$rowLimite['limite'] : 1;

        // Conta etapas no período atual
        $stmtCheck = $conn->prepare("SELECT COUNT(DISTINCT g.id) AS total
            FROM etapa_colaborador ec
            INNER JOIN gantt_prazos g ON g.id = ec.gantt_id
            WHERE ec.colaborador_id = ?
              AND (? <= g.data_fim AND ? >= g.data_inicio)
        ");
        $stmtCheck->bind_param("iss", $colaboradorId, $fim, $inicio);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $rowCheck = $resultCheck->fetch_assoc();

        // Se atingiu limite, busca obras conflitantes
        if ($rowCheck['total'] >= $limite) {
            $stmtConflito = $conn->prepare("SELECT g.id, g.data_inicio, g.data_fim, g.obra_id
                FROM etapa_colaborador ec
                INNER JOIN gantt_prazos g ON g.id = ec.gantt_id
                WHERE ec.colaborador_id = ?
                  AND (? <= g.data_fim AND ? >= g.data_inicio)
            ");
            $stmtConflito->bind_param("iss", $colaboradorId, $fim, $inicio);
            $stmtConflito->execute();
            $resultConflito = $stmtConflito->get_result();

            $obrasConflitantes = [];
            while ($row = $resultConflito->fetch_assoc()) {
                $obrasConflitantes[] = [
                    'id' => $row['id'],
                    'obra_id' => $row['obra_id'],
                    'data_inicio' => $row['data_inicio'],
                    'data_fim' => $row['data_fim']
                ];
            }


            $stmtDatas = $conn->prepare("SELECT g.data_inicio, g.data_fim 
            FROM etapa_colaborador ec
            INNER JOIN gantt_prazos g ON g.id = ec.gantt_id
            WHERE ec.colaborador_id = ?
        ");

            if (!$stmtDatas) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao preparar consulta de datas ocupadas: ' . $conn->error
                ]);
                exit;
            }

            $stmtDatas->bind_param("i", $colaboradorId);
            $stmtDatas->execute();
            $resultDatas = $stmtDatas->get_result();

            $datasOcupadas = [];
            while ($row = $resultDatas->fetch_assoc()) {
                $datasOcupadas[] = [
                    'from' => $row['data_inicio'],
                    'to' => $row['data_fim']
                ];
            }

            echo json_encode([
                'success' => false,
                'message' => 'O colaborador já atingiu o limite de etapas simultâneas para essa função nesse período.',
                'obras_conflitantes' => $obrasConflitantes,
                'datas_ocupadas' => $datasOcupadas,
                'periodo_conflitante' => ['data_inicio' => $inicio, 'data_fim' => $fim],
                'gantt_id' => $etapa['id'],
            ]);
            exit;
        }
    }
}

$stmt = $conn->prepare("UPDATE gantt_prazos 
    SET data_inicio = ?, data_fim = ? 
    WHERE tipo_imagem = ? AND etapa = ? AND imagem_id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar statement: ' . $conn->error]);
    exit;
}

foreach ($etapas as $etapa) {
    $inicio = $etapa['data_inicio'];
    $fim = $etapa['data_fim'];
    $etapaNome = $etapa['etapa'];

    $stmt->bind_param("ssssi", $inicio, $fim, $tipoImagem, $etapaNome, $imagemId);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $stmt->error]);
        exit;
    }
}

echo json_encode(['success' => true]);
