<?php
session_start();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styleIndex.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <title>Improov+Flow</title>
</head>

<body>

    <label class="switch">
        <input type="checkbox" id="theme-toggle">
        <span class="slider round"></span>
    </label>
    <main>
        <h1>IMPROOV+FLOW</h1>
        <div class="buttons">
            <button onclick="visualizarTabela()">Visualizar tabela com imagens</button>
            <button onclick="listaPos()">Lista Pós-Produção</button>
            <!-- <button onclick="dashboard()">Dashboard</button> -->
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <button onclick="clientes()">Informações clientes</button>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <button onclick="arquitetura()">Tela arquitetura</button>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <button onclick="animacao()">Lista Animação</button>
            <?php endif; ?>
        </div>
    </main>

    <script src="./script/scriptIndex.js"></script>
</body>

</html>