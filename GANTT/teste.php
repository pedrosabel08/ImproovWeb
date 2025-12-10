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
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <title>Gantt por Obra</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        <h2>Gantt - Obra: <span id="obraNome"></span></h2>

        <table id="ganttTable">
            <thead>
                <tr id="headerMeses"></tr>
                <tr id="headerDias"></tr>
            </thead>
            <tbody id="ganttBody"></tbody>
        </table>
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
                <input type="hidden" name="imagemId" id="imagemId">
                <input type="hidden" name="etapaNome" id="etapaNome">
                <input type="hidden" name="funcaoId" id="funcaoId">
                <input type="hidden" name="etapaId" id="etapaId">
            </div>
            <button id="confirmarBtn">Atribuir</button>
        </div>
    </div>

    <div id="modalConflito" class="modal"
        style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%, -30%); background:#fff; padding:20px; border:1px solid #ccc; z-index:999;">
        <div id="textoConflito"></div>
        <div style="margin-top:15px;">
            <div class="buttons">
                <button id="btnTrocar">🔁 Trocar</button>
                <button id="btnRemoverEAlocar">🚫 Remover e alocar</button>
                <button id="btnAddForcado">✅ Adicionar Forçado!</button>
                <button id="btnVoltar" style="display:none;">🔙 Voltar</button>
            </div>

            <div class="trocar" style="display: none; margin-top: 10px; align-items: center; flex-direction: column;">
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

    <div id="modalConflitoData" style="display:none; position:fixed; z-index:1000; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5);">
        <div style="background:white; padding:20px; margin:100px auto; width:80%; max-width:600px; border-radius:10px; max-height: 60vh; overflow: auto;">
            <h2>Conflito de Etapas</h2>
            <p id="periodoConflitante"></p>
            <div id="conflitosDetalhes"></div>
            <button onclick="document.getElementById('modalConflitoData').style.display='none'">Fechar</button>
            <button id="verAgendaBtn">Ver agenda</button>

            <input type="text" id="calendarioDatasDisponiveis" style="display:none;" />

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>