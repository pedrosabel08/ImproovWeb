<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexao.php';

// Simple file logger for debugging (insereFuncao2)
function write_log_insere_funcao2($msg)
{
    $logdir = __DIR__ . '/logs';
    if (!is_dir($logdir)) {
        @mkdir($logdir, 0755, true);
    }
    $file = $logdir . '/insereFuncao2.log';
    $line = date('[Y-m-d H:i:s]') . ' ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

$data = $_POST;
$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$status_id = isset($data['status_id']) ? (int)$data['status_id'] : null;

$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Alteração' => 6,
    'Planta Humanizada' => 7,
    'Filtro de assets' => 8,
    'Pré-Finalização' => 9
];

$funcao_parametros = [
    'Caderno' => 'caderno',
    'Modelagem' => 'modelagem',
    'Composição' => 'comp',
    'Finalização' => 'finalizacao',
    'Pós-Produção' => 'pos',
    'Alteração' => 'alteracao',
    'Planta Humanizada' => 'planta',
    'Filtro de assets' => 'filtro',
    'Pré-Finalização' => 'pre'
];

$conn->begin_transaction();

try {
    // Atualiza o status da imagem
    $update_image_status = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?");
    $update_image_status->bind_param('ii', $status_id, $imagem_id);
    $update_image_status->execute();
    $update_image_status->close();

    // Busca o nome da imagem (para calcular valor de Planta Humanizada)
    $imagem_nome_atual = null;
    $stmtImgNome = $conn->prepare("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1");
    $stmtImgNome->bind_param('i', $imagem_id);
    $stmtImgNome->execute();
    $stmtImgNome->bind_result($imagem_nome_atual);
    $stmtImgNome->fetch();
    $stmtImgNome->close();

    // Prepara busca de valor por colaborador/função
    $stmtValor = $conn->prepare("SELECT valor FROM funcao_colaborador WHERE colaborador_id = ? AND funcao_id = ? LIMIT 1");
    if ($stmtValor === false) {
        write_log_insere_funcao2("Prepare stmtValor failed: " . $conn->error);
        throw new Exception('Erro no prepare stmtValor: ' . $conn->error);
    }

    // Prepara statement de insert/update
    $insertSql = "INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao, valor)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE colaborador_id = VALUES(colaborador_id), prazo = VALUES(prazo),
                            status = VALUES(status), observacao = VALUES(observacao),
                            valor = VALUES(valor)";
    $stmt = $conn->prepare($insertSql);
    if ($stmt === false) {
        write_log_insere_funcao2("Prepare insert stmt failed: " . $conn->error . " -- SQL: " . $insertSql);
        throw new Exception('Erro no prepare insert: ' . $conn->error);
    }

    foreach ($funcao_ids as $funcao => $funcao_id) {
        $parametro = $funcao_parametros[$funcao];

        if (!empty($data[$parametro . '_id'])) {
            $colaborador_id = (int)emptyToNull($data[$parametro . '_id']);
            $prazo = emptyToNull($data['prazo_' . $parametro]);
            $status = emptyToNull($data['status_' . $parametro]);
            $obs = emptyToNull($data['obs_' . $parametro]);

            // Verifica se o colaborador existe
            $check_colaborador = $conn->prepare("SELECT COUNT(*) FROM colaborador WHERE idcolaborador = ?");
            $check_colaborador->bind_param("i", $colaborador_id);
            $check_colaborador->execute();
            $check_colaborador->bind_result($exists);
            $check_colaborador->fetch();
            $check_colaborador->close();

            if (!$exists) {
                throw new Exception("Colaborador ID $colaborador_id não encontrado na tabela colaborador. parametro_id = {$parametro}_id");
            }

            // Determina valor da função para este colaborador
            $valorFuncao = null;
            if ($funcao_id == 7) {
                // Planta Humanizada: derivar do nome da imagem
                if ($imagem_nome_atual !== null) {
                    $n = mb_strtolower($imagem_nome_atual, 'UTF-8');
                    if (str_contains($n, 'lazer') || str_contains($n, 'implanta')) {
                        $valorFuncao = 200.00;
                    } elseif (str_contains($n, 'pavimento') && (str_contains($n, 'repeti') || str_contains($n, 'varia'))) {
                        $valorFuncao = 80.00;
                    } elseif (str_contains($n, 'pavimento') || str_contains($n, 'garagem')) {
                        $valorFuncao = 150.00;
                    } elseif (str_contains($n, 'varia')) {
                        $valorFuncao = 80.00;
                    } else {
                        $valorFuncao = 130.00;
                    }
                }
            } else {
                $stmtValor->bind_param('ii', $colaborador_id, $funcao_id);
                if ($stmtValor->execute() === false) {
                    write_log_insere_funcao2("stmtValor execute failed: " . $stmtValor->error . " | colaborador_id=" . $colaborador_id . " funcao_id=" . $funcao_id);
                } else {
                    $resultValor = $stmtValor->get_result();
                    $rowValor = $resultValor->fetch_assoc();
                    if ($rowValor === null) {
                        write_log_insere_funcao2("stmtValor: NO ROW FOUND in funcao_colaborador for colaborador_id=" . $colaborador_id . " funcao_id=" . $funcao_id);
                    } elseif ($rowValor['valor'] === null) {
                        write_log_insere_funcao2("stmtValor: ROW FOUND but valor=NULL in funcao_colaborador for colaborador_id=" . $colaborador_id . " funcao_id=" . $funcao_id);
                    } else {
                        $valorFuncao = (float)$rowValor['valor'];
                    }
                    $resultValor->free();
                }
            }

            write_log_insere_funcao2("Detected valorFuncao=" . var_export($valorFuncao, true) . " for colaborador_id=" . $colaborador_id . " funcao_id=" . $funcao_id);

            $bound = $stmt->bind_param("iiisssd", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $valorFuncao);
            if ($bound === false) {
                write_log_insere_funcao2("bind_param failed (insert): " . $stmt->error . " | tipos=iiisssd | valores=" . json_encode([$imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $valorFuncao]));
                throw new Exception('Erro no bind_param (insert): ' . $stmt->error);
            }

            $execOk = $stmt->execute();
            write_log_insere_funcao2("EXECUTE insert: ok=" . ($execOk ? '1' : '0') . " | stmt_error=" . $stmt->error . " | affected_rows=" . $stmt->affected_rows);
            if ($execOk === false) {
                throw new Exception('Erro no execute insert: ' . $stmt->error);
            }

            // ─── Inserir em alteracoes se funcao_id = 6 ───────────────────────────
            if ($funcao_id == 6) {
                $funcao_imagem_id = $stmt->insert_id;

                // Verificar se já existe registro em alteracoes
                $stmtCheck = $conn->prepare(
                    "SELECT idalt FROM alteracoes WHERE funcao_id = ? AND status_id = ? LIMIT 1"
                );
                $stmtCheck->bind_param("ii", $funcao_imagem_id, $status_id);
                $stmtCheck->execute();
                $stmtCheck->store_result();
                $exists = $stmtCheck->num_rows > 0;
                $stmtCheck->close();

                // Se não existe, inserir na tabela alteracoes
                if (!$exists && $status_id !== null) {
                    $stmtAlt = $conn->prepare(
                        "INSERT INTO alteracoes (funcao_id, data_recebimento, status_id) VALUES (?, NOW(), ?)"
                    );
                    $stmtAlt->bind_param("ii", $funcao_imagem_id, $status_id);
                    if (!$stmtAlt->execute()) {
                        write_log_insere_funcao2("ALTERACOES INSERT ERROR: " . $stmtAlt->error);
                    }
                    $stmtAlt->close();
                }
            }
            // ──────────────────────────────────────────────────────────────────────
        }
    }

    $stmt->close();
    $stmtValor->close();

    $conn->commit();
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('\Predis\Client')) {
            (new \Predis\Client())->publish('funcao_atualizada:updated', json_encode(['source' => 'insereFuncao2']));
        }
    } catch (Exception $e) { /* ignore Redis failures */ }
    echo json_encode([
        'success' => 'Dados inseridos/atualizados com sucesso!'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Erro ao executar a transação: ' . $e->getMessage()]);
}

$conn->close();
