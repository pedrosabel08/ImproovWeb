<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

require '../conexao.php';

$obraId = null;
if (isset($_GET['obra_id'])) $obraId = intval($_GET['obra_id']);
if ($obraId === null && isset($_GET['obraId'])) $obraId = intval($_GET['obraId']);

if (!$obraId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
    exit;
}

try {
    $sql = "SELECT 
                bt.id AS tipo_id,
                bt.tipo_imagem,
                bt.created_at AS tipo_created_at,
                bt.updated_at AS tipo_updated_at,
                bt.created_by,
                bt.updated_by,
                u1.nome_usuario AS created_by_name,
                u2.nome_usuario AS updated_by_name,
                br.categoria,
                br.origem,
                br.tipo_arquivo,
                br.obrigatorio,
                br.status,
                br.updated_at AS requisito_updated_at
            FROM briefing_tipo_imagem bt
            LEFT JOIN usuario u1 ON u1.idusuario = bt.created_by
            LEFT JOIN usuario u2 ON u2.idusuario = bt.updated_by
            LEFT JOIN briefing_requisitos_arquivo br ON br.briefing_tipo_imagem_id = bt.id
            WHERE bt.obra_id = ?
            ORDER BY bt.tipo_imagem, br.categoria, br.tipo_arquivo";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        $details = $conn->error;
        $msg = 'Erro ao preparar query';
        if (stripos($details, 'briefing_tipo_imagem') !== false || stripos($details, 'briefing_requisitos_arquivo') !== false) {
            $msg = 'Tabelas do briefing de arquivos não existem. Rode o SQL em /sql/briefing_arquivos.sql';
        }
        echo json_encode(['success' => false, 'error' => $msg, 'details' => $details]);
        exit;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $res = $stmt->get_result();

    $tipos = [];
    $meta = [
        'created_at' => null,
        'created_by_name' => null,
        'updated_at' => null,
        'updated_by_name' => null,
    ];

    while ($row = $res->fetch_assoc()) {
        $tipo = $row['tipo_imagem'];
        if (!isset($tipos[$tipo])) {
            $tipos[$tipo] = [
                'tipo_id' => intval($row['tipo_id']),
                'tipo_imagem' => $tipo,
                'requisitos' => []
            ];
        }

        if (!empty($row['categoria'])) {
            $cat = $row['categoria'];
            if (!isset($tipos[$tipo]['requisitos'][$cat])) {
                $tipos[$tipo]['requisitos'][$cat] = [
                    'origem' => 'interno',
                    'tipos_arquivo' => [],
                    'status' => 'dispensado',
                    // itens por tipo_arquivo (cliente)
                    'itens' => []
                ];
            }

            $origem = $row['origem'] ? strtolower((string)$row['origem']) : 'interno';
            $tipoArquivo = $row['tipo_arquivo'] ?? null;

            if ($origem === 'cliente') {
                $tipos[$tipo]['requisitos'][$cat]['origem'] = 'cliente';
                if ($tipoArquivo && strtoupper((string)$tipoArquivo) !== 'INTERNAL') {
                    $tipos[$tipo]['requisitos'][$cat]['tipos_arquivo'][] = $tipoArquivo;
                    $tipos[$tipo]['requisitos'][$cat]['itens'][(string)$tipoArquivo] = [
                        'tipo_arquivo' => $tipoArquivo,
                        'status' => $row['status'] ?? 'pendente',
                        'updated_at' => $row['requisito_updated_at'] ?? null,
                    ];
                }

                // status agregado por categoria: prioriza RECEBIDO > PENDENTE > VALIDADO
                $st = strtolower((string)($row['status'] ?? 'pendente'));
                $cur = strtolower((string)($tipos[$tipo]['requisitos'][$cat]['status'] ?? 'pendente'));
                $rank = ['recebido' => 3, 'pendente' => 2, 'validado' => 1, 'dispensado' => 0];
                $rNew = $rank[$st] ?? 0;
                $rCur = $rank[$cur] ?? 0;
                if ($rNew > $rCur) {
                    $tipos[$tipo]['requisitos'][$cat]['status'] = $st;
                }
            } else {
                // interno: mantém como interno e não adiciona tipos_arquivo
                $tipos[$tipo]['requisitos'][$cat]['origem'] = 'interno';
                $tipos[$tipo]['requisitos'][$cat]['status'] = 'dispensado';
            }
        }

        // Meta: pega o mais recente (por updated_at do requisito, fallback do tipo)
        $candidateUpdated = $row['requisito_updated_at'] ?? $row['tipo_updated_at'] ?? null;
        if ($candidateUpdated && (!$meta['updated_at'] || strcmp($candidateUpdated, $meta['updated_at']) > 0)) {
            $meta['updated_at'] = $candidateUpdated;
            $meta['updated_by_name'] = $row['updated_by_name'] ?? null;
        }

        $candidateCreated = $row['tipo_created_at'] ?? null;
        if ($candidateCreated && (!$meta['created_at'] || strcmp($candidateCreated, $meta['created_at']) < 0)) {
            $meta['created_at'] = $candidateCreated;
            $meta['created_by_name'] = $row['created_by_name'] ?? null;
        }
    }

    $stmt->close();
    $conn->close();

    // Normaliza itens (map -> array) e remove duplicatas em tipos_arquivo
    foreach ($tipos as &$t) {
        foreach ($t['requisitos'] as &$r) {
            if (isset($r['tipos_arquivo']) && is_array($r['tipos_arquivo'])) {
                $r['tipos_arquivo'] = array_values(array_unique($r['tipos_arquivo']));
            }
            if (isset($r['itens']) && is_array($r['itens'])) {
                $r['itens'] = array_values($r['itens']);
            } else {
                $r['itens'] = [];
            }
        }
    }
    unset($t, $r);

    echo json_encode([
        'success' => true,
        'data' => [
            'obra_id' => $obraId,
            'tipos' => $tipos,
            'meta' => $meta,
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()]);
}
