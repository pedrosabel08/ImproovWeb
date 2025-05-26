<?php
include 'conexao.php';

// Inserir nova atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $versao = $_POST['versao'];
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data = $_POST['data'];

    $stmt = $conn->prepare("INSERT INTO atualizacoes (versao, titulo, descricao, data) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $versao, $titulo, $descricao, $data);
    $stmt->execute();
    $stmt->close();

    // Redireciona para evitar reenvio do formulário
    header("Location: atualizacoes.php");
    exit;
}

// Buscar atualizações existentes
$result = $conn->query("SELECT * FROM atualizacoes ORDER BY data DESC, id DESC");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Atualizações do Sistema</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            padding: 0;
            margin: 0;
            font-family: "Open Sans", sans-serif;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            max-width: 800px;
            margin: auto;
        }

        h1 {
            color: #333;
        }

        .atualizacao {
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .data {
            color: gray;
            font-size: 0.9em;
        }

        form {
            margin-top: 40px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        input,
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }

        button {
            padding: 10px 20px;
        }
    </style>
</head>

<body>

    <h1>📋 Atualizações do Sistema</h1>

    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="atualizacao">
            <h2>Versão <?= htmlspecialchars($row['versao']) ?> - <?= htmlspecialchars($row['titulo']) ?></h2>
            <p class="data">Data: <?= date('d/m/Y', strtotime($row['data'])) ?></p>
            <p><?= nl2br(htmlspecialchars($row['descricao'])) ?></p>
        </div>
    <?php endwhile; ?>

    <h2>➕ Adicionar nova atualização</h2>
    <form method="POST">
        <label>Versão:</label>
        <input type="text" name="versao" required placeholder="Ex: 1.0.2">

        <label>Título:</label>
        <input type="text" name="titulo" required placeholder="Breve título da atualização">

        <label>Descrição:</label>
        <textarea name="descricao" rows="5" required placeholder="Detalhe o que foi feito"></textarea>

        <label>Data:</label>
        <input type="date" name="data" required value="<?= date('Y-m-d') ?>">
        <button type="submit">Adicionar Atualização</button>
    </form>

</body>

</html>