<?php
require_once __DIR__ . '/../helpers/custos_helper.php';
function custos_decimal($value): string
{
    $s = trim((string)$value);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $s)) throw new InvalidArgumentException('Use valor positivo com até duas casas decimais (ex.: 1800.00).');
    $c = custos_centavos($s);
    if ($c > 9999999999) throw new InvalidArgumentException('Valor acima do limite.');
    return number_format($c / 100, 2, '.', '');
}
function custos_comercial_validar(mysqli $conn, int $obra, array $input): array
{
    $tipo = $input['categoria'] ?? 'imagem';
    if (!in_array($tipo, ['imagem', 'foto'], true)) throw new InvalidArgumentException('Categoria inválida.');
    $v = ['categoria' => $tipo, 'id' => (int)($input['id'] ?? 0), 'imagem_id' => (int)($input['imagem_id'] ?? 0), 'valor' => custos_decimal($input['valor'] ?? '')];
    if ($tipo === 'imagem') {
        $image = custos_query($conn, 'SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra=? AND obra_id=?', 'ii', [$v['imagem_id'], $obra]);
        if (!$image) throw new InvalidArgumentException('Imagem não pertence à obra.');
        foreach (['imposto', 'valor_imposto', 'comissao_comercial', 'valor_comissao_comercial'] as $k) $v[$k] = custos_decimal($input[$k] ?? '0');
        if ((float)$v['imposto'] > 100 || (float)$v['comissao_comercial'] > 100) throw new InvalidArgumentException('Percentuais devem estar entre 0 e 100.');
        if (custos_centavos($v['valor_imposto']) + custos_centavos($v['valor_comissao_comercial']) > custos_centavos($v['valor'])) throw new InvalidArgumentException('Deduções excedem o valor vendido.');
        $v['numero_contrato'] = trim((string)($input['numero_contrato'] ?? ''));
        if (mb_strlen($v['numero_contrato']) > 255) throw new InvalidArgumentException('Contrato excede 255 caracteres.');
    }
    return $v;
}
/** Parent obra row is locked by the caller: serialize insert/upsert without
 * retroactively imposing a uniqueness constraint on commercial history. */
function custos_comercial_salvar(mysqli $conn, int $obra, array $v, bool $upsert = false): void
{
    $table = $v['categoria'] === 'foto' ? 'servico_foto' : 'imagem_comercial';
    $id = $v['id'];
    if ($id) {
        $row = custos_query($conn, "SELECT id FROM $table WHERE id=? AND obra_id=? FOR UPDATE", 'ii', [$id, $obra]);
        if (!$row) throw new InvalidArgumentException('Item comercial não pertence à obra.');
    } elseif ($table === 'imagem_comercial') {
        $rows = custos_query($conn, 'SELECT id FROM imagem_comercial WHERE obra_id=? AND imagem_id=? FOR UPDATE', 'ii', [$obra, $v['imagem_id']]);
        if (count($rows) > 1) throw new DomainException('Imagem possui múltiplos itens comerciais. Edite pelo ID; importação não pode escolher silenciosamente.');
        if ($rows && !$upsert) throw new DomainException('Imagem já cadastrada. Use Editar valores.');
        if ($rows) $id = (int)$rows[0]['id'];
    }
    if ($table === 'servico_foto') {
        if ($id) {
            $s = $conn->prepare('UPDATE servico_foto SET valor=? WHERE id=? AND obra_id=?');
            $s->bind_param('sii', $v['valor'], $id, $obra);
        } else {
            $s = $conn->prepare('INSERT INTO servico_foto (valor,obra_id) VALUES (?,?)');
            $s->bind_param('si', $v['valor'], $obra);
        }
    } else {
        $values = [$v['numero_contrato'], $v['valor'], $v['imposto'], $v['valor_imposto'], $v['comissao_comercial'], $v['valor_comissao_comercial'], $v['imagem_id'], $obra];
        if ($id) {
            $s = $conn->prepare('UPDATE imagem_comercial SET numero_contrato=?,valor=?,imposto=?,valor_imposto=?,comissao_comercial=?,valor_comissao_comercial=?,imagem_id=? WHERE obra_id=? AND id=?');
            $values[] = $id;
            $types = 'ssssssiii';
        } else {
            $s = $conn->prepare('INSERT INTO imagem_comercial (numero_contrato,valor,imposto,valor_imposto,comissao_comercial,valor_comissao_comercial,imagem_id,obra_id) VALUES (?,?,?,?,?,?,?,?)');
            $types = 'ssssssii';
        }
        $s->bind_param($types, ...$values);
    }
    $s->execute();
    $s->close();
}
