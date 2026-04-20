<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexao.php';

// Simple file logger for debugging
function write_log_insere_funcao($msg)
{
    $logdir = __DIR__ . '/logs';
    if (!is_dir($logdir)) {
        @mkdir($logdir, 0755, true);
    }
    $file = $logdir . '/insereFuncao.log';
    $line = date('[Y-m-d H:i:s]') . ' ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

function intToNull($value)
{
    if ($value === '' || $value === null) {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (int)$value;
}

$data = $_POST;

$imagem_id = isset($data['imagem_id']) ? intToNull($data['imagem_id']) : null;

$funcao_id = isset($data['funcao_id'])
    ? intToNull($data['funcao_id'])
    : null;

$colaborador_id = isset($data['colaborador_id'])
    ? intToNull($data['colaborador_id'])
    : (isset($data['alteracao_id']) ? intToNull($data['alteracao_id']) : null);

$prazo = isset($data['prazo'])
    ? emptyToNull($data['prazo'])
    : (isset($data['prazo_alteracao']) ? emptyToNull($data['prazo_alteracao']) : null);

$status = isset($data['status'])
    ? emptyToNull($data['status'])
    : (isset($data['status_alteracao']) ? emptyToNull($data['status_alteracao']) : null);

$observacao = isset($data['observacao'])
    ? emptyToNull($data['observacao'])
    : (isset($data['obs_alteracao']) ? emptyToNull($data['obs_alteracao']) : null);

$status_id = isset($data['status_id']) ? intToNull($data['status_id']) : null;

if ($funcao_id === null && (isset($data['status_alteracao']) || isset($data['prazo_alteracao']) || isset($data['obs_alteracao']) || isset($data['alteracao_id']))) {
    $funcao_id = 6;
}

if (!$imagem_id) {
    echo json_encode(['error' => 'Parâmetro imagem_id é obrigatório']);
    exit;
}

$conn->begin_transaction();

try {
    // Atualiza o status da imagem se enviado
    if ($status_id !== null) {
        $stmtStatus = $conn->prepare(
            "UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?"
        );
        $stmtStatus->bind_param("ii", $status_id, $imagem_id);
        $stmtStatus->execute();
        $stmtStatus->close();
    }


    // Monta campos e valores dinâmicos para funcao_imagem
    $campos = ['imagem_id'];
    $valores = [$imagem_id];
    $updates = [];

    if ($colaborador_id !== null) {
        $campos[] = 'colaborador_id';
        $valores[] = $colaborador_id;
        $updates[] = 'colaborador_id = VALUES(colaborador_id)';
    }

    if ($funcao_id !== null) {
        $campos[] = 'funcao_id';
        $valores[] = $funcao_id;
        $updates[] = 'funcao_id = VALUES(funcao_id)';
    }

    if ($prazo !== null) {
        $campos[] = 'prazo';
        $valores[] = $prazo;
        $updates[] = 'prazo = VALUES(prazo)';
    }

    if ($status !== null) {
        $campos[] = 'status';
        $valores[] = $status;
        $updates[] = 'status = VALUES(status)';
    }

    if ($observacao !== null) {
        $campos[] = 'observacao';
        $valores[] = $observacao;
        $updates[] = 'observacao = VALUES(observacao)';
    }

    // ─── Auto-populate valor a partir de funcao_colaborador ───────────────
    $valorFuncao = null;
    if ($colaborador_id !== null && $funcao_id !== null) {
        if ($funcao_id == 7) {
            // Planta Humanizada: valor derivado do nome da imagem
            $stmtImg = $conn->prepare(
                "SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1"
            );
            $stmtImg->bind_param('i', $imagem_id);
            $stmtImg->execute();
            $stmtImg->bind_result($nomeImagem);
            $stmtImg->fetch();
            $stmtImg->close();
            if ($nomeImagem !== null) {
                $n = mb_strtolower($nomeImagem, 'UTF-8');
                if (str_contains($n, 'lazer') || str_contains($n, 'implanta')) {
                    $valorFuncao = 200.00;
                } elseif (str_contains($n, 'pavimento') && (str_contains($n, 'repeti') || str_contains($n, 'varia'))) {
                    $valorFuncao = 80.00;
                } elseif (str_contains($n, 'pavimento') || str_contains($n, 'garagem')) {
                    $valorFuncao = 150.00;
                } elseif (str_contains($n, 'varia')) {
                    $valorFuncao = 80.00;
                } else {
                    $valorFuncao = 130.00; // Apto individual padrão
                }
            }
        } else {
            // Demais funções: buscar valor configurado em funcao_colaborador
            $stmtValor = $conn->prepare(
                "SELECT valor FROM funcao_colaborador WHERE colaborador_id = ? AND funcao_id = ? LIMIT 1"
            );
            $stmtValor->bind_param('ii', $colaborador_id, $funcao_id);
            $stmtValor->execute();
            $stmtValor->bind_result($valorFuncao);
            $stmtValor->fetch();
            $stmtValor->close();
        }
    }
    if ($valorFuncao !== null) {
        $campos[] = 'valor';
        $valores[] = (float)$valorFuncao;
        // Atualiza valor sempre que o colaborador for enviado na requisição (troca de colaborador reflete nova tarifa)
        $updates[] = 'valor = VALUES(valor)';
    }
    // ──────────────────────────────────────────────────────────────────────

    $sql = "INSERT INTO funcao_imagem (" . implode(',', $campos) . ") VALUES (" . implode(',', array_fill(0, count($valores), '?')) . ")";
    if (!empty($updates)) {
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        write_log_insere_funcao("Prepare failed: " . $conn->error . " -- SQL: " . $sql);
        throw new Exception('Erro no prepare: ' . $conn->error);
    }

    // Monta tipos de bind_param
    $tipos = '';
    foreach ($valores as $v) {
        if (is_int($v)) {
            $tipos .= 'i';
        } elseif (is_float($v)) {
            $tipos .= 'd';
        } else {
            $tipos .= 's';
        }
    }

    $bound = $stmt->bind_param($tipos, ...$valores);
    if ($bound === false) {
        write_log_insere_funcao("bind_param failed: " . $stmt->error . " | tipos=" . $tipos . " | valores=" . json_encode($valores));
        throw new Exception('Erro no bind_param: ' . $stmt->error);
    }

    $execOk = $stmt->execute();
    write_log_insere_funcao(
        "EXECUTE: ok=" . ($execOk ? '1' : '0') .
            " | SQL=" . $sql .
            " | tipos=" . $tipos .
            " | valores=" . json_encode($valores) .
            " | affected_rows=" . $stmt->affected_rows .
            " | stmt_error=" . $stmt->error .
            " | conn_error=" . $conn->error
    );

    if ($execOk === false) {
        $stmt->close();
        throw new Exception('Erro no execute: ' . $stmt->error);
    }

    $stmt->close();

    // ─── Inserir em alteracoes se funcao_id = 6 ───────────────────────────
    if ($funcao_id == 6) {
        // Buscar o idfuncao_imagem que acabou de ser inserido/atualizado
        $stmtGetId = $conn->prepare(
            "SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1"
        );
        $stmtGetId->bind_param("ii", $imagem_id, $funcao_id);
        $stmtGetId->execute();
        $stmtGetId->bind_result($funcao_imagem_id);
        $stmtGetId->fetch();
        $stmtGetId->close();

        if ($funcao_imagem_id && $status_id !== null) {
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
            if (!$exists) {
                $stmtAlt = $conn->prepare(
                    "INSERT INTO alteracoes (funcao_id, data_recebimento, status_id) VALUES (?, NOW(), ?)"
                );
                $stmtAlt->bind_param("ii", $funcao_imagem_id, $status_id);
                if (!$stmtAlt->execute()) {
                    write_log_insere_funcao("ALTERACOES INSERT ERROR: " . $stmtAlt->error);
                }
                $stmtAlt->close();
            }
        }
    }
    // ──────────────────────────────────────────────────────────────────────

    $conn->commit();
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('\Predis\Client')) {
            (new \Predis\Client())->publish('funcao_atualizada:updated', json_encode(['source' => 'insereFuncao']));
        }
    } catch (Exception $e) { /* ignore Redis failures */ }
    echo json_encode(['success' => 'Dados inseridos/atualizados com sucesso!']);
} catch (Exception $e) {
    // Log exception details for debugging
    write_log_insere_funcao("EXCEPTION: " . $e->getMessage() .
        " | SQL=" . ($sql ?? 'N/A') .
        " | tipos=" . ($tipos ?? 'N/A') .
        " | valores=" . (isset($valores) ? json_encode($valores) : 'N/A') .
        " | conn_error=" . $conn->error
    );

    $conn->rollback();
    echo json_encode(['error' => 'Erro ao executar a transação: ' . $e->getMessage()]);
}

$conn->close();
