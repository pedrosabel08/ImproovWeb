<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth(true);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/obra_itens_helper.php';
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $obra = (int)($input['obra_id'] ?? 0);
    $itemId = (int)($input['item_id'] ?? 0);
    custos_obra($conn, $obra);
    $conn->begin_transaction();
    custos_query($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
    $id = obra_item_record_cost($conn, $obra, $itemId, $input, isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null);
    $conn->commit();
    custos_json(['success' => true, 'id' => $id]);
} catch (InvalidArgumentException | DomainException | JsonException $e) {
    if (isset($conn)) $conn->rollback();
    custos_json(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    error_log('Lançamento de custo por item: ' . $e->getMessage());
    custos_json(['error' => 'Não foi possível registrar o custo realizado.'], 500);
}
