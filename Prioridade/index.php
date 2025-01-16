<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <title>Prioridades</title>
</head>

<body>

    <?php
    include '../conexao.php';

    session_start();
    // Verificar se o usuário está logado
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        // Se não estiver logado, redirecionar para a página de login
        header("Location: ../index.html");
        exit();
    }

    include '../conexaoMain.php';
    $conn = conectarBanco();

    $clientes = obterClientes($conn);
    $obras = obterObras($conn);
    $colaboradores = obterColaboradores($conn);

    $conn->close();
    ?>

    <?php

    include '../sidebar.php';

    ?>
    <div id="container" class="container">
        <div class="tabela">
            <div class="selects">

                <select name="colaboradorSelect" id="colaboradorSelect">
                    <option value="0">Colaborador:</option>
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="obraSelect" id="obraSelect">
                    <option value="0">Obra:</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= htmlspecialchars($obra['idobra']); ?>">
                            <?= htmlspecialchars($obra['nome_obra']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="buttons">
                <button id="selecionarTodos" style="background-color: lightblue;">Selecionar Todos</button>
                <button id="definirPrioridade" style="background-color: lightcoral" ;>Definir Prioridade</button>
            </div>
            <table id="imagens">
                <thead>
                    <th>Nome imagem</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Selecionar</th>
                </thead>
            </table>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>