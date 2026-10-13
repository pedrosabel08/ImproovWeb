<?php
/** Cadastro canônico compartilhado pela tela da obra e pelo mapa arquitetônico. */
function flow_subtipo_save(mysqli $conn, mixed $value): array
{
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 100) {
        throw new InvalidArgumentException('Informe um subtipo com até 100 caracteres.');
    }
    $name = trim($value);
    if ((int) $conn->query("SELECT GET_LOCK('flow_subtipo_cadastro', 5) AS ok")->fetch_assoc()['ok'] !== 1) {
        throw new RuntimeException('Tente cadastrar o subtipo novamente.');
    }
    try {
        $stmt = $conn->prepare('SELECT id, nome FROM subtipo_imagem WHERE LOWER(nome) = LOWER(?) ORDER BY id LIMIT 1');
        $stmt->bind_param('s', $name); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row) {
            $stmt = $conn->prepare('INSERT INTO subtipo_imagem (nome) VALUES (?)');
            $stmt->bind_param('s', $name); $stmt->execute();
            $row = ['id' => $conn->insert_id, 'nome' => $name]; $stmt->close();
        }
        $row['id'] = (int) $row['id'];
        return $row;
    } finally { $conn->query("SELECT RELEASE_LOCK('flow_subtipo_cadastro')"); }
}
function flow_subtipos(mysqli $conn, int $obraId): array
{
    $stmt = $conn->prepare('SELECT s.id, s.nome, EXISTS(SELECT 1 FROM imagens_cliente_obra i WHERE i.obra_id = ? AND i.subtipo_id = s.id) AS pertence_obra FROM subtipo_imagem s ORDER BY pertence_obra DESC, s.nome');
    $stmt->bind_param('i', $obraId); $stmt->execute();
    $groups = ['obra' => [], 'demais' => []];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $groups[(int) $row['pertence_obra'] ? 'obra' : 'demais'][] = ['id' => (int) $row['id'], 'nome' => $row['nome']];
    }
    $stmt->close();
    return $groups;
}
