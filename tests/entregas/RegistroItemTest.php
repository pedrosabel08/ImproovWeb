<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/Entregas/registro_item_helper.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = improov_db_connect();
$db->query('CREATE TEMPORARY TABLE entregas_itens (id INT PRIMARY KEY, entrega_id INT NOT NULL, status VARCHAR(50), data_entregue DATETIME NULL)');
$db->query("INSERT INTO entregas_itens VALUES
    (1, 10, 'Entrega pendente', '2026-10-23 12:00:00'),
    (2, 10, 'Pendente', NULL),
    (3, 10, 'Entregue no prazo', '2026-10-24 12:00:00'),
    (4, 11, 'Entrega pendente', '2026-10-25 12:00:00')");
$stmt = entregas_preparar_registro_item($db);
$status = 'Entregue com atraso';
$entrega = 10;
foreach ([1, 2, 3, 4] as $id) {
    $stmt->bind_param('sii', $status, $id, $entrega);
    $stmt->execute();
}
$items = [];
foreach ($db->query('SELECT * FROM entregas_itens ORDER BY id')->fetch_all(MYSQLI_ASSOC) as $item) {
    $items[(int) $item['id']] = $item;
}
function registro_assert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
registro_assert($items[1]['status'] === $status && $items[1]['data_entregue'] === '2026-10-23 12:00:00', 'Reentrega deve concluir o item sem apagar a primeira data.');
registro_assert($items[2]['status'] === $status && $items[2]['data_entregue'] !== null, 'Primeira entrega deve registrar status e data.');
registro_assert($items[3]['status'] === 'Entregue no prazo' && $items[3]['data_entregue'] === '2026-10-24 12:00:00', 'Repetir registro de item entregue deve preservar status e data.');
registro_assert($items[4]['status'] === 'Entrega pendente' && $items[4]['data_entregue'] === '2026-10-25 12:00:00', 'Item de outra entrega nao pode ser alterado.');
echo "RegistroItemTest: OK\n";
