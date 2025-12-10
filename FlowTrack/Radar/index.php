<?php
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
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="../../css/styleSidebar.css" />
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

        <div class="radar-filters" id="radar-filters" aria-label="Filtrar funções"></div>

        <div id="radar-grid" class="radar-grid" aria-live="polite"></div>
    </div>

    <script src="script.js"></script>
    <script src="../../script/sidebar.js"></script>
</body>

</html>