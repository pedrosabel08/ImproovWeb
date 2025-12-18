<?php
session_start();
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

// conectar ao banco (função em conexaoMain.php)
$conn = conectarBanco();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$clientes = obterClientes($conn);
$nivel_acesso = $_SESSION['nivel_acesso'];

// Monta um array de colaboradores por função (usando a tabela funcao_colaborador)
$colaboradores_por_funcao = [];
$sqlFc = "SELECT fc.funcao_id, c.idcolaborador, c.nome_colaborador
           FROM funcao_colaborador fc
           JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
           WHERE c.ativo = 1";
if ($resFc = $conn->query($sqlFc)) {
    while ($r = $resFc->fetch_assoc()) {
        $fid = $r['funcao_id'];
        if (!isset($colaboradores_por_funcao[$fid])) {
            $colaboradores_por_funcao[$fid] = [];
        }
        $colaboradores_por_funcao[$fid][] = $r;
    }
    $resFc->free();
}

// Garantir que colaboradores específicos (sempre presentes) estejam disponíveis
// (IDs solicitados pelo usuário). Buscamos explicitamente e mesclamos em $colaboradores
// caso não estejam presentes na lista padrão (ex.: inativos).
$always_present_ids = [9, 21];
$ids_to_fetch = implode(',', array_map('intval', $always_present_ids));
$sqlAlways = "SELECT idcolaborador, nome_colaborador FROM colaborador WHERE idcolaborador IN ($ids_to_fetch)";
if ($resAlways = $conn->query($sqlAlways)) {
    $existing = array_column($colaboradores, 'idcolaborador');
    while ($r = $resAlways->fetch_assoc()) {
        if (!in_array($r['idcolaborador'], $existing)) {
            $colaboradores[] = $r;
        }
    }
    $resAlways->free();
}

