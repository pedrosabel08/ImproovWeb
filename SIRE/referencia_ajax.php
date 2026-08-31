<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/sire_helpers.php';

sire_require_auth();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
require_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();
$jsonPayload = json_decode(file_get_contents('php://input'), true);
$action = $_REQUEST['action'] ?? ($jsonPayload['action'] ?? '');
$userId = sire_session_user_id();

function sire_get_pillars(mysqli $conn): array
{
    $pillars = [];
    $result = $conn->query("SELECT id, codigo, nome, ordem FROM sire_pilar ORDER BY ordem, nome");
    while ($row = $result->fetch_assoc()) {
        $pillars[$row['codigo']] = [
            'id' => (int) $row['id'],
            'codigo' => $row['codigo'],
            'nome' => $row['nome'],
            'valores' => [],
        ];
    }
    $values = $conn->query("SELECT v.id, v.pilar_id, v.nome, v.descricao, p.codigo,
            COUNT(rv.referencia_id) AS referencias
        FROM sire_pilar_valor v
        INNER JOIN sire_pilar p ON p.id = v.pilar_id
        LEFT JOIN sire_referencia_valor rv ON rv.valor_id = v.id
        WHERE v.ativo = 1
        GROUP BY v.id, v.pilar_id, v.nome, v.descricao, p.codigo, p.ordem
        ORDER BY p.ordem, v.nome");
    while ($row = $values->fetch_assoc()) {
        if (isset($pillars[$row['codigo']])) {
            $pillars[$row['codigo']]['valores'][] = [
                'id' => (int) $row['id'],
                'text' => $row['nome'],
                'descricao' => $row['descricao'] ?? '',
                'referencias' => (int) $row['referencias'],
            ];
        }
    }
    return array_values($pillars);
}

function sire_get_reference(mysqli $conn, int $referenceId): ?array
{
    $stmt = $conn->prepare("SELECT sr.*, ri.funcao_imagem_id, fi.imagem_id, ri.nomenclatura AS flow_nomenclatura, ri.nome_arquivo AS flow_nome_arquivo,
            ri.importado_em, i.obra_id, i.tipo_imagem AS ambiente, o.nomenclatura AS obra_nomenclatura
        FROM sire_referencia sr
        LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
        LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
        LEFT JOIN obra o ON o.idobra = i.obra_id
        WHERE sr.id = ? LIMIT 1");
    $stmt->bind_param('i', $referenceId);
    $stmt->execute();
    $reference = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$reference) {
        return null;
    }
    $reference['imagem_url'] = sire_reference_image_url($reference);
    $reference['nomenclatura'] = $reference['flow_nomenclatura'] ?: $reference['titulo'];
    $reference['nome_arquivo_exibicao'] = $reference['flow_nome_arquivo'] ?: $reference['nome_arquivo'];
    $reference['modelo_pasta'] = null;
    $reference['modelo_nome_arquivo'] = null;

    $imagemId = (int) ($reference['imagem_id'] ?? 0);
    if ($imagemId > 0) {
        $stmt = $conn->prepare("SELECT caminho, nome_arquivo
            FROM arquivo_log al
            INNER JOIN funcao_imagem fi_modelo ON fi_modelo.idfuncao_imagem = al.funcao_imagem_id
            WHERE fi_modelo.imagem_id = ?
              AND LOWER(al.nome_arquivo) LIKE '%.max'
            ORDER BY al.criado_em DESC, al.id DESC
            LIMIT 1");
        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $modelo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($modelo['caminho'])) {
            $caminhoModelo = (string) $modelo['caminho'];
            $ultimoSeparador = max(
                (int) strrpos($caminhoModelo, '\\'),
                (int) strrpos($caminhoModelo, '/')
            );
            if ($ultimoSeparador > 0) {
                $reference['modelo_pasta'] = substr($caminhoModelo, 0, $ultimoSeparador + 1);
            }
            $reference['modelo_nome_arquivo'] = $modelo['nome_arquivo'] ?? null;
        }
    }
    $reference['classificacoes'] = [];
    $stmt = $conn->prepare("SELECT p.codigo, v.id, v.nome, v.descricao, v.ativo
        FROM sire_referencia_valor rv
        INNER JOIN sire_pilar_valor v ON v.id = rv.valor_id
        INNER JOIN sire_pilar p ON p.id = v.pilar_id
        WHERE rv.referencia_id = ? ORDER BY p.ordem, v.nome");
    $stmt->bind_param('i', $referenceId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reference['classificacoes'][$row['codigo']][] = [
            'id' => (int) $row['id'],
            'text' => $row['nome'],
            'descricao' => $row['descricao'] ?? '',
            'ativo' => (bool) $row['ativo'],
        ];
    }
    $stmt->close();
    return $reference;
}

if ($action === 'getPilares') {
    $pillars = sire_get_pillars($conn);
    $conn->close();
    sire_json(['success' => true, 'pilares' => $pillars]);
}

if ($action === 'getReferencia') {
    $id = (int) ($_GET['id'] ?? 0);
    $reference = $id > 0 ? sire_get_reference($conn, $id) : null;
    $conn->close();
    if (!$reference) {
        sire_json(['success' => false, 'message' => 'Referência não encontrada.'], 404);
    }
    sire_json(['success' => true, 'referencia' => $reference]);
}

// Valores agora são criados exclusivamente na administração do SIRE.
if ($action === 'createValue') {
    $conn->close();
    sire_json(['success' => false, 'message' => 'Crie valores pela Administração do SIRE.'], 403);
}

if ($action === 'saveClassificacao') {
    $payload = $jsonPayload ?: [];
    $referenceId = (int) ($payload['referencia_id'] ?? 0);
    $descricao = trim((string) ($payload['descricao'] ?? ''));
    $classification = is_array($payload['classificacoes'] ?? null) ? $payload['classificacoes'] : [];
    if ($referenceId <= 0 || !sire_get_reference($conn, $referenceId)) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Referência não encontrada.'], 404);
    }
    $valid = [];
    foreach ($classification as $codigo => $values) {
        if (!is_array($values)) {
            continue;
        }
        foreach ($values as $valueId) {
            $valueId = (int) $valueId;
            if ($valueId > 0) {
                $valid[$valueId] = $codigo;
            }
        }
    }
    $conn->begin_transaction();
    try {
        $check = $conn->prepare("SELECT v.id, p.codigo, v.ativo,
            EXISTS(SELECT 1 FROM sire_referencia_valor current_value WHERE current_value.referencia_id = ? AND current_value.valor_id = v.id) AS ja_associado
            FROM sire_pilar_valor v
            INNER JOIN sire_pilar p ON p.id = v.pilar_id
            WHERE v.id = ?");
        foreach ($valid as $valueId => $codigo) {
            $check->bind_param('ii', $referenceId, $valueId);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            if (!$row || $row['codigo'] !== $codigo || (!(bool) $row['ativo'] && !(bool) $row['ja_associado'])) {
                throw new RuntimeException('Valor de classificação inválido.');
            }
        }
        $check->close();
        $delete = $conn->prepare('DELETE FROM sire_referencia_valor WHERE referencia_id = ?');
        $delete->bind_param('i', $referenceId);
        $delete->execute();
        $delete->close();
        if ($valid) {
            $insert = $conn->prepare('INSERT INTO sire_referencia_valor (referencia_id, valor_id, classificado_por) VALUES (?, ?, ?)');
            foreach (array_keys($valid) as $valueId) {
                $insert->bind_param('iii', $referenceId, $valueId, $userId);
                $insert->execute();
            }
            $insert->close();
        }
        $update = $conn->prepare('UPDATE sire_referencia SET descricao = ? WHERE id = ?');
        $update->bind_param('si', $descricao, $referenceId);
        $update->execute();
        $update->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        sire_json(['success' => false, 'message' => $e->getMessage()], 422);
    }
    $reference = sire_get_reference($conn, $referenceId);
    $conn->close();
    sire_json(['success' => true, 'referencia' => $reference]);
}

if ($action === 'addReference') {
    $type = $_POST['tipo'] ?? '';
    $title = trim((string) ($_POST['titulo'] ?? ''));
    $description = trim((string) ($_POST['descricao'] ?? ''));
    if (!in_array($type, ['Upload', 'URL'], true)) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Tipo de referência inválido.'], 422);
    }
    $origin = $type;
    $url = null;
    $fileName = null;
    $filePath = null;
    $mime = null;
    $size = null;
    if ($type === 'URL') {
        $url = trim((string) ($_POST['url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Informe uma URL válida.'], 422);
        }
        if ($title === '') {
            $title = $url;
        }
    } else {
        $file = $_FILES['imagem'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Selecione uma imagem válida.'], 422);
        }
        $original = (string) $file['name'];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Envie JPG, PNG, WEBP ou GIF.'], 422);
        }
        $dir = dirname(__DIR__) . '/uploads/sire_referencias';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Não foi possível preparar o diretório de imagens.'], 500);
        }
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME)) ?: 'referencia';
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '_' . $safeBase . '.' . $ext;
        $filePath = 'uploads/sire_referencias/' . $fileName;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $fileName)) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Não foi possível salvar a imagem.'], 500);
        }
        $mime = mime_content_type($dir . '/' . $fileName) ?: ($file['type'] ?? null);
        $size = (int) filesize($dir . '/' . $fileName);
        if ($title === '') {
            $title = pathinfo($original, PATHINFO_FILENAME);
        }
    }
    $stmt = $conn->prepare('INSERT INTO sire_referencia (titulo, origem, descricao, url_externa, nome_arquivo, caminho_arquivo, mime, tamanho_bytes, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssssii', $title, $origin, $description, $url, $fileName, $filePath, $mime, $size, $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        if ($filePath) {
            @unlink(dirname(__DIR__) . '/' . $filePath);
        }
        $conn->close();
        sire_json(['success' => false, 'message' => $error], 500);
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();
    $reference = sire_get_reference($conn, $id);
    $conn->close();
    sire_json(['success' => true, 'referencia' => $reference]);
}

$conn->close();
sire_json(['success' => false, 'message' => 'Ação inválida.'], 400);
