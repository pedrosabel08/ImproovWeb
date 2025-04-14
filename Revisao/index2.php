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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

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


    <div class="main">
        <div class="contagem">
            <h4>Contagem de revisões por função:</h4>
            <div id="contagem_alt"></div>
        </div>
        <div class="filtros">
            <select name="nome_funcao" id="nome_funcao">
                <option value="Todos">Todos</option>
                <option value="Caderno">Caderno</option>
                <option value="Filtro de assets">Filtro de assets</option>
                <option value="Modelagem">Modelagem</option>
                <option value="Composição">Composição</option>
                <option value="Pré-Finalização">Pré-Finalização</option>
                <option value="Finalização">Finalização</option>
                <option value="Pós-produção">Pós-produção</option>
                <option value="Alteração">Alteração</option>
                <option value="Planta Humanizada">Planta Humanizada</option>
            </select>
            <select name="filtro_obra" id="filtro_obra"></select>
            <select name="filtro_colaborador" id="filtro_colaborador"></select>
        </div>

        <div class="alternar">
            <button onclick="fetchTarefas('Todos', 'Em aprovação')">Em aprovação</button>
            <button onclick="fetchTarefas('Todos', 'Ajuste')">Ajuste</button>
            <button onclick="fetchTarefas('Todos', 'Aprovado')">Aprovado</button>
        </div>


        <div class="container"></div>
    </div>

    <div class="container-aprovacao hidden">
        <header>
            <button id="btnBack"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon turn-left-arrow" viewBox="0 0 24 24">
                    <path d="M9 14L4 9l5-5" />
                    <path d="M20 20v-7a4 4 0 0 0-4-4H4" />
                </svg>
            </button>
            <div class="task-info" id="task-info">
                <h3 id="funcao_nome"></h3>
                <h3 id="colaborador_nome"></h3>
                <p id="imagem_nome"></p>
                <div id="buttons-task">

                </div>

            </div>
        </header>

        <nav>
            <div id="imagens"></div>
        </nav>

        <div class="imagens">
            <div id="imagem_completa">
                <div id="imagem_atual"></div>
            </div>
            <div class="sidebar-direita">
                <h3>Comentários</h3>
                <div class="comentarios"></div>
            </div>
        </div>
    </div>
    <ul id="menuContexto">
        <li onclick="excluirImagem()">Excluir <span>🗑️</span></li>
    </ul>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script src="script2.js"></script>
    <script src="../script/sidebar.js"></script>

</body>

</html>