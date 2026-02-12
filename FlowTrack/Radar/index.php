<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

include '../../conexaoMain.php';
include '../../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../../index.html");
    exit();
}


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
<html lang="pt-BR">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FlowTrack | Radar</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../../css/styleSidebar.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../../css/modalSessao.css'); ?>">
</head>

<body>
    <?php include '../../sidebar.php'; ?>
    <div class="radar-container">
        <header class="radar-header">
            <div>
                <h1>Radar</h1>
                <p>Varrendo funções e sugerindo alocações quando a etapa anterior já andou.</p>
            </div>
            <button id="radar-refresh" class="radar-btn">Revarrer</button>
        </header>

        <div class="radar-filters-row">
            <div class="radar-filters" id="radar-filters" aria-label="Filtrar funções"></div>
            <div class="radar-obra-filters" id="radar-obra-filters" aria-label="Filtrar por obra"></div>
            <div class="radar-type-filter">
                <span class="small">Filtrar por anterior:</span>
                <select id="filter-prev-started">
                    <option value="">Todos</option>
                    <option value="started">Somente iniciado</option>
                </select>
            </div>
        </div>

        <div id="radar-grid" class="radar-grid" aria-live="polite"></div>
    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../../script/controleSessao.js'); ?>"></script>
</body>

</html>