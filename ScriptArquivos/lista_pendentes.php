<?php
require 'conexao.php';

// Busca arquivos pendentes
$sql = "SELECT a.idarquivo, a.nome_original, a.status, a.caminho, c.nome_colaborador as recebido_por, a.recebido_em, o.nome_obra AS obra
        FROM arquivos a
        JOIN colaborador c ON c.idcolaborador = a.recebido_por
        JOIN obra o ON o.idobra = a.obra_id
        ";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Arquivos Pendentes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        form {
            display: inline;
        }

        button {
            padding: 6px 12px;
            margin: 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .aprovado {
            background: #4CAF50;
            color: white;
        }

        .rejeitado {
            background: #f44336;
            color: white;
        }
    </style>
</head>

<body>
    <h1>Arquivos Pendentes de Revisão</h1>

    <table>
        <tr>
            <th>ID</th>
            <th>Arquivo</th>
            <th>Projeto</th>
            <th>Status</th>
            <th>Recebido por</th>
            <th>Data</th>
            <th>Ações</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?= $row['idarquivo'] ?></td>
                <td><?= htmlspecialchars($row['nome_original']) ?></td>
                <td><?= htmlspecialchars($row['obra']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= htmlspecialchars($row['recebido_por']) ?></td>
                <td><?= htmlspecialchars($row['recebido_em']) ?></td>
                <td>
                    <!-- Formulário Aprovar -->
                    <form method="POST" action="revisar.php">
                        <input type="hidden" name="idarquivo" value="<?= $row['idarquivo'] ?>">
                        <input type="hidden" name="acao" value="aprovado">
                        <input type="hidden" name="responsavel" value="1"><!-- pode vir do login -->
                        <button type="submit" class="aprovado">Aprovar</button>
                    </form>

                    <!-- Formulário Rejeitar -->
                    <form method="POST" action="revisar.php">
                        <input type="hidden" name="idarquivo" value="<?= $row['idarquivo'] ?>">
                        <input type="hidden" name="acao" value="rejeitado">
                        <input type="hidden" name="responsavel" value="1"><!-- pode vir do login -->
                        <button type="submit" class="rejeitado">Rejeitar</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</body>

</html>