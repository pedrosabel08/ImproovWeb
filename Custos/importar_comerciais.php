<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth(true);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/comercial_helper.php';
try {
    $obra = (int)($_POST['obra_id'] ?? 0);
    custos_obra($conn, $obra);
    $file = $_FILES['arquivo'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024 || !is_uploaded_file($file['tmp_name']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') throw new InvalidArgumentException('Envie um CSV válido de até 2 MB.');
    $raw = file_get_contents($file['tmp_name']);
    if (!mb_check_encoding($raw, 'UTF-8') || str_contains($raw, chr(0))) throw new InvalidArgumentException('CSV deve usar UTF-8 e não conter conteúdo binário.');
    $h = fopen($file['tmp_name'], 'r');
    $headers = fgetcsv($h, 0, ',', '"', '');
    if (!$headers) throw new InvalidArgumentException('CSV vazio.');
    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
    $headers = array_map('trim', $headers);
    $required = ['imagem_nome', 'numero_contrato', 'valor', 'imposto', 'valor_imposto', 'comissao_comercial', 'valor_comissao_comercial'];
    if (count(array_unique($headers)) !== count($headers) || array_diff($required, $headers) || array_diff($headers, $required)) throw new InvalidArgumentException('Cabeçalhos esperados: ' . implode(',', $required));
    $conn->begin_transaction();
    custos_query($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
    $images = custos_query($conn, 'SELECT idimagens_cliente_obra,imagem_nome FROM imagens_cliente_obra WHERE obra_id=?', 'i', [$obra]);
    $byName = [];
    foreach ($images as $i) $byName[trim($i['imagem_nome'])][] = $i['idimagens_cliente_obra'];
    $line = 1;
    $valid = [];
    $errors = [];
    $seen = [];
    while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
        $line++;
        if ($row === [null]) continue;
        try {
            if ($line > 5001) throw new InvalidArgumentException('Limite de 5000 linhas.');
            if (count($row) !== count($headers)) throw new InvalidArgumentException('Número incorreto de colunas.');
            $v = array_combine($headers, $row);
            $name = trim($v['imagem_nome']);
            $ids = $byName[$name] ?? [];
            if (count($ids) !== 1) throw new InvalidArgumentException('Nome de imagem ausente ou ambíguo nesta obra: ' . $name);
            if (isset($seen[$ids[0]])) throw new InvalidArgumentException('Imagem repetida no arquivo.');
            $seen[$ids[0]] = true;
            $v['imagem_id'] = $ids[0];
            $valid[] = custos_comercial_validar($conn, $obra, $v);
        } catch (InvalidArgumentException $e) {
            $errors[] = ['linha' => $line, 'erro' => $e->getMessage()];
        }
        if (count($errors) >= 100) break;
    }
    fclose($h);
    if ($errors) {
        $conn->rollback();
        custos_json(['error' => 'Importação cancelada. Nenhuma linha gravada.', 'erros' => $errors], 422);
    }
    if (!$valid) throw new InvalidArgumentException('CSV sem itens.');
    foreach ($valid as $v) custos_comercial_salvar($conn, $obra, $v, true);
    $conn->commit();
    custos_json(['success' => true, 'itens' => count($valid)]);
} catch (InvalidArgumentException | DomainException $e) {
    $conn->rollback();
    custos_json(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Custos CSV: ' . $e->getMessage());
    custos_json(['error' => 'Falha na importação. Nenhum item gravado.'], 500);
}
