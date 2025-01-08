<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <title>Revisão de Tarefas</title>
</head>

<body>
    <header>
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div id="menu" class="hidden">
            <a href="../inicio.php" id="tab-imagens">Página Principal</a>
            <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>
            <a href="../Render/index.php">Lista Render</a>

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
                <a href="../Obras/index.php">Obras</a>
            <?php endif; ?>

            <a href="../Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>

        <img src="../gif/assinatura_preto.gif" alt="Logo Improov + Flow" style="width: 150px;">

    </header>

    <div class="main" style="display: flex; flex-direction: column;">
        <select name="nome_funcao" id="nome_funcao" style="width: 200px;margin: 0 auto;border: none;border-bottom: 1px solid black;">
            <option value="Todos">Todos</option>
            <option value="Caderno">Caderno</option>
            <option value="Modelagem">Modelagem</option>
            <option value="Composição">Composição</option>
            <option value="Finalização">Finalização</option>
            <option value="Pós-produção">Pós-produção</option>
            <option value="Alteração">Alteração</option>
            <option value="Planta Humanizada">Planta Humanizada</option>
        </select>
        <div class="container"></div>
    </div>

    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</body>

</html>