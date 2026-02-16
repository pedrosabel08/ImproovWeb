<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$allowedEditors = [1, 2, 9];
$userId = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : 0;
if (!$userId || !in_array($userId, $allowedEditors, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para editar o briefing de arquivos']);
    exit;
}

require '../conexao.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // fallback: accept application/x-www-form-urlencoded
    $data = $_POST;
}

$obraId = isset($data['obra_id']) ? intval($data['obra_id']) : (isset($data['obraId']) ? intval($data['obraId']) : 0);
if (!$obraId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
    exit;
}

$tipos = $data['tipos'] ?? null;
if (!is_array($tipos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tipos inválido']);
    exit;
}

$categoriasPermitidas = ['Arquitetônico', 'Referências', 'Paisagismo', 'Luminotécnico', 'Estrutural'];
$tiposArquivoPermitidos = ['PDF', 'IMG', 'SKP', 'DWG', 'IFC', 'Outros'];

function normTipoArquivo($v) {
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '') return null;
    return $s;
}

try {
    $conn->begin_transaction();

    // 1) Remover tipos que não estão mais no payload
    $tiposKeys = array_keys($tipos);
    $tiposKeys = array_values(array_filter($tiposKeys, fn($t) => trim((string)$t) !== ''));

    if (count($tiposKeys) === 0) {
        // Se o payload não tem tipos, remove tudo desta obra.
        $stmtDelAll = $conn->prepare('DELETE FROM briefing_tipo_imagem WHERE obra_id = ?');
        $stmtDelAll->bind_param('i', $obraId);
        $stmtDelAll->execute();
        $stmtDelAll->close();
        $conn->commit();
        echo json_encode(['success' => true, 'data' => ['obra_id' => $obraId]]);
        exit;
    }

    // DELETE ... NOT IN (...) via placeholders
    $placeholders = implode(',', array_fill(0, count($tiposKeys), '?'));
    $sqlDel = "DELETE FROM briefing_tipo_imagem WHERE obra_id = ? AND tipo_imagem NOT IN ($placeholders)";
    $stmtDel = $conn->prepare($sqlDel);
    if ($stmtDel === false) {
        throw new RuntimeException('Erro ao preparar delete: ' . $conn->error);
    }

    $types = 'i' . str_repeat('s', count($tiposKeys));
    $values = array_merge([$obraId], $tiposKeys);

    $bind = [$types];
    foreach ($values as $k => $v) {
        $bind[] = $values[$k];
    }
    $refs = [];
    foreach ($bind as $k => $v) {
        $refs[$k] = &$bind[$k];
    }
    call_user_func_array([$stmtDel, 'bind_param'], $refs);
    $stmtDel->execute();
    $stmtDel->close();

    // Prepared statements reused
    $stmtUpsertTipo = $conn->prepare(
        "INSERT INTO briefing_tipo_imagem (obra_id, tipo_imagem, created_by, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP"
    );
    if ($stmtUpsertTipo === false) {
        $details = $conn->error;
        if (stripos($details, 'briefing_tipo_imagem') !== false) {
            throw new RuntimeException('Tabelas do briefing de arquivos não existem. Rode o SQL em /sql/briefing_arquivos.sql');
        }
        throw new RuntimeException('Erro ao preparar upsert tipo: ' . $details);
    }

    $stmtGetTipoId = $conn->prepare('SELECT id FROM briefing_tipo_imagem WHERE obra_id = ? AND tipo_imagem = ? LIMIT 1');
    if ($stmtGetTipoId === false) {
        throw new RuntimeException('Erro ao preparar select tipo: ' . $conn->error);
    }

    $stmtDelCats = $conn->prepare(
        "DELETE FROM briefing_requisitos_arquivo
         WHERE briefing_tipo_imagem_id = ? AND categoria NOT IN ('Arquitetônico','Referências','Paisagismo','Luminotécnico','Estrutural')"
    );
    if ($stmtDelCats === false) {
        $details = $conn->error;
        if (stripos($details, 'briefing_requisitos_arquivo') !== false) {
            throw new RuntimeException('Tabelas do briefing de arquivos não existem. Rode o SQL em /sql/briefing_arquivos.sql');
        }
        throw new RuntimeException('Erro ao preparar delete categorias: ' . $details);
    }

    $stmtDelReqByCat = $conn->prepare(
        "DELETE FROM briefing_requisitos_arquivo WHERE briefing_tipo_imagem_id = ? AND categoria = ?"
    );
    if ($stmtDelReqByCat === false) {
        throw new RuntimeException('Erro ao preparar delete requisito por categoria: ' . $conn->error);
    }

    $stmtUpsertReq = $conn->prepare(
        "INSERT INTO briefing_requisitos_arquivo
            (briefing_tipo_imagem_id, categoria, origem, tipo_arquivo, obrigatorio, status)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            origem = VALUES(origem),
            obrigatorio = VALUES(obrigatorio),
            status = VALUES(status),
            updated_at = CURRENT_TIMESTAMP"
    );
    if ($stmtUpsertReq === false) {
        throw new RuntimeException('Erro ao preparar upsert requisito: ' . $conn->error);
    }

    foreach ($tipos as $tipoImagem => $cats) {
        $tipoImagem = trim((string)$tipoImagem);
        if ($tipoImagem === '') continue;

        // Upsert tipo
        $stmtUpsertTipo->bind_param('isii', $obraId, $tipoImagem, $userId, $userId);
        if (!$stmtUpsertTipo->execute()) {
            throw new RuntimeException('Erro ao salvar tipo_imagem: ' . $stmtUpsertTipo->error);
        }

        // Fetch tipo_id
        $stmtGetTipoId->bind_param('is', $obraId, $tipoImagem);
        $stmtGetTipoId->execute();
        $resTipo = $stmtGetTipoId->get_result();
        $rowTipo = $resTipo ? $resTipo->fetch_assoc() : null;
        $tipoId = $rowTipo ? intval($rowTipo['id']) : 0;
        if (!$tipoId) {
            throw new RuntimeException('Não foi possível obter o id do tipo_imagem salvo');
        }

        // Remove categorias desconhecidas
        $stmtDelCats->bind_param('i', $tipoId);
        $stmtDelCats->execute();

        // Para cada categoria obrigatória, grava origem e (se cliente) tipos_arquivo (1+)
        $cats = is_array($cats) ? $cats : [];
        foreach ($categoriasPermitidas as $categoria) {
            $c = $cats[$categoria] ?? [];
            $receberCliente = false;
            if (is_array($c) && array_key_exists('receber_cliente', $c)) {
                $receberCliente = ($c['receber_cliente'] === true || $c['receber_cliente'] === 1 || $c['receber_cliente'] === '1' || strtolower((string)$c['receber_cliente']) === 'true');
            } elseif (is_array($c) && array_key_exists('origem', $c)) {
                $receberCliente = (strtolower((string)$c['origem']) === 'cliente');
            }

            // Limpa o que existir para esta categoria (permite trocar interno <-> cliente, e trocar lista)
            $stmtDelReqByCat->bind_param('is', $tipoId, $categoria);
            if (!$stmtDelReqByCat->execute()) {
                throw new RuntimeException('Erro ao limpar requisitos da categoria: ' . $stmtDelReqByCat->error);
            }

            if ($receberCliente) {
                $tiposArquivo = [];
                if (is_array($c) && isset($c['tipos_arquivo']) && is_array($c['tipos_arquivo'])) {
                    $tiposArquivo = $c['tipos_arquivo'];
                } elseif (is_array($c) && isset($c['tipo_arquivo'])) {
                    // compatibilidade com payload antigo
                    $one = normTipoArquivo($c['tipo_arquivo']);
                    if ($one !== null) $tiposArquivo = [$one];
                }

                // Normaliza/valida
                $tiposNorm = [];
                foreach ($tiposArquivo as $t) {
                    $tt = normTipoArquivo($t);
                    if ($tt === null) continue;
                    if (!in_array($tt, $tiposArquivoPermitidos, true)) {
                        throw new InvalidArgumentException("Tipo de arquivo inválido para '$tipoImagem' / '$categoria'");
                    }
                    $tiposNorm[$tt] = true;
                }

                if (count($tiposNorm) === 0) {
                    throw new InvalidArgumentException("Selecione ao menos um tipo de arquivo para '$tipoImagem' / '$categoria'");
                }

                foreach (array_keys($tiposNorm) as $tipoArquivo) {
                    $origem = 'cliente';
                    $obrigatorio = 1;
                    $status = 'pendente';
                    $stmtUpsertReq->bind_param('isssis', $tipoId, $categoria, $origem, $tipoArquivo, $obrigatorio, $status);
                    if (!$stmtUpsertReq->execute()) {
                        throw new RuntimeException('Erro ao salvar requisito: ' . $stmtUpsertReq->error);
                    }
                }
            } else {
                // Origem interna: grava placeholder
                $origem = 'interno';
                $tipoArquivo = 'INTERNAL';
                $obrigatorio = 0;
                $status = 'dispensado';
                $stmtUpsertReq->bind_param('isssis', $tipoId, $categoria, $origem, $tipoArquivo, $obrigatorio, $status);
                if (!$stmtUpsertReq->execute()) {
                    throw new RuntimeException('Erro ao salvar requisito interno: ' . $stmtUpsertReq->error);
                }
            }
        }
    }

    $stmtUpsertTipo->close();
    $stmtGetTipoId->close();
    $stmtDelCats->close();
    $stmtDelReqByCat->close();
    $stmtUpsertReq->close();

    $conn->commit();

    echo json_encode(['success' => true, 'data' => ['obra_id' => $obraId]]);
} catch (InvalidArgumentException $e) {
    if ($conn && $conn->errno === 0) {
        try { $conn->rollback(); } catch (Throwable $_) {}
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($conn) {
        try { $conn->rollback(); } catch (Throwable $_) {}
    }
    http_response_code(500);
    $msg = $e->getMessage();
    // Se a mensagem já estiver orientando SQL, preserva como error principal
    if (stripos($msg, '/sql/briefing_arquivos.sql') !== false) {
        echo json_encode(['success' => false, 'error' => $msg]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro interno', 'details' => $msg]);
    }
}
