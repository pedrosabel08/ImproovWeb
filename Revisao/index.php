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
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <title>Revisão de Tarefas</title>
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
    <header>
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

    <div id="historico_modal" style="display: none;">
        <div class="historico-container"></div>
        <div class="historico-add hidden">
            <form id="adicionar_obs">
                <div id="text_obs"></div>

                <p id="id_revisao"></p>
                <button type="submit">Enviar</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</body>

</html>