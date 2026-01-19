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

include '../conexao.php';

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     header("Location: ../index.html");
//     exit();
// }

include '../conexaoMain.php';


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

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <title>Painel de Produção</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header>
            <p>Olá, Pedro</p>
        </header>

        <main>
            <div class="global-filters">
                <label for="global-funcao">Filtrar por função:</label>
                <select id="global-funcao">
                    <option value="all">Todas as funções</option>
                    <?php foreach ($funcoes as $f): ?>
                        <option value="<?= $f['idfuncao'] ?>"><?= htmlspecialchars($f['nome_funcao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <section class="card" id="fachada">
                <div class="title">
                    <p class="nome_tipo">Fachada</p>
                    <div class="pessoas">Marcio, Vitor</div>
                </div>
                <div class="content"></div>
            </section>
            <section class="card" id="externas">
                <div class="title">
                    <p class="nome_tipo">Externas</p>
                    <div class="pessoas">Marcio, Vitor</div>
                </div>
                <div class="content"></div>
            </section>
            <section class="card" id="internas">
                <div class="title">
                    <p class="nome_tipo">Internas</p>
                    <div class="pessoas">José, Bruna, André Moreira</div>
                </div>
                <div class="content"></div>
            </section>
            <section class="card" id="unidades">
                <div class="title">
                    <p class="nome_tipo">Unidades</p>
                    <div class="pessoas">José, Bruna, André Moreira</div>
                </div>
                <div class="content"></div>
            </section>
            <section class="card" id="plantas">
                <div class="title">
                    <p class="nome_tipo">Planta Humanizada</p>
                    <div class="pessoas">Anderson, Andressa, Jiulia, Julio</div>
                </div>
                <div class="content"></div>
            </section>
        </main>
    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
</body>

</html>