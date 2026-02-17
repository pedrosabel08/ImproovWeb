<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// session_start();
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../Dashboard/styleObra.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <meta charset="UTF-8">
    <title>Kanban de Alterações por Obra</title>

</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <div class="header">
            <h2>Tela de alterações</h2>
        </div>
        <div class="kanban-board" id="kanban-board">
            <div class="kanban-column" id="kanban-nãoiniciado">
                <div class="kanban-title">Não iniciado</div>
            </div>
            <div class="kanban-column" id="kanban-emandamento">
                <div class="kanban-title">Em andamento</div>
            </div>
            <div class="kanban-column" id="kanban-emaprovação">
                <div class="kanban-title">Em aprovação</div>
            </div>
            <div class="kanban-column" id="kanban-finalizado">
                <div class="kanban-title">Finalizado</div>
            </div>
        </div>
    </div>

    <div class="form-edicao" id="form-edicao">
        <form id="form-add" method="post" action="insereFuncao.php">
            <div class="titulo-funcoes">
                <span id="campoNomeImagem"></span>
            </div> <input type="hidden" id="imagem_id" name="imagem_id" value="">
            <div class="modal-funcoes">
                <div class="funcao_comp">
                    <div class="funcao" style="margin: auto; padding: 10px 20px; flex: 0;">
                        <div class="titulo" style="justify-content: center;">
                            <p id="alteracao">Alteração</p>
                        </div>
                        <div class="opcoes" id="opcoes">
                            <select name="alteracao_id" id="opcao_alteracao">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_alteracao" id="status_alteracao">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_alteracao" id="prazo_alteracao">
                            <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Caminho arquivo">
                        </div>
                        <button type="submit" id="salvar_funcoes" class="buttons-form-add" style="width: max-content; margin: 10px auto;">Salvar</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
    </div>

    <!-- Popover unificado -->
    <div id="popover-tarefas" class="popover oculto">
        <!-- Tarefas -->
        <div class="secao">
            <div class="secao-titulo secao-tarefas" onclick="toggleSecao('tarefas')">
                <strong>Tarefas</strong>
                <span id="badge-tarefas" class="badge-interna"></span>
            </div>
            <div id="conteudo-tarefas" class="secao-conteudo"></div>
        </div>

        <!-- Notificações -->
        <div class="secao">
            <div class="secao-titulo secao-notificacoes">
                <strong>Notificações</strong>
                <span id="badge-notificacoes" class="badge-interna"></span>
            </div>
            <div id="conteudo-notificacoes" class="secao-conteudo">
            </div>
        </div>
        <button id="btn-ir-revisao">Ir para Revisão</button>
    </div>

    <div id="modalSessao" class="modal-sessao">
        <div class="modal-conteudo">
            <h2>Sessão Expirada</h2>
            <p>Sua sessão expirou. Deseja continuar?</p>
            <button onclick="renovarSessao()">Continuar Sessão</button>
            <button onclick="sair()">Sair</button>
        </div>
    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/notificacoes.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

</body>

</html>