<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Carrega conexão com o banco antes de executar atualizações de logs
include '../conexaoMain.php';
$conn = conectarBanco();

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
        integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/tributejs@5.1.3/dist/tribute.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">


    <title>Flow Review</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>


    <div class="main">



        <div class="container-main">
            <select id="filtroFuncao" style="display: none;">
                <option value="">Todas as funções</option>
            </select>
            <div id="metrics-panel" style="margin-bottom:8px; display:none;">
            </div>
            <div class="containerObra">
            </div>
            <div class="tarefasObra hidden">
                <div class="header">
                    <nav class="breadcrumb-nav">
                        <a href="https://improov.com.br/flow/ImproovWeb/Revisao/index.php">Flow Review</a>
                        <a id="obra_id_nav" class="obra_nav"
                            href="https://improov.com.br/flow/ImproovWeb/Revisao/index.php?obra_id=''">Obra</a>
                    </nav>
                    <div class="filtros">
                        <div>
                            <label for="nome_funcao">Função:</label>
                            <select name="nome_funcao" id="nome_funcao"></select>
                        </div>
                        <div>
                            <label for="filtro_colaborador">Colaborador:</label>
                            <select name="filtro_colaborador" id="filtro_colaborador"></select>
                        </div>
                        <input type="hidden" name="filtro_obra" id="filtro_obra">
                    </div>
                    <!-- 
                    <div class="alternar">
                        <button onclick="fetchObrasETarefas('Todos', 'Em aprovação')">Em aprovação</button>
                        <button onclick="fetchObrasETarefas('Todos', 'Ajuste')">Ajuste</button>
                    </div> -->
                </div>
                <div class="tarefasImagensObra"></div>
            </div>
        </div>
    </div>

    <div class="container-aprovacao hidden">
        <header>
            <nav class="breadcrumb-nav">
                <a href="https://improov.com.br/flow/ImproovWeb/Revisao/index.php">Flow Review</a>
                <a id="obra_id_nav" class="obra_nav"
                    href="https://improov.com.br/flow/ImproovWeb/Revisao/index.php?obra_id=''">Obra</a>
            </nav>
            <div class="task-info" id="task-info">
                <h3 id="funcao_nome"></h3>
                <h3 id="colaborador_nome"></h3>
                <p id="imagem_nome"></p>
                <div id="buttons-task">

                </div>

            </div>
            <!-- <div>
                <button id="add-imagem" class="tooltip" data-tooltip="Adicionar imagem" style="transform: translateX(-90%);">+</button>
            </div> -->
        </header>



        <div class="imagens">
            <div class="wrapper-sidebar">
                <div id="sidebarTabulator" class="sidebar-min"></div>
            </div>
            <nav>
                <div id="imagens"></div>
            </nav>
            <div id="imagem_completa">
                <div class="nav-select">
                    <!-- <select id="commentTypeSelect" style="margin-right:8px;">
                        <option value="ponto">Comentário (ponto)</option>
                        <option value="livre">Rabiscar (livre)</option>
                    </select> -->
                    <select id="indiceSelect"></select>
                    <div class="buttons">
                        <button id="reset-zoom"><i class="fa-solid fa-compress"></i></button>
                        <button id="btn-menos-zoom"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button id="btn-mais-zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button id="btn-download-imagem"><i class="fa-solid fa-download"></i></button>
                    </div>
                </div>
                <div id="image_wrapper" class="image_wrapper">
                </div>
            </div>
            <div class="sidebar-direita">
                <button id="submit_decision">Enviar aprovação</button>

                <!-- Modal -->
                <div id="decisionModal" class="modal-decision hidden">
                    <div class="modal-content-decision">
                        <span class="close">&times;</span>
                        <label><input type="radio" name="decision" value="aprovado"> Aprovado</label><br>
                        <label><input type="radio" name="decision" value="aprovado_com_ajustes"> Aprovado com
                            ajustes</label><br>
                        <label><input type="radio" name="decision" value="ajuste"> Ajuste</label><br>

                        <div class="modal-footer">
                            <button id="cancelBtn" class="cancel-btn">Cancel</button>
                            <button id="confirmBtn" class="confirm-btn">Confirm</button>
                        </div>
                    </div>
                </div>
                <div class="comentarios"></div>
            </div>
        </div>
    </div>
    <ul id="menuContexto">
        <li onclick="excluirImagem()">Excluir <span>🗑️</span></li>
    </ul>
    <div id="comentarioModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Novo Comentário</h3>
            <textarea id="comentarioTexto" rows="5" placeholder="Digite um comentário..."
                style="width: calc(100% - 10px); padding: 5px;"></textarea>
            <input type="file" id="imagemComentario" accept="image/*" />
            <div class="modal-actions">
                <button id="enviarComentario" style="background-color: green;">Enviar</button>
                <button id="fecharComentarioModal" style="background-color: red;">Cancelar</button>
            </div>
        </div>
    </div>


    <div id="modal-imagem" class="modal-imagem" onclick="fecharImagemModal()">
        <img id="imagem-ampliada" src="" alt="Imagem ampliada">
    </div>

    <!-- Modal -->
    <div id="imagem-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Enviar Imagens</h2>
            <input type="file" id="input-imagens" multiple accept="image/*">
            <div id="preview" class="preview-container"></div>
            <button id="btn-enviar-imagens">Enviar</button>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/tributejs@5.1.3/dist/tribute.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>


    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>

</body>

</html>