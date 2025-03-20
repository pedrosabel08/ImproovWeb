<?php


include 'conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styleSidebar.css">
    <link rel="stylesheet" href="Revisao/style.css">
    <title>Tela de Aprovação</title>

</head>
<?php

include 'sidebar.php';

?>

<body>

    <div class="container">
        <header>
            <div class="task-info" id="task-info">
                <h3 id="funcao_nome">Pedro - Composição</h3>
                <p id="imagem_nome">1. LD_RES Fotomontagem aérea com inserção do empreendimento em fotografia aérea ângulo 1

                </p>
                <div id="buttons-task">
                    <button class="action-btn tooltip" id="add_obs" onclick="addObservacao(648)" data-tooltip="Adicionar Observação">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <button class="action-btn tooltip" id="check" data-tooltip="Aprovar" onclick="revisarTarefa(61612, 'Pedro', '1. LD_RES &nbsp;Fotomontagem aérea com inserção do empreendimento em fotografia aérea ângulo 1', 'Composição', '21', true)">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button class="action-btn tooltip" id="xmark" data-tooltip="Rejeitar" onclick="revisarTarefa(61612, 'Pedro', '1. LD_RES &nbsp;Fotomontagem aérea com inserção do empreendimento em fotografia aérea ângulo 1', 'Composição', '21', false)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        </header>

        <nav>
            <img src="assets/07_HSA_MON_Hall de Entrada_EF.jpg" alt=""></li>
            <img src="assets/07_HSA_MON_Hall de Entrada_EF.jpg" alt=""></li>
            <img src="assets/07_HSA_MON_Hall de Entrada_EF.jpg" alt=""></li>
        </nav>

        <main>
            <div id="imageContainer">
                <div id="imagens"></div>

                <div id="imagem_completa">
                    <div id="imagem_atual"></div>
                </div>
            </div>
        </main>

        <aside class="sidebar-direita">
            <h3>Comentários</h3>
            <div class="comentarios"></div>
        </aside>
    </div>

    <script src="script/sidebar.js"></script>
</body>

</html>