<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth(true);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/comercial_helper.php';
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $obra = (int)($input['obra_id'] ?? 0);
    custos_obra($conn, $obra);
    $conn->begin_transaction();
    custos_query($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
    $v = custos_comercial_validar($conn, $obra, $input);
    custos_comercial_salvar($conn, $obra, $v);
    $conn->commit();
    custos_json(['success' => true]);
} catch (InvalidArgumentException | DomainException | JsonException $e) {
    $conn->rollback();
    custos_json(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Custos comercial: ' . $e->getMessage());
    custos_json(['error' => 'Não foi possível salvar. Nenhum dado alterado.'], 500);
}
