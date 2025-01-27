<?php
session_start();
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';

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
    <title>Detalhes da Obra</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="../css/styleSidebar.css">

</head>

<body>

    <!-- <div class="sidebar" id="sidebar" style="display: none;">
            <div class="content">
                <div class="nav">
                    <p class="top">+</p>
                    <a href="index.php" id="dashboard" class="tooltip"><i class="fa-solid fa-chart-line"></i><span class="tooltiptext">Dashboard</span></a>
                    <a href="projetos.php" id="projects" class="tooltip active"><i class="fa-solid fa-list-check"></i><span class="tooltiptext">Projetos</span></a>
                    <?php if ($nivel_acesso === 1): ?>
                        <a href="#" id="colabs" class="tooltip"><i class="fa-solid fa-users"></i><span class="tooltiptext">Colaboradores</span></a>
                        <a href="controle_comercial.html" id="controle_comercial" class="tooltip"><i class="fa-solid fa-dollar-sign"></i><span class="tooltiptext">Controle Comercial</span></a>
                    <?php endif; ?>
                </div>
                <div class="bottom">
                    <a href="#" id="sair" class="tooltip"><i class="fa fa-arrow-left"></i><span class="tooltiptext">Sair</span></a>
                </div>
            </div>
        </div> -->

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header>
            <h1 id="nomenclatura"></h1>
        </header>
        <button id="infosBtn" onclick="document.querySelector('.obs').scrollIntoView({behavior: 'smooth'})"><i class="fa-solid fa-circle-info"></i></button>
        <button id="infosBtn" onclick="document.querySelector('.filtro-tabela').scrollIntoView({behavior: 'smooth'})"><i class="fa-solid fa-info"></i></button>

        <!-- Tabela para exibir as funções da obra -->
        <div class="filtro-tabela">
            <div class="filtro">
                <h3>Filtro</h3>
                <select name="tipo_imagem" id="tipo_imagem">
                    <option value="0">Todos</option>
                    <option value="Fachada">Fachada</option>
                    <option value="Imagem Interna">Imagem Interna</option>
                    <option value="Imagem Externa">Imagem Externa</option>
                    <option value="Planta Humanizada">Planta Humanizada</option>
                </select>

                <select id="antecipada_obra">
                    <option value="">Todos as imagens</option>
                    <option value="Antecipada">Antecipada</option>
                </select>
            </div>

            <div class="buttons">
                <button id="editImagesBtn">Editar Imagens</button>
                <button id="addImagem">Adicionar Imagem</button>
            </div>

            <div id="editImagesModal" style="display: none;">
                <div class="modal-content-images" style="overflow-y: auto; max-height: 600px;">
                    <div id="modalHeader">
                        <div id="unsavedChanges" style="display: none;">
                            <p>Você fez alterações. Não esqueça de salvar!</p>
                            <button id="saveChangesBtn">Salvar Alterações</button>
                        </div>
                        <div class="header">
                            <h2>Editar Imagens</h2>
                            <span class="close-modal-images">&times;</span>
                        </div>
                    </div>
                    <div id="imageList"></div>
                </div>
            </div>

            <div id="add-imagem" class="modal">
                <form class="modal-content" id="add-imagem-form" onsubmit="submitFormImagem(event)">
                    <h2>Adicionar Imagem</h2>
                    <label for="opcao">Cliente:</label>
                    <select name="cliente_id" id="opcao_cliente">
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                                <?= htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="opcao">Obra:</label>
                    <select name="opcao" id="opcao_obra">
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="arquivos">Recebimento de arquivos: </label>
                    <input type="date" name="arquivos" id="arquivos">

                    <label for="data_inicio">Data Início: </label>
                    <input type="date" name="data_inicio" id="data_inicio">

                    <label for="prazo">Prazo: </label>
                    <input type="date" name="prazo" id="prazo">

                    <label for="nome-imagem">Nome da imagem:</label>
                    <input type="text" name="nome" id="nome-imagem">

                    <label for="tipo-imagem">Tipo da imagem:</label>
                    <input type="text" name="tipo" id="tipo-imagem">
                    <div class="buttons" style="margin: auto">
                        <button type="submit" id="salvar">Salvar</button>
                    </div>
                </form>
            </div>

            <div class="tabela">
                <table id="tabela-obra">
                    <thead>
                        <tr>
                            <th class="resizable">Imagem<div class="resize-handle"></div>
                            </th>
                            <th>Tipo de Imagem</th>
                            <th>Caderno</th>
                            <th>Status</th>
                            <th>Modelagem</th>
                            <th>Status</th>
                            <th>Composição</th>
                            <th>Status</th>
                            <th>Finalização</th>
                            <th>Status</th>
                            <th>Pós Produção</th>
                            <th>Status</th>
                            <th>Alteração</th>
                            <th>Status</th>
                            <th>Planta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">
            <!-- <button id="follow-up">Follow-up</button> -->

            <div class="obra-identificacao">
                <h4 id="data_inicio_obra"></h4>
                <h4 id="prazo_obra"></h4>
                <h4 id="dias_trabalhados"></h4>
            </div>

            <div class="obra-acompanhamento">

                <?php
                // Exibir somente se o usuário tiver nível de acesso 1
                if (isset($_SESSION['logado']) && $_SESSION['logado'] === true && $_SESSION['nivel_acesso'] == 1) {
                ?>
                    <button id="orcamento">Orçamento</button>
                <?php
                }
                ?>
            </div>

            <div class="modalOrcamento" id="modalOrcamento">
                <div class="orcamento-form">
                    <h2>Fazer Orçamento</h2>
                    <form id="formOrcamento">
                        <input type="hidden" id="idObraOrcamento">
                        <div class="linha">
                            <label for="tipo">Tipo:</label>
                            <input type="text" id="tipo" required>
                        </div>
                        <div class="linha">
                            <label for="valor">Valor:</label>
                            <input type="number" id="valor" required>
                        </div>
                        <div class="linha">
                            <label for="data">Data:</label>
                            <input type="date" id="data" required>
                        </div>
                        <div class="buttons">
                            <button type="submit" id="salvar_orcamento">Salvar Orçamento</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="modalAcompanhamento" class="modal">
                <div class="modal-content" style="width: 500px;">
                    <span class="close-modal">&times;</span>
                    <h2 style="margin-bottom: 30px;">Acompanhamento por Email</h2>
                    <div id="acompanhamentoConteudo">
                        <form id="adicionar_acomp" style="align-items: center;">

                            <!-- Campo de assunto -->
                            <div id="campo">
                                <label for="assunto">Assunto:</label>
                                <textarea name="assunto" id="assunto" name="assunto" required></textarea>
                            </div>

                            <!-- Campo de data -->
                            <div id="campo">
                                <label for="data">Data:</label>
                                <input type="date" name="data_acomp" id="data_acomp" required>
                            </div>

                            <!-- Botão para enviar -->
                            <button type="submit" id="add-acomp">Adicionar Acompanhamento</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="obra-imagens">
                <h4 id="total_imagens"></h4>
                <h4 id="total_imagens_antecipadas"></h4>
                <div id="funcoes" style="display: flex;flex-wrap: wrap; gap: 50px; justify-content: space-around;">
                </div>
                <div id="grafico">
                    <canvas id="graficoPorcentagem" width="400" height="200"></canvas>
                </div>
            </div>

        </div>


        <div id="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">
            <div class="acompanhamentos">
                <h1>Acompanhamentos</h1>
                <button id="acomp" style="background-color: steelblue; width: 140px; text-align: center;">Acompanhamento</button>

                <div id="list_acomp" class="list-acomp"></div>
                <button id="btnMostrarAcomps"><i class="fas fa-chevron-down"></i> Mostrar Todos</button>
            </div>

            <div class="obs">
                <h1>Observações</h1>
                <button id="obsAdd" style="background-color: steelblue; width: 140px; text-align: center;">Observação</button>

                <div id="infos"></div>
            </div>
        </div>
    </div>
    <div class="form-edicao" id="form-edicao">
        <form id="form-add" method="post" action="insereFuncao.php">
            <div class="titulo-funcoes">
                <span id="campoNomeImagem"></span>
            </div> <input type="hidden" id="imagem_id" name="imagem_id">
            <div class="funcao">
                <div class="titulo">
                    <p id="caderno">Caderno</p>
                    <i class="fas fa-chevron-down toggle-options"></i>
                </div>
                <div class="opcoes" style="display: none;">
                    <select name="caderno_id" id="opcao_caderno">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_caderno" id="status_caderno">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_caderno" id="prazo_caderno">
                    <input type="text" name="obs_caderno" id="obs_caderno" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="filtro">Filtro de assets</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">
                    <select name="filtro_id" id="opcao_filtro">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_filtro" id="status_filtro">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_filtro" id="prazo_filtro">
                    <input type="text" name="obs_filtro" id="obs_filtro" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="modelagem">Modelagem</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" style="display: none;">
                    <select name="model_id" id="opcao_model">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_modelagem" id="status_modelagem">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_modelagem" id="prazo_modelagem">
                    <input type="text" name="obs_modelagem" id="obs_modelagem" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="comp">Composição</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">
                    <select name="comp_id" id="opcao_comp">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_comp" id="status_comp">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_comp" id="prazo_comp">
                    <input type="text" name="obs_comp" id="obs_comp" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="final">Finalização</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">
                    <select name="final_id" id="opcao_final">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_finalizacao" id="status_finalizacao">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_finalizacao" id="prazo_finalizacao">
                    <input type="text" name="obs_finalizacao" id="obs_finalizacao" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="pos">Pós-Produção</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">
                    <select name="pos_id" id="opcao_pos">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status_pos" id="status_pos">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_pos" id="prazo_pos">
                    <input type="text" name="obs_pos" id="obs_pos" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="alteracao">Alteração</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">
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
                    </select>
                    <input type="date" name="prazo_alteracao" id="prazo_alteracao">
                    <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Observação">
                </div>
            </div>
            <div class="funcao">
                <div class="titulo">
                    <p id="planta">Planta Humanizada</p>
                    <i class="fas fa-chevron-down" id="toggle-options"></i>
                </div>
                <div class="opcoes" id="opcoes" style="display: none;">

                    <select name="planta_id" id="opcao_planta">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status_planta" id="status_planta">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                    </select>
                    <input type="date" name="prazo_planta" id="prazo_planta">
                    <input type="text" name="obs_planta" id="obs_planta" placeholder="Observação">
                </div>
            </div>
            <div class="funcao" id="status_funcao">
                <p id="status">Status</p>
                <select name="status_id" id="opcao_status">
                    <?php foreach ($status_imagens as $status): ?>
                        <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                            <?= htmlspecialchars($status['nome_status']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="buttons">
                <button type="button" id="btnAnterior" style="background: white; color: black"><i class="fa-solid fa-angle-left"></i></button>
                <button type="submit" id="salvar_funcoes">Salvar</button>
                <button type="button" id="btnProximo" style="background: white; color: black"><i class="fa-solid fa-angle-right"></i></button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="scriptObra.js"></script> <!-- Link para o seu script JS -->
    <script src="../script/sidebar.js"></script>


</body>

</html>