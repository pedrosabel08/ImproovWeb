<?php
session_start();
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GANTT</title>
    <script defer src="script.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">

        <h2>GANTT - <span id="nomenclatura"></span></h2>
        <select name="opcao" id="opcao_obra">
            <?php foreach ($obras as $obra): ?>
                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="tabela-container">
            <table id="gantt"></table>
        </div>
    </div>

    <div id="colaboradorModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div>
                <label for="colaboradorInput">ID do Colaborador:</label>
                <select name="colaborador_id" id="colaborador_id">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="confirmarBtn">Atribuir</button>
        </div>
    </div>

    <div id="modalConflito" class="modal" style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%, -30%); background:#fff; padding:20px; border:1px solid #ccc; z-index:999;">
        <div id="textoConflito"></div>
        <div style="margin-top:15px;">
            <div class="buttons">
                <button id="btnTrocar">🔁 Trocar</button>
                <button id="btnRemoverEAlocar">🚫 Remover e alocar</button>
                <button id="btnAgenda">📅 Ver agenda</button>
                <button id="btnVoltar" style="display:none;">🔙 Voltar</button>
            </div>

            <div class="trocar" style="display: none; margin-top: 10px;">
                <select name="colaborador_id_troca" id="colaborador_id_troca">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="confirmarBtnTroca">Trocar</button>
            </div>
        </div>
    </div>

    <div id="modalEtapa" style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%,-50%);
     background:white; padding:20px; border:1px solid #ccc; z-index:1000;">
        <label for="nomeEtapa">Etapa Coringa:</label>
        <input type="text" id="nomeEtapa" placeholder="Nome da etapa">
        <br><br>
        <button onclick="confirmarEtapaCoringa()">Confirmar</button>
        <button onclick="fecharModalEtapa()">Cancelar</button>
    </div>

    <div class="tooltip-box" id="tooltip"></div>





    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>