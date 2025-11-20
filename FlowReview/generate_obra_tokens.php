<?php
/**
 * Script auxiliar para gerar tokens (UUID v4) para todas as obras.
 * - Cria coluna `token` em `obra` se não existir
 * - Gera UUID para obras que não possuam token
 *
 * Uso (CLI): php generate_obra_tokens.php
 * Uso (web): acessar como usuário admin (idusuario 1 ou 2) ou via GET/POST
 */
try {
    require_once __DIR__ . '/../conexao.php';

    // Permitir execução via CLI ou por usuário admin
    $isCli = php_sapi_name() === 'cli';
    require_once __DIR__ . '/auth_cookie.php';
    $isAdmin = $isCli || (!empty($flow_user_id) && in_array($flow_user_id, [1,2]));
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Execute via CLI ou usuário admin.']);
        exit();
    }

    header('Content-Type: application/json');

    // 1) Verifica se a coluna `token` existe
    $dbName = null;
    $resDb = $conn->query("SELECT DATABASE() AS dbname");
    if ($resDb) {
        $rdb = $resDb->fetch_assoc();
        $dbName = $rdb['dbname'];
    }

    $colExists = false;
    if ($dbName) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'obra' AND COLUMN_NAME = 'token'");
        $stmt->bind_param('s', $dbName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $colExists = intval($row['cnt']) > 0;
        }
        $stmt->close();
    }

    $messages = [];
    if (!$colExists) {
        // Tenta adicionar a coluna
        $sqlAlter = "ALTER TABLE obra ADD COLUMN token VARCHAR(64) NULL UNIQUE";
        if ($conn->query($sqlAlter) === TRUE) {
            $messages[] = 'Coluna `token` criada com sucesso.';
        } else {
            // Se falhar, reporta e continua tentando (talvez permissão)
            $messages[] = 'Falha ao criar coluna token: ' . $conn->error;
        }
    } else {
        $messages[] = 'Coluna `token` já existe.';
    }

    // 2) Seleciona obras
    $rs = $conn->query("SELECT idobra, nome_obra, token FROM obra");
    if (!$rs) throw new Exception('Erro ao buscar obras: ' . $conn->error);

    // Preparar update
    $stmtUpd = $conn->prepare("UPDATE obra SET token = ? WHERE idobra = ?");
    if (!$stmtUpd) throw new Exception('Erro ao preparar update: ' . $conn->error);

    $countUpdated = 0;
    $countSkipped = 0;
    $rows = [];
    while ($row = $rs->fetch_assoc()) {
        $rows[] = $row;
        if (empty($row['token'])) {
            $uuid = generate_uuid_v4();
            $stmtUpd->bind_param('si', $uuid, $row['idobra']);
            if ($stmtUpd->execute()) {
                $countUpdated++;
            } else {
                $messages[] = "Falha ao atualizar obra {$row['idobra']}: " . $stmtUpd->error;
            }
        } else {
            $countSkipped++;
        }
    }

    $stmtUpd->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'total_obras' => count($rows),
        'updated' => $countUpdated,
        'skipped_with_token' => $countSkipped
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generate_uuid_v4() {
    $data = random_bytes(16);
    // Set version to 0100
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
