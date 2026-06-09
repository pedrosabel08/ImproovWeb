<?php

function animacao_tipo_normalizar($nome)
{
    $nome = trim((string)$nome);
    $nome = preg_replace('/\s+/', ' ', $nome);
    if (function_exists('mb_substr')) {
        return mb_substr($nome, 0, 100, 'UTF-8');
    }
    return substr($nome, 0, 100);
}

function animacao_tipo_ensure_table(mysqli $conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS tipo_animacao (
        id int NOT NULL AUTO_INCREMENT,
        nome varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
        ativo tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_tipo_animacao_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new Exception($conn->error);
    }
}

function animacao_tipo_seed(mysqli $conn)
{
    animacao_tipo_ensure_table($conn);

    $defaults = ['vertical', 'horizontal', 'reels'];
    $stmt = $conn->prepare("INSERT IGNORE INTO tipo_animacao (nome) VALUES (?)");
    foreach ($defaults as $nome) {
        $stmt->bind_param('s', $nome);
        $stmt->execute();
    }
    $stmt->close();

    $sql = "INSERT IGNORE INTO tipo_animacao (nome)
            SELECT DISTINCT TRIM(tipo_animacao)
              FROM animacao
             WHERE tipo_animacao IS NOT NULL
               AND TRIM(tipo_animacao) <> ''";
    if (!$conn->query($sql)) {
        throw new Exception($conn->error);
    }
}

function animacao_tipo_salvar(mysqli $conn, $nome)
{
    $nome = animacao_tipo_normalizar($nome);
    if ($nome === '') {
        throw new Exception('Tipo de animacao vazio');
    }

    animacao_tipo_ensure_table($conn);

    $stmt = $conn->prepare(
        "INSERT INTO tipo_animacao (nome, ativo)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), ativo = 1"
    );
    $stmt->bind_param('s', $nome);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new Exception($erro);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, nome FROM tipo_animacao WHERE nome = ? LIMIT 1");
    $stmt->bind_param('s', $nome);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: ['id' => null, 'nome' => $nome];
}

function animacao_tipo_listar(mysqli $conn)
{
    animacao_tipo_seed($conn);

    $result = $conn->query("SELECT id, nome FROM tipo_animacao WHERE ativo = 1 ORDER BY nome ASC");
    if (!$result) {
        throw new Exception($conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}