// Helper para renderizar <option> de colaboradores: encontra a função por nome (parcial),
// mostra primeiro os colaboradores atribuídos a essa função e, em seguida, um optgroup
// "Outros colaboradores" com o restante (permite escolher alguém não alocado).
function renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, $nomeBusca)
{
    $fid = null;
    foreach ($funcoes as $f) {
        if (isset($f['nome_funcao'])) {
            // comparação insensível (usa parcial para maior robustez)
            if (mb_strtolower($f['nome_funcao']) === mb_strtolower($nomeBusca) || mb_stripos(mb_strtolower($f['nome_funcao']), mb_strtolower($nomeBusca)) !== false) {
                $fid = $f['idfuncao'];
                break;
            }
        }
    }

    $assigned = [];
    if ($fid && isset($colaboradores_por_funcao[$fid])) {
        $assigned = $colaboradores_por_funcao[$fid];
    }

    // IDs dos atribuídos para filtrar
    $assigned_ids = array();
    foreach ($assigned as $a) {
        $assigned_ids[] = $a['idcolaborador'];
    }

    // Mapar colaboradores gerais por id para acesso rápido
    $all_map = [];
    foreach ($colaboradores as $c) {
        $all_map[$c['idcolaborador']] = $c;
    }

    // Exibir atribuídos primeiro
    foreach ($assigned as $colab) {
        echo '<option value="' . htmlspecialchars($colab['idcolaborador']) . '">' . htmlspecialchars($colab['nome_colaborador']) . '</option>';
    }

    // Construir lista de 'outros' (aqueles que não estão em assigned)
    $others = [];
    foreach ($all_map as $id => $colab) {
        if (!in_array($id, $assigned_ids)) {
            $others[$id] = $colab;
        }
    }

    if (!empty($others)) {
        echo '<optgroup label="Outros colaboradores">';
        foreach ($others as $colab) {
            echo '<option value="' . htmlspecialchars($colab['idcolaborador']) . '">' . htmlspecialchars($colab['nome_colaborador']) . '</option>';
        }
        echo '</optgroup>';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Obra</title>
    <link rel="stylesheet" href="styleObra.css">
    <link rel="stylesheet" href="popoverAcomp.css">
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
    <!-- Select2 for improved multi-select UI -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        /* Visual hint for image types */
        .tipo-desconhecido td { background: rgba(255, 235, 205, 0.5); }
        .tipo-planta-humanizada td { background: rgba(200, 230, 201, 0.3); }
        .tipo-unidade td { background: rgba(187, 222, 251, 0.12); }
    </style>
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
    <div class="container animate__animated animate__fadeIn">

        <header>
            <h1 id="nomenclatura" class="animate__animated animate__fadeInDown"></h1>

            <!-- Quick access links: in-page anchors + Drive, Fotográfico, Review Studio -->
            <div id="quickAccess" class="quick-access" aria-label="Acessos rápidos" style="display: none;">
                <!-- Internal anchors -->
                <a class="quick-link" href="#tabela-obra" title="Ir para Tabela" aria-hidden="false">
                    <i class="fa-solid fa-table-cells"></i>
                    <span class="qa-label">Tabela</span>
                </a>
                <a class="quick-link" href="#list_acomp" title="Ir para Histórico" aria-hidden="false">
                    <i class="fa-solid fa-list"></i>
                    <span class="qa-label">Histórico</span>
                </a>
                <a class="quick-link" href="#obsSection" title="Ir para Observações" aria-hidden="false">
                    <i class="fa-solid fa-note-sticky"></i>
                    <span class="qa-label">Observações</span>
                </a>
                <a class="quick-link" href="#secao-arquivos" title="Ir para Arquivos" aria-hidden="false">
                    <i class="fa-solid fa-file"></i>
                    <span class="qa-label">Arquivos</span>
                </a>

                <!-- External quick links -->
                <a id="quick_fotografico" class="quick-link" href="#" target="_blank" rel="noopener noreferrer" title="Fotográfico" aria-hidden="true">
                    <i class="fa-solid fa-camera"></i>
                    <span class="qa-label">Fotográfico</span>
                </a>
                <a id="quick_drive" class="quick-link" href="#" target="_blank" rel="noopener noreferrer" title="Drive" aria-hidden="true">
                    <i class="fa-brands fa-google-drive"></i>
                    <span class="qa-label">Drive</span>
                </a>
                <a id="quick_review" class="quick-link" href="#" target="_blank" rel="noopener noreferrer" title="Review Studio" aria-hidden="true">
                    <i class="fa-solid fa-folder-open"></i>
                    <span class="qa-label">Review</span>
                </a>
                <button id="altBtn" class="hidden">Flow Review</button>
            </div>
            <!-- Mobile hamburger for quick access (visible on small screens) -->
            <button id="quickHamburger" class="quick-hamburger" aria-label="Acessos rápidos" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Mobile quick access panel -->
            <div id="quickMobileMenu" class="quick-mobile-menu" aria-hidden="true">
                <div class="quick-mobile-inner">
                    <button id="quickMobileClose" class="quick-mobile-close" aria-label="Fechar menu"><i class="fa-solid fa-times"></i></button>
                    <nav id="quickMobileNav" class="quick-mobile-nav">
                        <!-- Items populated by JS: internal anchors and external links -->
                        <a class="mobile-link" id="mobile_tabela" href="#tabela-obra"><i class="fa-solid fa-table-cells"></i> <span>Tabela</span></a>
                        <a class="mobile-link" id="mobile_hist" href="#list_acomp"><i class="fa-solid fa-list"></i> <span>Histórico</span></a>
                        <a class="mobile-link" id="mobile_obs" href="#obsSection"><i class="fa-solid fa-note-sticky"></i> <span>Observações</span></a>
                        <a class="mobile-link" id="mobile_arquivos" href="#secao-arquivos"><i class="fa-solid fa-file"></i> <span>Arquivos</span></a>
                        <hr>
                        <a class="mobile-link" id="mobile_fotografico" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-camera"></i> <span>Fotográfico</span></a>
                        <a class="mobile-link" id="mobile_drive" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-google-drive"></i> <span>Drive</span></a>
                        <a class="mobile-link" id="mobile_review" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-folder-open"></i> <span>Review</span></a>
                        <button id="altBtn" class="hidden">Flow Review</button>

                    </nav>
                </div>
            </div>
        </header>
        <!-- Tabela para exibir as funções da obra -->
        <div class="filtro-tabela">
            <div class="filtro">
                <div class="filtros-select">
                    <select name="tipo_imagem" id="tipo_imagem" multiple>
                        <option value="0">Todos</option>
                    </select>

                    <select id="antecipada_obra" multiple size="2">
                        <option value="">Todos as imagens</option>
                        <option value="1">Antecipada</option>
                    </select>

                    <select name="imagem_status_etapa_filtro" id="imagem_status_etapa_filtro" multiple></select>
                    <select name="imagem_status_filtro" id="imagem_status_filtro" multiple></select>
                </div>

                <div id="prazos-list"></div>
                <div id="calendarMini" class="animate__animated" style="display: none;"></div>
            </div>

            <div class="buttons animate__animated">
                <div class="actions-menu">
                    <button id="actionsMenuBtn" class="menu-icon animate__animated" aria-expanded="false" aria-label="Ações da obra" title="Ações">
                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    </button>
                    <div id="actionsMenu" class="actions-menu-dropdown" aria-hidden="true">
                        <button id="editImagesBtn" class="action-item">Editar Imagens</button>
                        <button id="addImagem" class="action-item">Adicionar Imagem</button>
                        <?php if ($nivel_acesso === 1): ?>
                            <button id="importTxtBtn" class="action-item">Importar Imagens (TXT)</button>
                        <?php endif; ?>
                        <button id="editArquivos" class="action-item">Editar Arquivos</button>
                        <button id="addFollowup" class="action-item" onclick="gerarFollowUpPDF()">Follow Up</button>
                        <!-- <button id="clearFilters" class="action-item">Limpar filtros</button> -->
                        <button id="markInactiveBtn" class="action-item">Marcar Inativa</button>
                        <button id="fotograficoBtn" class="action-item">Fotográfico</button>
                    </div>
                </div>
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

            <div class="buttons-actions">
                <button id="copyColumn" class="tooltip" data-tooltip="Copiar coluna" style="width: max-content;"><i
                        class="fas fa-copy"></i></button>
                <button id="batch_actions" class="tooltip animate__animated" data-tooltip="Batch actions"
                    style="width: max-content;"><i class="fa-solid fa-gear"></i></button>
                <button id="acoesBtn">Ações</button>
            </div>

            <div id="acoesModal">
                <div class="modal-row" data-target="prazoField">Prazo
                    <div id="prazoField" class="modal-field" style="display: none;">
                        <input type="date" id="prazo_modal">
                    </div>
                </div>
                <div class="modal-row" data-target="etapaField">Etapa
                    <div id="etapaField" class="modal-field" style="display: none;">
                        <select name="opcao_status_modal" id="opcao_status_modal">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-row" data-target="statusField">Status
                    <div id="statusField" class="modal-field" style="display: none;">
                        <select id="statusSelectModal" name="statusSelectModal">
                            <?php foreach ($status_etapa as $statusEtapa): ?>
                                <option value="<?= htmlspecialchars($statusEtapa['id']); ?>">
                                    <?= htmlspecialchars($statusEtapa['nome_substatus']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-row" data-target="funcaoField">Função
                    <div id="funcaoField" class="modal-field" style="display: none;">
                        <select id="modal_funcao">
                            <option value="">-- Selecionar função --</option>
                            <?php foreach ($funcoes as $f): ?>
                                <option value="<?= htmlspecialchars($f['idfuncao']); ?>">
                                    <?= htmlspecialchars($f['nome_funcao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-row" data-target="colabField">Colaborador
                    <div id="colabField" class="modal-field" style="display: none;">
                        <select id="modal_colaborador">
                            <option value="">-- Selecionar colaborador --</option>
                            <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, ''); ?>
                        </select>
                    </div>
                </div>
                <div style="text-align: center; padding-top: 5px;">
                    <button id="btnAtualizar">✓ Atualizar</button>
                </div>
            </div>
            <div id="previewModal" class="modal"
                style="display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); background:#fff; border:1px solid #ccc; padding:20px; z-index:1000; width:300px; box-shadow:0 0 10px rgba(0,0,0,0.3);">
                <h3>Confirmação de Atualização</h3>
                <div id="previewContent" style="max-height:200px; overflow-y:auto; margin:10px 0;"></div>
                <button id="confirmUpdateBtn" style="margin-right:10px;">Confirmar</button>
                <button id="cancelUpdateBtn">Cancelar</button>
            </div>
            <div id="previewOverlay"
                style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.3); z-index:999;">
            </div>

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

            <?php if ($nivel_acesso === 1): ?>
                <div id="importTxtModal" class="modal" style="display:none;">
                    <form class="modal-content" id="importTxtForm" style="width:520px; max-width:95%;" enctype="multipart/form-data">
                        <h2>Importar Imagens (TXT)</h2>
                        <label for="importTxtFile">Arquivo TXT:</label>
                        <input type="file" id="importTxtFile" name="txtFile" accept=".txt,text/plain" required>
                        <div class="buttons" style="margin: auto; display:flex; gap:10px; justify-content:center;">
                            <button type="button" id="importTxtCancel">Cancelar</button>
                            <button type="submit" id="importTxtSubmit">Importar</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="tabela">
                <table id="tabela-obra">
                    <thead>
                        <tr id="linha-porcentagem"></tr>
                        <tr>
                            <th>Etapa</th>
                            <th class="resizable">Imagem<div class="resize-handle"></div>
                            </th>
                            <th>Status</th>
                            <th style="max-width: 15px;">Prazo</th>
                            <th onclick="mostrarFiltroColaborador('caderno')">Caderno</th>
                            <th onclick="mostrarFiltroColaborador('filtro')">Filtro de assets</th>
                            <th onclick="mostrarFiltroColaborador('modelagem')">Modelagem</th>
                            <th onclick="mostrarFiltroColaborador('composicao')">Composição</th>
                            <th onclick="mostrarFiltroColaborador('pre')">Pré-Finalização</th>
                            <th onclick="mostrarFiltroColaborador('finalizacao')">Finalização</th>
                            <th onclick="mostrarFiltroColaborador('pos_producao')">Pós-Produção</th>
                            <th onclick="mostrarFiltroColaborador('alteracao')">Alteração</th>
                            <!-- <th onclick="mostrarFiltroColaborador('planta')">Planta</th>
                            <th>Status</th> -->
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal para unificar acompanhamentos (populado pelo JS) -->
        <div id="unifyAcompanhamentoModal" class="modal" style="display:none;">
            <div class="modal-content" style="width:720px; max-width:95%;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <h2 style="margin:0;">Unificar Acompanhamentos</h2>
                    <button class="unify-close" style="background:transparent;border:none;font-size:20px;">&times;</button>
                </div>
                <div id="unifyGroupsList" style="max-height:60vh; overflow:auto;">
                    <!-- JS irá popular com grupos: cada grupo terá data, assunto, count e botões -->
                </div>
                <div style="text-align:right; margin-top:10px;">
                    <button id="unifyCloseBtn">Fechar</button>
                </div>
            </div>
        </div>

        <div id="infos-obra" style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">
            <div class="acompanhamentos">
                <div class="infos-obra-header">
                    <h1>Histórico</h1>
                    <div class="buttons-acomp" style="display: flex;">
                        <button id="acomp" class="btnAcompObs">+ Novo</button>
                        <button id="configAcomp" class="animate__animated" style="background-color: transparent; color: black;"><i class="fa-solid fa-gear"></i></button>
                    </div>
                </div>

                <!-- Acompanhamentos: filtros por categoria -->
                <div class="acomp-filters" aria-label="Filtrar acompanhamentos">
                    <button id="btn_acomp_todos" class="acomp-filter-btn active" data-category="todos">Todos</button>
                    <button id="btn_acomp_manuais" class="acomp-filter-btn" data-category="manuais">Manuais</button>
                    <button id="btn_acomp_entregas" class="acomp-filter-btn" data-category="entregas">Entregas</button>
                    <button id="btn_acomp_arquivos" class="acomp-filter-btn" data-category="arquivos">Arquivos</button>
                </div>

                <div id="list_acomp" class="list-acomp" aria-live="polite"></div>
                <button id="btnMostrarAcomps"><i class="fas fa-chevron-down"></i></button>
            </div>
        </div>
        <div id="infos-obra" class="infos-obra"
            style="width: 95%; margin: 30px auto; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.7);">

            <div id="obsSection" class="obs">
                <div class="infos-obra-header">
                    <h1>Informações da Obra</h1>
                    <button id="obsAdd" class="btnAcompObs">+ Novo</button>
                </div>
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
                        <label for="assets">Haverá necessidade de escolha de assets (modelos de mobiliário)
                            especifico?</label>
                        <input type="text" name="assets" id="assets">
                    </div>
                    <div class="campo">
                        <label for="comp_planta">Existe a necessidade das plantas humanizadas estarem compatibilizadas
                            com as imagens finais?</label>
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
                    <div class="campo link_campo">
                        <label for="">Link do Fotográfico:</label>
                        <input type="text" name="fotografico" id="fotografico"
                            style="font-size: 14px; border: none; width: 80ch;">
                    </div>
                    <div class="campo link_campo">
                        <label for="">Link do Drive:</label>
                        <input type="text" name="link_drive" id="link_drive"
                            style="font-size: 14px; border: none; width: 80ch;">
                    </div>
                    <div class="campo link_campo">
                        <label for="">Link do Review Studio:</label>
                        <input type="text" name="link_review" id="link_review"
                            style="font-size: 14px; border: none; width: 80ch;">
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


        <div id="modalAcompanhamento" class="modal">
            <div class="modal-content" style="width: 500px;">
                <h2 style="margin-bottom: 30px;">Acompanhamento por Email</h2>
                <div id="acompanhamentoConteudo">
                    <form id="adicionar_acomp">
                        <div class="radioButtons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <label><input type="radio" name="acompanhamento" value="Start do Projeto"> Start do
                                Projeto</label>
                            <label><input type="radio" name="acompanhamento" value="Prazo de dias úteis (45 dias)">
                                Prazo de dias úteis (45 dias)</label>
                            <label><input type="radio" name="acompanhamento" value="Recebimento de arquivos">
                                Recebimento de arquivos</label>
                            <label><input type="radio" name="acompanhamento" value="Prazo com a entrega (30/01)"> Prazo
                                com a entrega (30/01)</label>
                            <label><input type="radio" name="acompanhamento"
                                    value="Projeto pausado aguardando aprovação do cliente.">Projeto pausado aguardando
                                aprovação do cliente.</label>
                            <label><input type="radio" name="acompanhamento" value="Enviado os toons da fachada">Enviado
                                os toons da fachada</label>
                            <label><input type="radio" name="acompanhamento" value="Enviado imagens prévias"> Enviado
                                imagens prévias</label>
                        </div>


                        <!-- Campo de assunto -->
                        <div id="campo">
                            <label for="assunto">Assunto:</label>
                            <textarea name="assunto" id="assunto" name="assunto" style="width: 50%;"
                                required></textarea>
                        </div>

                        <!-- Campo de data -->
                        <div id="campo">
                            <label for="data">Data:</label>
                            <input type="date" name="data_acomp" id="data_acomp" required>
                        </div>

                        <!-- Botão para enviar -->
                        <button type="submit" id="add-acomp" style="width: max-content;margin: auto;">Adicionar
                            Acompanhamento</button>
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

        <!-- Secao Arquivos da Obra (inserida abaixo de Observação) -->
        <section id="secao-arquivos" class="secao arquivos-secao infos-obra">
            <div class="secao-titulo arquivos-header">
                <h2>Arquivos da Obra</h2>
                <div class="arquivos-actions">
                    <button id="btnAddArquivo" type="button" class="btn-add-arquivo" title="Adicionar novo arquivo">+ Novo</button>
                    <button id="btnRefreshArquivos" type="button" class="btn-refresh-arquivo" title="Recarregar lista">↻</button>
                </div>
            </div>
            <div id="arquivosWrapper" class="arquivos-wrapper">
                <div class="arquivos-head-row">
                    <div class="arq-col nome">Nome</div>
                    <div class="arq-col tipo">Tipo</div>
                    <div class="arq-col colaborador">Colaborador</div>
                    <div class="arq-col tamanho">Tamanho</div>
                    <div class="arq-col modificado">Modificado</div>
                </div>
                <div id="listaArquivos" class="arquivos-grid" aria-live="polite"></div>
            </div>
        </section>

    </div>
    <div class="form-edicao" id="form-edicao">
        <form id="form-add" method="post" action="insereFuncao.php">
            <div class="titulo-funcoes">
                <span id="campoNomeImagem"></span>
            </div> <input type="hidden" id="imagem_id" name="imagem_id" value="">
            <div class="modal-funcoes">
                <span id="mood"></span>
                <div class="modal-funcoes-inner">
                    <div class="modal-funcoes-left">
                        <div class="funcao_comp">
                            <div class="funcao">
                                <div class="titulo">
                                    <p id="caderno">Caderno</p>
                                    <i class="fas fa-chevron-down toggle-options"></i>
                                </div>
                                <div class="opcoes" style="display: none;">
                                    <select name="caderno_id" id="opcao_caderno">
                                        <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'caderno'); ?>
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
                                        <button type="button" onclick="abrirModal(this)" id="revisao_imagem_caderno">Adicionar
                                            Imagens</button>
                                    </div>
                                </div>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'filtro'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_filtro">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'modelagem'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_model">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'comp'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_comp">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'pre'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pre">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'final'); ?>
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
                            <input type="text" name="obs_finalizacao" id="obs_finalizacao"
                                placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_final">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'pos'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pos">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'alteracao'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_alt">Adicionar
                                    Imagens</button>
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
                                <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'planta'); ?>
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
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_ph">Adicionar
                                    Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao status_select" id="status_funcao" style="margin-bottom: 15px;width: max-content;">
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
                <div class="funcao render_add" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="render">
                        <p id="render_alta">Render</p>
                        <button id="addRender" class="buttons-form-add"
                            style=" padding: 3px 10px; font-size: 13px; background-color: steelblue;">Adicionar
                            render</button>
                        <label class="switch">
                            <input type="checkbox" id="notificar">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="funcao revisao_add" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="revisao">
                        <p id="revisao">Revisao</p>
                        <button id="addRevisao" class="buttons-form-add"
                            style=" padding: 3px 10px; font-size: 13px; background-color: steelgreen;">Adicionar
                            revisão</button>
                    </div>
                </div>
                <div class="buttons">
                    <button type="button" id="btnAnterior" style="background: white; color: black"><i
                            class="fa-solid fa-angle-left"></i></button>
                    <div>
                        <button type="submit" id="salvar_funcoes" class="buttons-form-add">Salvar</button>
                        <div id="loadingBar" style="display: none;">
                            <div class="progress"></div>
                        </div>
                    </div>
                    <button type="button" id="btnProximo" style="background: white; color: black"><i
                            class="fa-solid fa-angle-right"></i></button>
                </div>
            </div>
            <div class="modal-funcoes-right">
                <div id="modalFuncoesInfo">
                    <!-- Informações da imagem serão carregadas aqui via AJAX -->
                    <p class="info-placeholder">Carregando informações da imagem...</p>
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
                            <?php renderColabOptions($funcoes, $colaboradores_por_funcao, $colaboradores, 'final'); ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeObra">Nome Obra</label>
                        <select name="obra_id" id="opcao_obra_pos" required>
                            <option value="0">Selecione uma obra:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="imagem_id_pos">Nome Imagem</label>
                        <select id="imagem_id_pos" name="imagem_id_pos" required>
                            <option value="">Selecione uma imagem:</option>
                            <?php foreach ($imagens as $imagem): ?>
                                <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                    <?= htmlspecialchars($imagem['imagem_nome']); ?>
                                </option>
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

    <!-- MODAL ÚNICO -->
    <div id="modalUpload" style="display: none;">
        <div id="overlay" onclick="fecharModal()"
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9;"></div>
        <input type="hidden" id="funcao_id_revisao">
        <input type="hidden" id="nome_funcao_upload">

        <div
            style="position: fixed; top: 50%; left: 50%; width: 600px; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; z-index: 10;">
            <h2 id="etapaTitulo">1. Envio de Prévia</h2>

            <!-- Conteúdo da etapa 1 -->
            <div id="etapaPrevia">
                <div id="drop-area-previa" class="drop-area">
                    Arraste suas imagens aqui ou clique para selecionar
                    <input type="file" id="fileElemPrevia" accept="image/*" multiple style="display:none;">
                </div>
                <ul class="file-list" id="fileListPrevia"></ul>
                <div class="buttons-upload">
                    <button onclick="fecharModal()" style="background-color: red;">Cancelar</button>
                    <button onclick="enviarImagens()" style="background-color: green;">Enviar Prévia</button>
                </div>
            </div>

            <!-- Conteúdo da etapa 2 -->
            <div id="etapaFinal" style="display: none;">
                <div id="drop-area-final" class="drop-area">
                    Arraste o arquivo final aqui ou clique para selecionar
                    <input type="file" id="fileElemFinal" multiple style="display:none;">
                </div>
                <ul class="file-list" id="fileListFinal"></ul>
                <div class="buttons-upload">
                    <button onclick="fecharModal()" style="background-color: red;">Cancelar</button>
                    <button onclick="enviarArquivo()" style="background-color: green;">Enviar Arquivo Final</button>
                </div>
            </div>

        </div>
    </div>

    <div class="modal" id="modal_pdf">
        <div class="modal-content" style="margin: 20px auto">
            <div id="pdf-controls">
                <button onclick="prevPage()">⬅ Página anterior</button>
                <button onclick="nextPage()">Próxima página ➡</button>
                <span>Página: <span id="page-num"></span> / <span id="page-count"></span></span>
            </div>

            <canvas id="pdf-canvas"></canvas>
        </div>
    </div>

    <div id="modal_status">
        <div class="modal-content" style="margin: 0;">
            <h2 style="font-size: 16px;">Alterar Status</h2>
            <label for="statusSelect" style="font-size: 14px;">Selecione o novo status:</label>
            <select id="statusSelect" name="statusSelect" style="width: max-content; text-align: center; margin: auto;"
                required>
                <?php foreach ($status_etapa as $statusEtapa): ?>
                    <option value="<?= htmlspecialchars($statusEtapa['id']); ?>">
                        <?= htmlspecialchars($statusEtapa['nome_substatus']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="alterar_status"
                onclick="alterarStatus(this.getAttribute('data-imagemid'))">✅</button>
        </div>
    </div>

    <div id="modal_hist_status">
        <div class="modal-content" style="margin: 0;">
            <h2 style="font-size: 16px;">Histórico Status</h2>
            <div id="historico_container"></div>
        </div>
    </div>

    <!-- Adicione este botão onde quiser -->
    <!-- <button id="btnUploadAcompanhamento">Upload Acompanhamento Obra</button> -->

    <!-- Modal para upload -->
    <!-- Modal Upload -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <h2>Novo Upload</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <label>Projeto</label>
                <select name="obra_id" required>
                    <option value="">-- Selecione --</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Categoria</label>
                <select name="tipo_categoria" required>
                    <option value="1">Arquitetônico</option>
                    <option value="2">Referências</option>
                    <option value="3">Paisagismo</option>
                    <option value="4">Luminotécnico</option>
                    <option value="5">Estrutural</option>
                    <option value="6">Alterações</option>
                    <option value="7">Ângulo definido</option>
                </select>

                <label>Tipo de Imagem</label>
                <select name="tipo_imagem[]" multiple size="5" required>
                    <option value="Fachada">Fachada</option>
                    <option value="Imagem Interna">Interna</option>
                    <option value="Imagem Externa">Externa</option>
                    <option value="Unidade">Unidades</option>
                    <option value="Planta Humanizada">Plantas Humanizadas</option>
                </select>

                <label>Tipo de Arquivo</label>
                <select name="tipo_arquivo" required>
                    <option value="">-- Selecione --</option>
                    <option value="DWG">DWG</option>
                    <option value="PDF">PDF</option>
                    <option value="SKP">SKP</option>
                    <option value="IMG">IMG</option>
                    <option value="IFC">IFC</option>
                    <option value="Outros">Outros</option>
                </select>

                <label id="labelSufixo" style="display:none;">Sufixo</label>
                <select name="sufixo" id="sufixoSelect" style="display:none;">
                    <!-- options populated by script.js based on tipo_arquivo -->
                </select>

                <!-- Adicione dentro do form do modal -->
                <div id="refsSkpModo" style="display:none;">
                    <label>
                        <input type="radio" name="refsSkpModo" value="geral" checked> Enviar geral
                    </label>
                    <label>
                        <input type="radio" name="refsSkpModo" value="porImagem"> Enviar por imagem
                    </label>
                </div>

                <div id="referenciasContainer" style="max-height: 50vh; overflow-y: auto;"></div>

                <label>Arquivo</label>
                <input id="arquivoFile" type="file" name="arquivos[]" multiple required>

                <label>Descrição</label>
                <textarea name="descricao" rows="4"></textarea>

                <div class="checkbox-group">
                    <label><input type="checkbox" name="flag_substituicao" value="1"> Substituir existente</label>
                </div>

                <div class="buttons">
                    <button type="button" class="btn-close" id="closeModal">Cancelar</button>
                    <button type="submit" class="btn-submit">Enviar</button>
                </div>
            </form>

        </div>
    </div>

    <!-- Modal para upload específico por imagem (simplificado) -->
    <div class="modal" id="uploadModalImagem" style="display:none;">
        <div class="modal-content">
            <h2>Enviar arquivo (imagem)</h2>
            <form id="uploadFormImagem" enctype="multipart/form-data">
                <input type="hidden" name="obra_id" id="obra_id_img">
                <input type="hidden" name="imagem_id" id="imagem_id_img">
                <input type="hidden" name="tipo_imagem" id="tipo_imagem_img">

                <label>Categoria</label>
                <select name="tipo_categoria" required>
                    <option value="1">Arquitetônico</option>
                    <option value="2">Referências</option>
                    <option value="3">Paisagismo</option>
                    <option value="4">Luminotécnico</option>
                    <option value="5">Estrutural</option>
                    <option value="6">Alterações</option>
                    <option value="7">Ângulo definido</option>
                </select>

                <label>Tipo de Arquivo</label>
                <select name="tipo_arquivo" id="tipo_arquivo_img" required>
                    <option value="">-- Selecione --</option>
                    <option value="DWG">DWG</option>
                    <option value="PDF">PDF</option>
                    <option value="SKP">SKP</option>
                    <option value="IMG">IMG</option>
                    <option value="IFC">IFC</option>
                    <option value="Outros">Outros</option>
                </select>

                <label id="labelSufixoImg" style="display:none;">Sufixo</label>
                <select name="sufixo" id="sufixoSelectImg" style="display:none;"></select>

                <label>Arquivo</label>
                <input id="arquivoFileImg" type="file" name="arquivos[]" multiple required>

                <label>Descrição</label>
                <textarea name="descricao" rows="4"></textarea>

                <div class="checkbox-group">
                    <label><input type="checkbox" name="flag_substituicao" value="1"> Substituir existente</label>
                </div>

                <div class="buttons">
                    <button type="button" class="btn-close-img" id="closeModalImg">Cancelar</button>
                    <button type="submit" class="btn-submit-img">Enviar</button>
                </div>
            </form>
        </div>
    </div>


    <!-- HTML -->
    <div id="modalOverlay" class="modal-overlay">
        <div id="modalCard" class="modal-card">
            <h2 style="margin-bottom: 10px;">Envio de Prévias</h2>
            <p style="font-weight: bold;">O processo de envio de prévias está disponível na página principal.</p>
            <span style="font-size: 0.9rem; margin-top: 10px;">Você pode arrastar o card para movê-lo para Em aprovação
                e fazer o envio das imagens.</span>
            <button id="closeModal"
                style="background: red;color: white;font-size: 0.8rem;padding: 4px 8px;border-radius: 15px;width: max-content; margin: auto; margin-top: 15px;">Fechar</button>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Fotografico modal -->
    <div id="fotograficoModal" class="modal" style="display:none;">
        <div class="modal-content" style="width:480px; max-width:95%; padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Fotográfico - Informações</h3>
                <button id="closeFotografico" style="background:none;border:0;font-size:20px;">&times;</button>
            </div>
            <div id="fotograficoBody">
                <label>Endereço</label>
                <input type="text" id="fotografico_endereco" style="width:100%;" />
                <div id="fotografico_alturas_container" style="margin-top:8px;"></div>
                <button id="addAlturaBtn" class="btn" style="margin-top:8px;">Adicionar Altura</button>
                <div id="fotograficoAddAlturaForm" style="display:none; margin-top:8px;">
                    <label>Altura</label>
                    <input type="text" id="fotografico_altura_value" style="width:100%;" />
                    <label>Observações</label>
                    <input type="text" id="fotografico_altura_obs" style="width:100%;" />
                    <div style="margin-top:8px; display:flex; gap:8px;">
                        <button id="saveAlturaBtn" class="btn">Salvar Altura</button>
                        <button id="cancelAlturaBtn" class="btn">Cancelar</button>
                    </div>
                </div>
                <div class="buttonsFotografico">
                    <button id="saveFotograficoInfo" class="btn">Salvar Informações</button>
                    <button id="openRegistrarFotografico" class="btn">Registrar Fotográfico</button>
                </div>

                <hr style="margin:12px 0;" />
                <h4>Registros</h4>
                <div id="fotograficoRegistrosList" style="max-height:220px; overflow:auto; border:1px solid #eee; padding:8px; background:#fafafa;"></div>

                <div id="fotograficoRegistroForm" style="display:none; margin-top:8px; border-top:1px solid #eee; padding-top:8px;">
                    <label>Data</label>
                    <input type="date" id="fotografico_registro_data" />
                    <label style="margin-top:8px;">Observações</label>
                    <textarea id="fotografico_registro_obs" style="width:100%; height:80px;"></textarea>
                    <div style="margin-top:8px; display:flex; gap:8px;">
                        <button id="saveFotograficoRegistro" class="btn">Salvar Registro</button>
                        <button id="cancelFotograficoRegistro" class="btn">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="scriptObra.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/notificacoes.js"></script>
    <script src="../script/controleSessao.js"></script>

</body>

</html>