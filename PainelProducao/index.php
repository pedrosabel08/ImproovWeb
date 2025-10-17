<?php
session_start();

include '../conexao.php';

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     header("Location: ../index.html");
//     exit();
// }

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
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

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>