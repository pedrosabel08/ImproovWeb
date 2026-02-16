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

session_start();
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     // Se não estiver logado, redirecionar para a página de login
//     header("Location: ../index.html");
//     exit();
// }
$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);

$conn->close();
?>

<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FollowUp - Escolha de Ângulos</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <!-- Use the project's standard base styles -->
    <!-- <link rel="stylesheet" href="<?php echo asset_url('../css/stylePadrao.css'); ?>"> -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <div class="header">
            <div class="saudacao">Bem-vindo ao FollowUp!</div>
        </div>
        <header class="followup-header">
            <div class="greeting">
                <h2>Olá,</h2>
                <h3 id="obra-nome">FollowUp - Escolha de Ângulos</h3>
            </div>
            <div class="metrics">
                <div class="metric-card">
                    <div class="metric-value" id="metric-chosen">0</div>
                    <div class="metric-label">Ângulos escolhidos</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="metric-pending">0</div>
                    <div class="metric-label">Faltam escolher</div>
                </div>
            </div>
        </header>

        <main id="lista">
            <div class="card list-card">
                <h4 class="card-title">Imagens</h4>
                <div class="card-body">
                    <table id="tabela-imagens">
                        <thead>
                            <tr>
                                <th>Nome da Imagem</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Linhas populadas por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <div class="card visualizer-card hidden" id="visualizador">
            <div id="left" class="col-left">
                <button id="voltar">&larr; Voltar</button>
                <h2 id="nome-imagem">Nome da Imagem</h2>
                <div id="carrossel" class="carrossel">
                    <!-- imagens do carrossel serão injetadas aqui -->
                </div>
                <div class="acoes">
                    <button id="escolher-angulo">Escolher ângulo</button>
                </div>
                <div class="observacao">
                    <label for="obs">Observação</label>
                    <textarea id="obs" rows="4"></textarea>
                </div>
        </div>
        <div id="right" class="col-right">
                <h3>Tabela de Status</h3>
                <table id="tabela-status">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- preenchido via JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>