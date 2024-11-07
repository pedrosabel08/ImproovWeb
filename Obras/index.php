<?php

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <title>Obras</title>
</head>

<body>

    <div id="menu" class="hidden">
        <a href="../inicio.php" id="tab-imagens">Página Principal</a>
        <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
        <a href="Pos-Producao/index.php">Lista Pós-Produção</a>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
            <a href="../infoCliente/index.php">Informações clientes</a>
            <a href="../Acompanhamento/index.php">Acompanhamentos</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
            <a href="../Animacao/index.php">Lista Animação</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
            <a href="../Imagens/index.php">Lista Imagens</a>
            <a href="../Pagamento/index.php">Pagamento</a>
            <a href="Obras/index.php">Obras</a>
        <?php endif; ?>

        <a href="../Metas/index.php">Metas e progresso</a>

        <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
            <i class="fa-solid fa-calendar-days"></i>
        </a>
    </div>
    <header>
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>

        <img src="../gif/assinatura_branco.gif" alt="" style="width: 200px;">
    </header>

    <main>
        <div id="obras">
            <span>Obras Ativas: <span id="contagemAtivas"></span></span> |
            <span>Obras Não Ativas: <span id="contagemNaoAtivas"></span></span>
        </div>
        <table id="tabela-obras">
            <thead>
                <tr>
                    <th>ID obra</th>
                    <th>Nome Obra</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>

        <div id="modalAcompanhamento" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Acompanhamento por Email</h2>
                <div id="acompanhamentoConteudo">
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="script.js"></script>
</body>

</html>