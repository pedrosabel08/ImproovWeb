<?php
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

include '../conexaoMain.php';
include '../conexao.php';

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
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">

    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <title>Flow Planner</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div id="planner">
        <aside id="left-panel">
            <header class="panel-header">
                <h2>Entregas</h2>
                <small>Ordenadas por prioridade temporal</small>
            </header>
            <div id="groups">
                <!-- Grupos serão renderizados via JS -->
            </div>
        </aside>

        <main id="right-panel">
            <header class="detail-header">
                <h2 id="detail-title">Selecione uma entrega</h2>
                <div class="detail-meta">
                    <div><strong>Prazo:</strong> <span id="detail-prazo">-</span></div>
                    <div><strong>Status:</strong> <span id="detail-status">-</span></div>
                    <div><strong>Progresso:</strong> <span id="detail-progresso">-</span></div>
                </div>
            </header>

            <section class="filters">
                <div class="filter">
                    <label for="filter-responsavel">Responsável</label>
                    <select id="filter-responsavel">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="filter">
                    <label for="filter-etapa">Etapa</label>
                    <select id="filter-etapa">
                        <option value="">Todas</option>
                        <option value="Render">Render</option>
                        <option value="Em render">Em render</option>
                        <option value="Pós-produção">Pós-produção</option>
                        <option value="Finalização">Finalização</option>
                        <option value="Em aprovação">Em aprovação</option>
                        <option value="Aguardando aprovação">Aguardando aprovação</option>
                        <option value="Entregue">Entregue</option>
                    </select>
                </div>
            </section>

            <section id="images-list">
                <!-- Lista de imagens da entrega selecionada -->
            </section>
        </main>
    </div>

    <script>
        const API_LISTAR = '../Entregas/listar_entregas.php';
        const API_DETALHE = 'get_entrega_detalhe.php';
    </script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>

</body>

</html>