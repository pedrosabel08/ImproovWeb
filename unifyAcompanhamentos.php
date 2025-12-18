<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexao.php';

// Simple endpoint to list duplicate acompanhamentos (same DATE(data) + assunto) and to unify a group
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'list') {
    $obra_id = isset($_REQUEST['obra_id']) ? intval($_REQUEST['obra_id']) : 0;
    if (!$obra_id) {
        http_response_code(400);
        echo json_encode(['error' => 'obra_id inválido']);
        exit;
    }

    $sql = "SELECT DATE(`data`) as data_date, assunto, COUNT(*) as cnt
            FROM acompanhamento_email
            WHERE obra_id = ?
            GROUP BY data_date, assunto
            HAVING cnt > 1
            ORDER BY data_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $groups = [];
    while ($row = $res->fetch_assoc()) {
        $groups[] = ['date' => $row['data_date'], 'assunto' => $row['assunto'], 'count' => intval($row['cnt'])];
    }

    // For each group fetch sample items
    foreach ($groups as &$g) {
        $sql2 = "SELECT idacompanhamento_email as id, assunto, `data`, colaborador_id
                 FROM acompanhamento_email
                 WHERE obra_id = ? AND DATE(`data`) = ? AND assunto = ?
                 ORDER BY `data` ASC";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('iss', $obra_id, $g['date'], $g['assunto']);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        $items = [];
        while ($it = $r2->fetch_assoc()) {
            $items[] = $it;
        }
        $g['items'] = $items;
    }

    echo json_encode(['groups' => $groups]);
    exit;
}

if ($action === 'unify' && $method === 'POST') {
    $obra_id = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $assunto = isset($_POST['assunto']) ? $_POST['assunto'] : '';

    if (!$obra_id || !$date || $assunto === '') {
        http_response_code(400);
        echo json_encode(['error' => 'parâmetros inválidos']);
        exit;
    }

    // Get all ids for this group
    $sql = "SELECT idacompanhamento_email as id FROM acompanhamento_email WHERE obra_id = ? AND DATE(`data`) = ? AND assunto = ? ORDER BY idacompanhamento_email ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $obra_id, $date, $assunto);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($r = $res->fetch_assoc()) {
        $ids[] = intval($r['id']);
    }

    if (count($ids) <= 1) {
        echo json_encode(['success' => false, 'message' => 'Nenhum duplicado encontrado']);
        exit;
    }

    // Keep first id, delete the rest
    $keep = array_shift($ids);
    $toDelete = $ids;

    $conn->begin_transaction();
    try {
        // Delete duplicates (all in the group except the kept id)
        $sqlDel = "DELETE FROM acompanhamento_email WHERE obra_id = ? AND DATE(`data`) = ? AND assunto = ? AND idacompanhamento_email <> ?";
        $stmtDel = $conn->prepare($sqlDel);
        $stmtDel->bind_param('issi', $obra_id, $date, $assunto, $keep);
        $stmtDel->execute();

        // // Update kept record assunto to mark as unificado (append tag)
        // $sqlUpd = "UPDATE acompanhamento_email SET assunto = CONCAT(assunto, ' (unificados)') WHERE idacompanhamento_email = ? LIMIT 1";
        // $stmtUpd = $conn->prepare($sqlUpd);
        // $stmtUpd->bind_param('i', $keep);
        // $stmtUpd->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'kept' => $keep]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'ação inválida']);

?>
