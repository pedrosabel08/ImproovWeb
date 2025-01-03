<?php

session_start();

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];
$nivel_acesso = $_SESSION['nivel_acesso'];

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <title>Projetos</title>
</head>

<body>

    <main>
        <div class="sidebar">
            <div class="content">
                <div class="nav">
                    <p class="top">+</p>
                    <a href="index.php" id="dashboard" class="tooltip"><i class="fa-solid fa-chart-line"></i><span class="tooltiptext">Dashboard</span></a>
                    <a href="projetos.php" id="projects" class="tooltip"><i class="fa-solid fa-list-check"></i><span class="tooltiptext">Projetos</span></a>
                    <?php if ($nivel_acesso === 1): ?>
                        <a href="#" id="colabs" class="tooltip"><i class="fa-solid fa-users"></i><span class="tooltiptext">Colaboradores</span></a>
                        <a href="controle_comercial.html" id="controle_comercial" class="tooltip"><i class="fa-solid fa-dollar-sign"></i><span class="tooltiptext">Controle Comercial</span></a>
                    <?php endif; ?>
                </div>
                <div class="bottom">
                    <a href="#" id="sair" class="tooltip"><i class="fa fa-arrow-left"></i><span class="tooltiptext">Sair</span></a>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="dashboard-header">
                <img src="../gif/assinatura_preto.gif" alt="">
            </div>
            <div id="legenda" class="legenda">
                <div class="legenda-item">
                    <h4>Obras ativas:</h4>
                    <span id="obras_ativas" style="color: green;"></span>
                </div>
                <div class="legenda-item">
                    <h4>Obras finalizadas:</h4>
                    <span id="obras_finalizadas" style="color: red;"></span>
                </div>
            </div>
            <div id="painel"></div>

            <!-- Botão para controlar a gaveta -->
            <button id="toggleGaveta"><i class="fas fa-chevron-down"></i></button>

            <!-- Gaveta para obras inativas -->
            <div id="gaveta">
                <div id="obrasInativas"></div>
            </div>
        </div>

    </main>

    <script src="scriptProjetos.js"></script>
</body>

</html>