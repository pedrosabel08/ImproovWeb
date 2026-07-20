<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

// POST: criar novo subtipo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $nome = isset($body['nome']) ? trim($body['nome']) : '';
    if ($nome === '') {
        echo json_encode(['error' => 'Nome vazio']);
        exit;
    }
    $stmtExisting = $conn->prepare('SELECT id, nome FROM subtipo_imagem WHERE LOWER(nome) = LOWER(?) LIMIT 1');
    $stmtExisting->bind_param('s', $nome);
    $stmtExisting->execute();
    $existing = $stmtExisting->get_result()->fetch_assoc();
    $stmtExisting->close();

    if ($existing) {
        echo json_encode(['id' => (int) $existing['id'], 'nome' => $existing['nome']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO subtipo_imagem (nome) VALUES (?)");
        $stmt->bind_param('s', $nome);
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            echo json_encode(['id' => $id, 'nome' => $nome]);
        } else {
            echo json_encode(['error' => $conn->error]);
        }
        $stmt->close();
    }
    $conn->close();
    exit;
}

// GET: para a obra, separa os subtipos já usados dos demais disponíveis.
$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId > 0) {
    $stmt = $conn->prepare(
        'SELECT si.id, si.nome,
                EXISTS(
                    SELECT 1
                      FROM imagens_cliente_obra ico
                     WHERE ico.subtipo_id = si.id
                       AND ico.obra_id = ?
                ) AS pertence_obra
           FROM subtipo_imagem si
          ORDER BY pertence_obra DESC, si.nome ASC'
    );
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if (!$result) {
        echo json_encode(['obra' => [], 'demais' => [], 'permite_cadastro' => false]);
        exit;
    }

    $subtiposObra = [];
    $demaisSubtipos = [];
    while ($row = $result->fetch_assoc()) {
        $subtipo = ['id' => (int) $row['id'], 'nome' => $row['nome']];
        if ((int) $row['pertence_obra'] === 1) {
            $subtiposObra[] = $subtipo;
        } else {
            $demaisSubtipos[] = $subtipo;
        }
    }

    echo json_encode([
        'obra' => $subtiposObra,
        'demais' => $demaisSubtipos,
        'permite_cadastro' => $subtiposObra === [] && $demaisSubtipos === [],
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
} else {
    $result = $conn->query("SELECT id, nome FROM subtipo_imagem ORDER BY nome ASC");
}
if (!$result) {
    echo json_encode([]);
    exit;
}

echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$conn->close();
