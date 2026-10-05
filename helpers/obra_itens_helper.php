<?php
require_once __DIR__ . '/../Custos/comercial_helper.php';

function obra_item_category_id(mysqli $conn, string $name): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 80) {
        throw new InvalidArgumentException('Informe uma categoria com até 80 caracteres.');
    }
    $stmt = $conn->prepare('INSERT INTO obra_item_categoria (nome) VALUES (?) ON DUPLICATE KEY UPDATE ativo=1');
    if (!$stmt) throw new RuntimeException('Não foi possível preparar a categoria do item.');
    $stmt->bind_param('s', $name);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível salvar a categoria do item.');
    $stmt->close();
    $rows = custos_query($conn, 'SELECT id FROM obra_item_categoria WHERE nome=? AND ativo=1', 's', [$name]);
    if (!$rows) throw new RuntimeException('Categoria do item não encontrada após salvar.');
    return (int)$rows[0]['id'];
}

function obra_item_money($value, string $label, bool $required = true): ?string
{
    if (($value === null || trim((string)$value) === '') && !$required) return null;
    $amount = custos_decimal($value);
    return $amount;
}

function obra_item_save(mysqli $conn, int $obraId, array $input, ?int $actorId = null): int
{
    $description = trim((string)($input['descricao'] ?? ''));
    $category = trim((string)($input['categoria'] ?? ''));
    $type = strtoupper(trim((string)($input['tipo_item'] ?? 'OUTRO')));
    $origin = strtoupper(trim((string)($input['origem'] ?? 'EXTRA')));
    $model = strtoupper(trim((string)($input['modelo_custo'] ?? 'DIRETO')));
    $quantity = (string)($input['quantidade'] ?? '1');
    $unit = trim((string)($input['unidade'] ?? ''));
    $zeroReason = trim((string)($input['justificativa_custo_zero'] ?? ''));
    $imageId = (int)($input['imagem_id'] ?? 0);
    $packageId = (int)($input['pacote_id'] ?? 0);
    $photoId = (int)($input['servico_foto_id'] ?? 0);
    $id = (int)($input['id'] ?? 0);

    if ($obraId <= 0) throw new InvalidArgumentException('Projeto inválido.');
    if ($description === '' || mb_strlen($description) > 255) throw new InvalidArgumentException('A descrição é obrigatória e deve ter até 255 caracteres.');
    if (!in_array($type, ['IMAGEM', 'PACOTE', 'MATERIAL', 'SERVICO', 'OUTRO'], true)) throw new InvalidArgumentException('Tipo de item inválido.');
    if (!in_array($origin, ['ONBOARDING', 'EXTRA', 'LEGADO'], true)) throw new InvalidArgumentException('Origem do item inválida.');
    if (!in_array($model, ['DIRETO', 'TAREFAS'], true)) throw new InvalidArgumentException('Modelo de custo inválido.');
    if (!preg_match('/^\d+(?:\.\d{1,3})?$/D', $quantity) || (float)$quantity <= 0) throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
    if (mb_strlen($unit) > 30 || mb_strlen($zeroReason) > 255) throw new InvalidArgumentException('Unidade ou justificativa excede o limite permitido.');

    $cost = obra_item_money($input['custo_previsto'] ?? null, 'custo', false);
    $revenue = obra_item_money($input['receita'] ?? null, 'receita', false);
    if (in_array($origin, ['ONBOARDING', 'EXTRA'], true) && $revenue === null) {
        throw new InvalidArgumentException('Informe o valor externo cobrado do cliente para este item.');
    }
    $categoryId = obra_item_category_id($conn, $category);

    if ($imageId > 0) {
        $rows = custos_query($conn, 'SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra=? AND obra_id=?', 'ii', [$imageId, $obraId]);
        if (!$rows) throw new InvalidArgumentException('A imagem não pertence ao projeto.');
    }
    if ($packageId > 0) {
        $rows = custos_query($conn, 'SELECT idobra_pacote FROM obra_pacote WHERE idobra_pacote=? AND obra_id=?', 'ii', [$packageId, $obraId]);
        if (!$rows) throw new InvalidArgumentException('O pacote não pertence ao projeto.');
    }
    if ($photoId > 0) {
        $rows = custos_query($conn, 'SELECT id FROM servico_foto WHERE id=? AND obra_id=?', 'ii', [$photoId, $obraId]);
        if (!$rows) throw new InvalidArgumentException('O serviço fotográfico não pertence ao projeto.');
    }
    if ($id > 0) {
        $rows = custos_query($conn, 'SELECT id FROM obra_item_financeiro WHERE id=? AND obra_id=? FOR UPDATE', 'ii', [$id, $obraId]);
        if (!$rows) throw new InvalidArgumentException('Item financeiro não pertence ao projeto.');
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE obra_item_financeiro SET categoria_id=?,tipo_item=?,descricao=?,quantidade=?,unidade=?,origem=?,imagem_id=?,pacote_id=?,servico_foto_id=?,receita=?,custo_previsto=?,modelo_custo=?,justificativa_custo_zero=? WHERE id=? AND obra_id=?');
        if (!$stmt) throw new RuntimeException('Não foi possível preparar a atualização do item.');
        $imageIdOrNull = $imageId ?: null;
        $packageIdOrNull = $packageId ?: null;
        $photoIdOrNull = $photoId ?: null;
        $stmt->bind_param('issdssiiissssii', $categoryId, $type, $description, $quantity, $unit, $origin, $imageIdOrNull, $packageIdOrNull, $photoIdOrNull, $revenue, $cost, $model, $zeroReason, $id, $obraId);
        if (!$stmt->execute()) throw new RuntimeException('Não foi possível atualizar o item financeiro.');
        $stmt->close();
        return $id;
    }

    if ($imageId || $packageId || $photoId) {
        $existing = custos_query($conn, 'SELECT id FROM obra_item_financeiro WHERE obra_id=? AND ((? > 0 AND imagem_id=?) OR (? > 0 AND pacote_id=?) OR (? > 0 AND servico_foto_id=?)) LIMIT 1 FOR UPDATE', 'iiiiiii', [$obraId, $imageId, $imageId, $packageId, $packageId, $photoId, $photoId]);
        if ($existing) {
            $input['id'] = (int)$existing[0]['id'];
            return obra_item_save($conn, $obraId, $input, $actorId);
        }
    }

    $stmt = $conn->prepare('INSERT INTO obra_item_financeiro (obra_id,categoria_id,tipo_item,descricao,quantidade,unidade,origem,imagem_id,pacote_id,servico_foto_id,receita,custo_previsto,modelo_custo,justificativa_custo_zero,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Não foi possível preparar o item financeiro.');
    $imageIdOrNull = $imageId ?: null;
    $packageIdOrNull = $packageId ?: null;
    $photoIdOrNull = $photoId ?: null;
    $stmt->bind_param('iissdssiiissssi', $obraId, $categoryId, $type, $description, $quantity, $unit, $origin, $imageIdOrNull, $packageIdOrNull, $photoIdOrNull, $revenue, $cost, $model, $zeroReason, $actorId);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível salvar o item financeiro: ' . $stmt->error);
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    return $newId;
}

function obra_item_record_cost(mysqli $conn, int $obraId, int $itemId, array $input, ?int $actorId = null): int
{
    $item = custos_query($conn, 'SELECT id FROM obra_item_financeiro WHERE id=? AND obra_id=? FOR UPDATE', 'ii', [$itemId, $obraId]);
    if (!$item) throw new InvalidArgumentException('Item financeiro não pertence ao projeto.');
    $value = obra_item_money($input['valor'] ?? null, 'valor');
    $date = trim((string)($input['data_lancamento'] ?? date('Y-m-d')));
    $description = trim((string)($input['descricao'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || mb_strlen($description) > 255) throw new InvalidArgumentException('Data ou descrição do lançamento inválida.');
    $stmt = $conn->prepare('INSERT INTO obra_item_custo_lancamento (item_id,valor,descricao,data_lancamento,criado_por) VALUES (?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Não foi possível preparar o lançamento de custo.');
    $stmt->bind_param('isssi', $itemId, $value, $description, $date, $actorId);
    if (!$stmt->execute()) throw new RuntimeException('Não foi possível registrar o custo realizado.');
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}
