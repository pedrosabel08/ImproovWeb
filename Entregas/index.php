<?php
session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

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
$status_etapa = obterStatus($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban de Entregas</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../PaginaPrincipal/styleIndex.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">

    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>

<body>


    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header>📦 Kanban de Entregas
            <button id="adicionar_entrega">Adicionar Entrega</button>
        </header>

        <main id="kanban">
            <div class="column" data-status="pendente,parcial">
                <h2>A entregar</h2>
            </div>
            <div class="column" data-status="concluida">
                <h2>Enviado / Aguardando feedback</h2>
            </div>
            <div class="column" data-status="atrasada">
                <h2>Atrasadas</h2>
            </div>
            <!-- <div class="column" data-status="aprovada">
                <h2>Aprovadas</h2>
            </div> -->
        </main>
    </div>
    <!-- Modal Adicionar Entrega -->
    <div id="modalAdicionarEntrega" class="modal">
        <div class="modal-content" style="max-width: 75vh;">
            <h2>Adicionar Entrega</h2>
            <form id="formAdicionarEntrega">
                <div>
                    <label>Obra:</label>
                    <select name="obra_id" id="obra_id" required>
                        <option value="">Selecione a obra</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Status:</label>
                    <select name="status_id" id="status_id" required>
                        <option value="">Selecione o status</option>
                        <?php foreach ($status_imagens as $status): ?>
                            <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                <?= htmlspecialchars($status['nome_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="imagens_container" class="imagens-container">
                    <p>Selecione uma obra e status para listar as imagens.</p>
                </div>

                <div>
                    <label for="prazo">Prazo previsto:</label>
                    <input type="date" name="prazo" id="prazo">
                </div>
                <div>
                    <label for="observacoes">Observações:</label>
                    <textarea name="observacoes" id="observacoes"></textarea>
                </div>
                <div class="buttons">
                    <button type="button" class="fecharModal">Fechar</button>
                    <button type="submit" class="btn-salvar">Salvar Entrega</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal" id="entregaModal">
        <div class="modal-content" style="max-width: 75vh;">
            <h3 id="modalTitulo"></h3>
            <button id="btnAdicionarImagem">Adicionar Imagem</button>
            <p><strong>Prazo:</strong> <span id="modalPrazo"></span></p>
            <p><strong>Conclusão geral:</strong> <span id="modalProgresso"></span></p>
            <div id="modalImagens"></div>
            <div class="buttons" style="margin-top: 20px;">
                <button type="button" class="fecharModal">Fechar</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>

<!-- Modal Selecionar Imagens para Entrega (usado ao clicar em "Adicionar Imagem") -->
<div id="modalSelecionarImagens" class="modal">
    <div class="modal-content" style="max-width: 75vh;">
        <h2>Selecionar imagens para adicionar à entrega</h2>
        <div id="selecionar_imagens_container" class="imagens-container">
            <p>Selecione uma entrega para carregar imagens.</p>
        </div>
        <div class="buttons">
            <button type="button" class="fecharModal">Fechar</button>
            <button type="button" id="btnAdicionarSelecionadas" class="btn-salvar">Adicionar Selecionadas</button>
        </div>
    </div>
</div>