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

// Buscar a quantidade de funções do colaborador com status "Em andamento"
$colaboradorId = $_SESSION['idcolaborador'];
$funcoesCountSql = "SELECT COUNT(*) AS total_funcoes_em_andamento
                    FROM funcao_imagem
                    WHERE colaborador_id = ? AND status = 'Em andamento'";
$funcoesCountStmt = $conn->prepare($funcoesCountSql);
$funcoesCountStmt->bind_param("i", $colaboradorId);
$funcoesCountStmt->execute();
$funcoesCountResult = $funcoesCountStmt->get_result();

// Armazenar a quantidade na sessão
$funcoesCount = $funcoesCountResult->fetch_assoc();

$funcoesCountStmt->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Obra</title>
    <link rel="stylesheet" href="styleObra.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../css/modalSessao.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
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
        <div class="buttons-nav">
            <button id="altBtn" onclick="window.location.href='https://improov.com.br/sistema/Alteracao/'"><i class="fa-solid fa-pen-to-square"></i></button>
            <button onclick="document.querySelector('.acompanhamentos').scrollIntoView({behavior: 'smooth'})"><i class="fa-solid fa-circle-info"></i></button>
            <button onclick="document.querySelector('.filtro-tabela').scrollIntoView({behavior: 'smooth'})"><i class="fa-solid fa-info"></i></button>
        </div>

        <!-- Tabela para exibir as funções da obra -->
        <div class="filtro-tabela">
            <div class="filtro">
                <div class="filtros-select">
                    <select name="tipo_imagem" id="tipo_imagem">
                        <option value="0">Todos</option>
                    </select>

                    <select id="antecipada_obra">
                        <option value="">Todos as imagens</option>
                        <option value="1">Antecipada</option>
                    </select>

                    <select name="imagem_status_filtro" id="imagem_status_filtro">
                        <option value="">Selecione um status</option>
                    </select>
                </div>

                <div id="prazos-list"></div>
                <div id="calendarMini"></div>

            </div>

            <div class="buttons">
                <button id="editImagesBtn">Editar Imagens</button>
                <button id="addImagem">Adicionar Imagem</button>
                <button id="editArquivos">Editar Arquivos</button>
                <button id="addFollowup" onclick="gerarFollowUpPDF()">Follow Up</button>
                <!-- <button id="flowReviewBtn">Flow Review</button> -->
            </div>

            <div class="contagem_imagens">
                <p id="imagens-totais"></p>
                <p id="antecipadas"></p>
                <p id="revisoes"></p>
            </div>

            <div id="estrela-container" style="display: none;">
                <span class="estrela" id="estrela1">★</span>
                <span class="estrela" id="estrela2">★</span>
                <span class="estrela" id="estrela3">★</span>
                <span class="estrela" id="estrela4">★</span>
                <span class="estrela" id="estrela5">★</span>
            </div>



            <button id="copyColumn" class="tooltip" data-tooltip="Copiar coluna" style="width: max-content;">
                <i class="fas fa-copy"></i>
            </button>


            <div id="editImagesModal" style="display: none;">
                <div class="modal-content-images" style="overflow-y: auto; max-height: 600px; width: 50%;">
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
                        <tr id="linha-porcentagem">
                        </tr>
                        <tr>
                            <th class="resizable">Imagem<div class="resize-handle"></div>
                            </th>
                            <th>Status</th>
                            <th style="max-width: 15px;">Prazo</th>
                            <th onclick="mostrarPorcentagem('caderno')">Caderno</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('filtro')">Filtro de assets</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('modelagem')">Modelagem</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('composicao')">Composição</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('pre')">Pré-Finalização</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('finalizacao')">Finalização</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('pos_producao')">Pós-Produção</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('alteracao')">Alteração</th>
                            <th>Status</th>
                            <th onclick="mostrarPorcentagem('planta')">Planta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">
            <div class="acompanhamentos">
                <h1>Histórico</h1>
                <button id="acomp" class="btnAcompObs">Acompanhamento</button>

                <div id="list_acomp" class="list-acomp"></div>
                <button id="btnMostrarAcomps"><i class="fas fa-chevron-down"></i></button>
            </div>
        </div>
        <div id="infos-obra" class="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">

            <div class="obs">
                <h1>Informações da Obra</h1>
                <button id="obsAdd" class="btnAcompObs">Adicionar Informação</button>

                <div id="briefing">
                    <div class="campo">
                        <label for="nivel">Qual o nível de padrão do empreendimento?</label>
                        <input type="text" name="nivel" id="nivel">
                    </div>
                    <div class="campo">
                        <label for="conceito">Qual o conceito do empreendimento?</label>
                        <input type="text" name="conceito" id="conceito">
                    </div>
                    <div class="campo">
                        <label for="valor_media">Qual a faixa média de valor dos apartamentos?</label>
                        <input type="text" name="valor_media" id="valor_media">
                    </div>
                    <div class="campo">
                        <label for="outro_padrao">Já tem algum outro empreendimento no mesmo padrão?</label>
                        <input type="text" name="outro_padrao" id="outro_padrao">
                    </div>
                    <div class="campo">
                        <label for="assets">Haverá necessidade de escolha de assets (modelos de mobiliário) especifico?</label>
                        <input type="text" name="assets" id="assets">
                    </div>
                    <div class="campo">
                        <label for="comp_planta">Existe a necessidade das plantas humanizadas estarem compatibilizadas com as imagens finais?</label>
                        <input type="text" name="comp_planta" id="comp_planta">
                    </div>
                    <div class="campo">
                        <label for="vidro">Cor dos vidros:</label>
                        <input type="text" name="vidro" id="vidro">
                    </div>
                    <div class="campo">
                        <label for="esquadria">Cor das esquadrias:</label>
                        <input type="text" name="esquadria" id="esquadria">
                    </div>
                    <div class="campo">
                        <label for="soleira">Cor das soleiras/pingadeiras:</label>
                        <input type="text" name="soleira" id="soleira">
                    </div>
                    <div class="campo">
                        <label for="acab_calcadas">Acabamento das calçadas:</label>
                        <input type="text" name="acab_calcadas" id="acab_calcadas">
                    </div>
                    <div class="campo">
                        <label for="">Link do Fotográfico:</label>
                        <input type="text" name="link_drive" id="link_drive" style="color: blue; font-size: 14px; border: none; width: 100ch;">
                    </div>
                    <div class="campo">
                        <label for="">Local da obra:</label>
                        <input type="text" name="local" id="local">
                    </div>
                    <div class="campo">
                        <label for="">Altura drone:</label>
                        <input type="text" name="altura_drone" id="altura_drone">
                    </div>

                </div>
                <div class="infos-container">
                    <h2>Observações</h2>
                    <table id="tabelaInfos">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- As linhas serão adicionadas aqui via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <div class="modal" id="modalArquivos">
            <form action="" id="formArquivos">
                <div class="modal-content" style="overflow-y: auto;">
                    <h2 style="margin-bottom: 15px;">Editar Arquivos</h2>
                    <div class="arquivos-container">
                        <div class="arquivo-item">
                            <label>
                                <span>Arquivos Fachada</span>
                                <input type="checkbox" class="tipo-imagem" data-tipo="Fachada">
                                <input type="date" class="data-recebimento" id="data-fachada" disabled>
                            </label>
                            <div class="subtipos">
                                <label>DWG<input type="checkbox"> </label>
                                <label>PDF<input type="checkbox"> </label>
                                <label>3D ou Referências/Mood<input type="checkbox"> </label>
                                <label>Paisagismo<input type="checkbox"> </label>
                            </div>
                        </div>

                        <div class="arquivo-item">
                            <label>
                                <span>Arquivos Imagens Externas</span>
                                <input type="checkbox" class="tipo-imagem" data-tipo="Imagem Externa">
                                <input type="date" class="data-recebimento" id="data-imagens-externas" disabled>
                            </label>
                            <div class="subtipos">
                                <label>DWG<input type="checkbox"> </label>
                                <label>PDF<input type="checkbox"> </label>
                                <label>3D ou Referências/Mood<input type="checkbox"> </label>
                                <label>Paisagismo<input type="checkbox"> </label>
                            </div>
                        </div>

                        <div class="arquivo-item">
                            <label>
                                <span>Arquivos Internas Áreas Comuns</span>
                                <input type="checkbox" class="tipo-imagem" data-tipo="Imagem Interna">
                                <input type="date" class="data-recebimento" id="data-internas-comuns" disabled>
                            </label>
                            <div class="subtipos">
                                <label>DWG<input type="checkbox"> </label>
                                <label>PDF<input type="checkbox"> </label>
                                <label>3D ou Referências/Mood<input type="checkbox"> </label>
                                <label>Luminotécnico<input type="checkbox"> </label>
                            </div>
                        </div>

                        <div class="arquivo-item">
                            <label>
                                <span>Arquivos Unidades</span>
                                <input type="checkbox" class="tipo-imagem" data-tipo="Unidade">
                                <input type="date" class="data-recebimento" id="data-unidades" disabled>
                            </label>
                            <div class="subtipos">
                                <label>DWG<input type="checkbox"> </label>
                                <label>PDF<input type="checkbox"> </label>
                                <label>3D ou Referências/Mood<input type="checkbox"> </label>
                                <label>Unidades Definidas<input type="checkbox"> </label>
                                <label>Luminotécnico<input type="checkbox"> </label>
                            </div>
                        </div>
                    </div>

                    <div class="arquivo-actions">
                        <input type="date" name="data_arquivos" id="data_arquivos" required>
                        <button type="button" id="salvarArquivo">Salvar</button>
                    </div>
                </div>
            </form>
        </div>



        <div id="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">
            <!-- <button id="follow-up">Follow-up</button> -->

            <div class="obra-identificacao">
                <h4 id="data_inicio_obra"></h4>
                <h4 id="prazo_obra"></h4>
                <h4 id="dias_trabalhados"></h4>
            </div>

            <div class="obra-acompanhamento">

                <!-- <?php
                        // Exibir somente se o usuário tiver nível de acesso 1
                        if (isset($_SESSION['logado']) && $_SESSION['logado'] === true && $_SESSION['nivel_acesso'] == 1) {
                        ?>
                    <button id="orcamento" style="display: block;">Orçamento</button>
                <?php
                        }
                ?>
                <button id="orcamento" style="display: none;">Orçamento</button> -->
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

            <div class="obra-imagens">
                <h4 id="total_imagens"></h4>
                <h4 id="total_imagens_antecipadas"></h4>
                <!-- <div id="funcoes" style="display: flex;flex-wrap: wrap; gap: 50px; justify-content: space-around;">
                </div>
                <div id="grafico">
                    <canvas id="graficoPorcentagem" width="400" height="200"></canvas>
                </div> -->
            </div>

        </div>

        <div id="modalAcompanhamento" class="modal">
            <div class="modal-content" style="width: 500px;">
                <h2 style="margin-bottom: 30px;">Acompanhamento por Email</h2>
                <div id="acompanhamentoConteudo">
                    <form id="adicionar_acomp">
                        <div class="radioButtons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <label><input type="radio" name="acompanhamento" value="Start do Projeto"> Start do Projeto</label>
                            <label><input type="radio" name="acompanhamento" value="Prazo de dias úteis (45 dias)"> Prazo de dias úteis (45 dias)</label>
                            <label><input type="radio" name="acompanhamento" value="Recebimento de arquivos"> Recebimento de arquivos</label>
                            <label><input type="radio" name="acompanhamento" value="Prazo com a entrega (30/01)"> Prazo com a entrega (30/01)</label>
                            <label><input type="radio" name="acompanhamento" value="Projeto pausado aguardando aprovação do cliente.">Projeto pausado aguardando aprovação do cliente.</label>
                            <label><input type="radio" name="acompanhamento" value="Enviado os toons da fachada">Enviado os toons da fachada</label>
                            <label><input type="radio" name="acompanhamento" value="Enviado imagens prévias"> Enviado imagens prévias</label>
                        </div>


                        <!-- Campo de assunto -->
                        <div id="campo">
                            <label for="assunto">Assunto:</label>
                            <textarea name="assunto" id="assunto" name="assunto" style="width: 50%;" required></textarea>
                        </div>

                        <!-- Campo de data -->
                        <div id="campo">
                            <label for="data">Data:</label>
                            <input type="date" name="data_acomp" id="data_acomp" required>
                        </div>

                        <!-- Botão para enviar -->
                        <button type="submit" id="add-acomp" style="width: max-content;margin: auto;">Adicionar Acompanhamento</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="modalObservacao" class="modal">
            <div class="modal-content" style="width: 500px;">
                <h2 style="margin-bottom: 30px;">Observação</h2>
                <div id="acompanhamentoConteudo">
                    <form id="adicionar_observacao" style="align-items: center;">
                        <!-- Campo de descrição -->
                        <input type="hidden" id="descricaoId">

                        <div id="campo">
                            <label for="desc">Descrição:</label>
                            <textarea name="desc" id="desc" name="desc" required></textarea>
                        </div>

                        <!-- Botão para enviar -->
                        <div class="buttons" style="margin-top: 15px;">
                            <button type="submit" id="add-acomp">Salvar</button>
                            <button id="deleteObs" style="background-color: red;">Excluir</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
    <div class="form-edicao" id="form-edicao">
        <form id="form-add" method="post" action="insereFuncao.php">
            <div class="titulo-funcoes">
                <span id="campoNomeImagem"></span>
            </div> <input type="hidden" id="imagem_id" name="imagem_id" value="">
            <div class="modal-funcoes">
                <span id="mood"></span>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_caderno" id="prazo_caderno">
                            <input type="text" name="obs_caderno" id="obs_caderno" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_caderno">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_filtro" id="prazo_filtro" placeholder="Data">
                            <input type="text" name="obs_filtro" id="obs_filtro" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_filtro">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_modelagem" id="prazo_modelagem">
                            <input type="text" name="obs_modelagem" id="obs_modelagem" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_model">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_comp" id="prazo_comp">
                            <input type="text" name="obs_comp" id="obs_comp" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_comp">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="pre">Pré-Finalização</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" style="display: none;">
                            <select name="opcao_pre" id="opcao_pre">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_pre" id="status_pre">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_pre" id="prazo_pre">
                            <input type="text" name="obs_pre" id="obs_pre" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pre">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_finalizacao" id="prazo_finalizacao">
                            <input type="text" name="obs_finalizacao" id="obs_finalizacao" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_final">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">

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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_pos" id="prazo_pos">
                            <input type="text" name="obs_pos" id="obs_pos" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pos">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_alteracao" id="prazo_alteracao">
                            <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_alt">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
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
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_planta" id="prazo_planta">
                            <input type="text" name="obs_planta" id="obs_planta" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_ph">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao" id="status_funcao" style="margin-bottom: 15px;width: max-content;">
                    <p id="status">Status</p>
                    <select name="status_id" id="opcao_status">
                        <?php foreach ($status_imagens as $status): ?>
                            <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                <?= htmlspecialchars($status['nome_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_hold" id="status_hold" multiple style="width: 200px;">
                        <option value="Paisagismo">Paisagismo</option>
                        <option value="Mood">Mood</option>
                        <option value="Interiores">Interiores</option>
                        <option value="Luminotécnico">Luminotécnico</option>
                        <option value="Arquitetônico">Arquitetônico</option>
                        <option value="Definição de unidade">Definição de unidade</option>
                        <option value="Aguardando aprovações">Aguardando aprovações</option>
                        <option value="Aguardando arquivos">Aguardando arquivos</option>
                    </select>
                </div>
                <div class="funcao" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="render">
                        <p id="render_alta">Render</p>
                        <button id="addRender" class="buttons-form-add" style=" padding: 3px 10px; font-size: 13px; background-color: steelblue;">Adicionar render</button>
                        <label class="switch">
                            <input type="checkbox" id="notificar">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="funcao" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="revisao">
                        <p id="revisao">Revisao</p>
                        <button id="addRevisao" class="buttons-form-add" style=" padding: 3px 10px; font-size: 13px; background-color: steelgreen;">Adicionar revisão</button>
                    </div>
                </div>
                <div class="buttons">
                    <button type="button" id="btnAnterior" style="background: white; color: black"><i class="fa-solid fa-angle-left"></i></button>
                    <div>
                        <button type="submit" id="salvar_funcoes" class="buttons-form-add">Salvar</button>
                        <div id="loadingBar" style="display: none;">
                            <div class="progress"></div>
                        </div>
                    </div>
                    <button type="button" id="btnProximo" style="background: white; color: black"><i class="fa-solid fa-angle-right"></i></button>
                </div>
            </div>

        </form>
    </div>

    <div class="tooltip-box" id="tooltip"></div>

    <div id="modal_pos" class="modal hidden">
        <div class="modal-content" style="width: 40%; max-height: 80%; overflow-y: auto; margin: 100px auto;">
            <span class="close">&times;</span>
            <button id="deleteButton">Excluir</button>
            <div id="form-inserir">
                <h2>Formulário de Dados</h2>
                <form id="formPosProducao">
                    <div>
                        <label for="nomeFinalizador">Nome Finalizador</label>
                        <select name="final_id" id="opcao_finalizador" required>
                            <option value="0">Selecione um colaborador:</option>
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeObra">Nome Obra</label>
                        <select name="obra_id" id="opcao_obra_pos" required>
                            <option value="0">Selecione uma obra:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="imagem_id_pos">Nome Imagem</label>
                        <select id="imagem_id_pos" name="imagem_id_pos" required>
                            <option value="">Selecione uma imagem:</option>
                            <?php foreach ($imagens as $imagem): ?>
                                <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                    <?= htmlspecialchars($imagem['imagem_nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="caminhoPasta">Caminho Pasta</label>
                        <input type="text" id="caminhoPasta" name="caminho_pasta">
                    </div>

                    <div>
                        <label for="numeroBG">Número BG</label>
                        <input type="text" id="numeroBG" name="numero_bg">
                    </div>

                    <div>
                        <label for="referenciasCaminho">Referências/Caminho</label>
                        <input type="text" id="referenciasCaminho" name="refs">
                    </div>

                    <div>
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="obs" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="status">Revisão</label>
                        <select name="status_id" id="opcao_status_pos">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="id-pos" id="id-pos" hidden>
                    <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">

                    <div>
                        <label for="status_pos">Status</label>
                        <input type="checkbox" name="status_pos" id="status_pos" disabled>
                    </div>

                    <div>
                        <label for="nome_responsavel">Nome Responsável</label>
                        <select name="responsavel_id" id="responsavel_id">
                            <option value="14" id="Adriana">Adriana</option>
                            <option value="28" id="Eduardo">Eduardo</option>

                        </select>
                    </div>

                    <input type="hidden" id="render_id_pos" name="render_id_pos">


                    <div>
                        <button type="submit">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal" id="modal-meta" style="display: none;">
        <div id="modal-content-meta" class="modal-content-meta">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2>🎉 Meta atingida! 🎉</h2>
            <h3 id="metas">A meta de 100 foi atingida por Pedro na função Caderno.</h3>
        </div>
    </div>

    <div id="modalLogs" class="modal">
        <div class="modal-content-log">
            <div class="nomes">
                <h4 id="nome_funcao_log" style="text-align: center;"></h4>
            </div>
            <table id="tabela-logs">
                <thead>
                    <tr>
                        <th>Status Anterior</th>
                        <th>Status Novo</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
    </div>

    <!-- Popover unificado -->
    <div id="popover-tarefas" class="popover oculto">
        <!-- Tarefas -->
        <div class="secao">
            <div class="secao-titulo secao-tarefas" onclick="toggleSecao('tarefas')">
                <strong>Tarefas</strong>
                <span id="badge-tarefas" class="badge-interna"></span>
            </div>
            <div id="conteudo-tarefas" class="secao-conteudo"></div>
        </div>

        <!-- Notificações -->
        <div class="secao">
            <div class="secao-titulo secao-notificacoes">
                <strong>Notificações</strong>
                <span id="badge-notificacoes" class="badge-interna"></span>
            </div>
            <div id="conteudo-notificacoes" class="secao-conteudo">
            </div>
        </div>
        <button id="btn-ir-revisao">Ir para Revisão</button>
    </div>

    <div id="modalSessao" class="modal-sessao">
        <div class="modal-conteudo">
            <h2>Sessão Expirada</h2>
            <p>Sua sessão expirou. Deseja continuar?</p>
            <button onclick="renovarSessao()">Continuar Sessão</button>
            <button onclick="sair()">Sair</button>
        </div>
    </div>


    <div id="calendarModal">
        <div class="close-btn" onclick="closeModal()">&times;</div>
        <div id="calendarContainer">
            <div id="calendarFull"></div>
        </div>
    </div>

    <!-- Modal simples para adicionar evento -->
    <div id="eventModal">
        <div class="eventos">
            <h3>Evento</h3>
            <form id="eventForm">
                <input type="hidden" name="id" id="eventId">
                <label>Título:</label>
                <input type="text" name="title" id="eventTitle" required>
                <label>Tipo de Evento:</label>
                <select name="eventType" id="eventType" required>
                    <option value="">Selecione</option>
                    <option value="Entrega">Entrega</option>
                    <option value="Arquivos">Arquivos</option>
                    <option value="Reunião">Reunião</option>
                    <option value="Outro">Outro</option>
                </select>
                <label>Data:</label>
                <input type="date" name="date" id="eventDate" required>
                <div class="buttons">
                    <button type="submit" style="background-color: green;">Salvar</button>
                    <button type="button" style="background-color: red;" onclick="deleteEvent()">Excluir</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL -->
    <div id="modalUpload" style="display: none;">
        <div id="overlay" onclick="fecharModal()" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9;"></div>
        <input type="hidden" id="funcao_id_revisao">
        <input type="hidden" id="nome_funcao_upload">
        <div style="position: fixed; top: 50%; left: 50%; width: 600px; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; z-index: 10;">
            <h2>Upload de Imagens</h2>

            <!-- Dropzone -->
            <div id="drop-area">
                Arraste suas imagens aqui ou clique para selecionar
                <input type="file" id="fileElem" accept="image/*" multiple style="display:none;">
            </div>

            <!-- Lista -->
            <ul class="file-list" id="fileList"></ul>

            <!-- Botões -->
            <div class="buttons-upload">
                <button onclick="fecharModal()" id="cancelar" style="background-color: red;">Cancelar</button>
                <button class="upload" onclick="enviarImagens()" id="enviar" style="background-color: green;">Enviar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="scriptObra2.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/notificacoes.js"></script>
    <script src="../script/controleSessao.js"></script>

    <script>
        // Converte o valor do COUNT para JSON e armazena no localStorage
        const funcoesTEA = <?php echo json_encode($funcoesCount['total_funcoes_em_andamento']); ?>;
        localStorage.setItem('funcoesTEA', funcoesTEA);
    </script>

</body>

</html>