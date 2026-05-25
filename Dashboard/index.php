<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// session_start();

require_once __DIR__ . '/../conexao.php';
include '../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];
$nivel_acesso = $_SESSION['nivel_acesso'];

// Consulta para contar as tarefas pendentes
$sql_pendentes = "SELECT COUNT(*) as count_pendentes FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ?";
$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $idcolaborador);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
$row_pendentes = $result_pendentes->fetch_assoc();
$count_pendentes = $row_pendentes['count_pendentes'];

// Consulta para imagens pendentes
$sql_imagens = "SELECT 
        i.imagem_nome, 
        o.nome_obra ,
i.prazo
    FROM 
        imagens_cliente_obra i 
    JOIN 
        funcao_imagem f ON i.idimagens_cliente_obra = f.imagem_id 
    JOIN 
        obra o ON o.idobra = i.obra_id  -- Supondo que o nome da tabela da obra seja 'obra'
    WHERE 
        f.colaborador_id = ?
        AND f.status <> 'Finalizado'
		AND i.prazo IS NOT NULL
        LIMIT 10";
$stmt_imagens = $conn->prepare($sql_imagens);
$stmt_imagens->bind_param("i", $idcolaborador);
$stmt_imagens->execute();
$result_imagens = $stmt_imagens->get_result();

$sql_grafico = "SELECT COUNT(*) as imagens, SUM(valor) as total, MONTH(prazo) as mes from funcao_imagem WHERE colaborador_id = ? GROUP BY MONTH(prazo)";
$stmt_grafico = $conn->prepare($sql_grafico);
$stmt_grafico->bind_param("i", $idcolaborador);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();

// Consulta para o total de produção
$sql_total = "SELECT ROUND(SUM(fi.valor)) AS total_producao FROM funcao_imagem fi WHERE fi.colaborador_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $idcolaborador);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$row_total = $result_total->fetch_assoc();
$count_total = $row_total['total_producao'];

$stmt_pendentes->close();
$stmt_imagens->close();
$stmt_total->close();

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
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('addClienteObra.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <!-- <link rel="stylesheet" href="<?php echo asset_url('../PaginaPrincipal/styleIndex.css'); ?>"> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>


<body>

    <?php

    include '../sidebar.php';

    ?>

    <div class="main-content">
        <!-- Cabeçalho do Dashboard -->
        <div class="dashboard-header">
            <div class="page-header-left">
                <img id="gif" src="../gif/assinatura_preto.gif" alt="Flow" class="dashboard-logo">
                <div class="dashboard-header-copy">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Visão geral da operação e produção</p>
                </div>
            </div>
            <?php if (in_array((int) $nivel_acesso, [1, 5], true)): ?>
                <button id="btnAddClienteObra" type="button">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>Iniciar Projeto</span>
                </button>
            <?php endif; ?>
        </div>

        <!-- KPIs -->
        <div class="stats-container">
            <?php if ($nivel_acesso == 1): ?>
                <!-- Total da empresa (orçamento) -->
                <div class="kpi-card" data-tone="accent">
                    <div class="kpi-head">
                        <span class="kpi-label">Total da empresa (R$)</span>
                        <span class="kpi-icon"><i class="fa-solid fa-chart-line"></i></span>
                    </div>
                    <div id="total_orcamentos" class="kpi-value kpi-value--sensitive"></div>
                    <div class="kpi-meta">
                        <span class="kpi-trend is-flat" id="trend_orcamento"><i class="fa-solid fa-minus"></i></span>
                        <span class="kpi-sub">Receita consolidada</span>
                    </div>
                </div>

                <!-- Total da empresa (produção) -->
                <div class="kpi-card" data-tone="success">
                    <div class="kpi-head">
                        <span class="kpi-label">Total produção (R$)</span>
                        <span class="kpi-icon"><i class="fa-solid fa-layer-group"></i></span>
                    </div>
                    <div id="total_producao" class="kpi-value kpi-value--sensitive"></div>
                    <div class="kpi-meta">
                        <span class="kpi-trend is-flat" id="trend_producao"><i class="fa-solid fa-minus"></i></span>
                        <span class="kpi-sub" id="producao_delta_label">vs. mês anterior</span>
                    </div>
                </div>

                <!-- Obras ativas -->
                <div class="kpi-card" data-tone="info">
                    <div class="kpi-head">
                        <span class="kpi-label">Obras ativas</span>
                        <span class="kpi-icon"><i class="fa-solid fa-building"></i></span>
                    </div>
                    <div id="obras_ativas" class="kpi-value"></div>
                    <div class="kpi-meta">
                        <span class="kpi-trend is-flat" id="trend_obras"><i class="fa-solid fa-minus"></i></span>
                        <span class="kpi-sub" id="obras_delta_label">vs. 3 meses atrás</span>
                    </div>
                </div>
            <?php else: ?>
                <!-- Colaborador: imagens pendentes -->
                <div class="kpi-card" data-tone="warning">
                    <div class="kpi-head">
                        <span class="kpi-label">Imagens pendentes</span>
                        <span class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                    </div>
                    <div class="kpi-value"><?php echo (int) $count_pendentes; ?></div>
                    <div class="kpi-meta">
                        <span class="kpi-sub">Fila atual</span>
                    </div>
                </div>

                <!-- Colaborador: produção acumulada -->
                <div class="kpi-card" data-tone="success">
                    <div class="kpi-head">
                        <span class="kpi-label">Total de produção (R$)</span>
                        <span class="kpi-icon"><i class="fa-solid fa-layer-group"></i></span>
                    </div>
                    <div class="kpi-value kpi-value--sensitive"><?php echo 'R$ ' . number_format((float) $count_total, 2, ',', '.'); ?></div>
                    <div class="kpi-meta">
                        <span class="kpi-sub">Produção acumulada</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>


        <main>
            <div class="kanban">
                <div class="kanban-box kanban-box--onboarding" id="onboarding-box">
                    <div class="header">
                        <div class="header-copy">
                            <div class="title"><i class="fa-solid fa-clipboard-check"></i><span>Onboarding Pendente</span></div>
                            <p class="column-subtitle">Checklist operacional em aberto</p>
                        </div>
                        <span class="task-count" id="count-onboarding">0</span>
                    </div>
                    <div class="content" id="onboarding-cards"></div>
                </div>

                <div class="kanban-box kanban-box--hold" id="hold-box">
                    <div class="header">
                        <div class="header-copy">
                            <div class="title"><i class="fa-solid fa-pause"></i><span>HOLD</span></div>
                            <p class="column-subtitle">Dependências bloqueando o fluxo</p>
                        </div>
                        <span class="task-count" id="count-hold">0</span>
                    </div>
                    <div class="content" id="hold-cards"></div>
                </div>

                <div class="kanban-box kanban-box--waiting" id="esperando-box">
                    <div class="header">
                        <div class="header-copy">
                            <div class="title"><i class="fa-solid fa-clock"></i><span>Esperando iniciar</span></div>
                            <p class="column-subtitle">Prontas para entrar em produção</p>
                        </div>
                        <span class="task-count" id="count-andamento">0</span>
                    </div>
                    <div class="content" id="andamento-cards"></div>
                </div>

                <div class="kanban-box kanban-box--production" id="producao-box">
                    <div class="header">
                        <div class="header-copy">
                            <div class="title"><i class="fa-solid fa-play"></i><span>Em produção</span></div>
                            <p class="column-subtitle">Obras ativas em execução</p>
                        </div>
                        <span class="task-count" id="count-finalizadas">0</span>
                    </div>
                    <div class="content" id="finalizadas-cards"></div>
                </div>
            </div>
        </main>
        <div class="modalInfos" id="modalInfos">
            <div id="infos-obra">
                <!-- <button id="follow-up">Follow-up</button> -->

                <div class="obra-identificacao">
                    <h3 id="nomenclatura"></h3>
                    <h4 id="data_inicio"></h4>
                    <h4 id="dias_trabalhados"></h4>
                    <div id="prazos-list"></div>

                </div>

                <div class="obra-acompanhamento">
                    <!-- <button id="acompanhamento">Acompanhamento</button> -->
                    <button id="orcamento">Orçamento</button>
                    <button id="obra" style="background-color: darkcyan;"
                        onclick="window.location.href='https://improov.com.br/sistema/Dashboard/obra.php'">Ir para a
                        tela da obra</button>
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
                    <!-- <h4 id="classificacao"></h4> -->
                    <div id="funcoes"></div>
                    <div id="grafico">
                        <canvas id="graficoPorcentagem" width="400" height="200"></canvas>
                    </div>
                </div>

                <div class="acompanhamentos">
                    <h1>Histórico</h1>
                    <button id="acomp" class="btnAcompObs">Acompanhamento</button>

                    <div id="list_acomp" class="list-acomp"></div>
                    <button id="btnMostrarAcomps"><i class="fas fa-chevron-down"></i> Mostrar Todos</button>
                </div>
            </div>
        </div>

    </div>

    <?php if (in_array((int) $nivel_acesso, [1, 5], true)): ?>
        <div id="modalAddClienteObra">
            <div class="onb-modal-shell">
                <div class="onb-modal-header">
                    <div>
                        <span class="onb-kicker">Flow Start</span>
                        <h2>Iniciar Projeto</h2>
                        <p>Transforme o start da obra em um onboarding operacional rastreável e padronizado.</p>
                    </div>
                    <button type="button" class="onb-close" id="closeAddClienteObra" aria-label="Fechar onboarding">&times;</button>
                </div>

                <div class="onb-stepper" id="onbStepper">
                    <button type="button" class="onb-step-chip is-active" data-step="1">
                        <span class="onb-step-index">1</span>
                        <span>Cliente e Projeto</span>
                    </button>
                    <button type="button" class="onb-step-chip" data-step="2">
                        <span class="onb-step-index">2</span>
                        <span>Pacotes e Prazos</span>
                    </button>
                    <button type="button" class="onb-step-chip" data-step="3">
                        <span class="onb-step-index">3</span>
                        <span>Lista de Imagens</span>
                    </button>
                    <button type="button" class="onb-step-chip" data-step="4">
                        <span class="onb-step-index">4</span>
                        <span>Contatos</span>
                    </button>
                </div>

                <div class="onb-layout">
                    <section class="onb-main">
                        <div class="onb-panel is-active" data-step-panel="1">
                            <div class="onb-card">
                                <div class="onb-card-header">
                                    <div>
                                        <h3>1. Cliente e Projeto</h3>
                                        <p>Defina a base comercial e operacional da nova obra.</p>
                                    </div>
                                </div>

                                <div class="onb-form-grid onb-form-grid-3">
                                    <div class="onb-field">
                                        <label for="onbClienteSelect">Cliente</label>
                                        <select id="onbClienteSelect">
                                            <option value="0">Novo cliente</option>
                                            <?php if (!empty($clientes) && is_array($clientes)): ?>
                                                <?php foreach ($clientes as $c): ?>
                                                    <?php
                                                    $cid = isset($c['idcliente']) ? $c['idcliente'] : (isset($c['id']) ? $c['id'] : '');
                                                    $cname = isset($c['nome_cliente']) ? $c['nome_cliente'] : (isset($c['nome']) ? $c['nome'] : '');
                                                    ?>
                                                    <?php if ($cid !== ''): ?>
                                                        <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($cname); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="onb-field">
                                        <label for="onbProjetoInterno">Projeto (nome interno)</label>
                                        <input id="onbProjetoInterno" type="text" autocomplete="off" placeholder="Ex.: CYR_OCE_VIEW">
                                    </div>

                                    <div class="onb-field">
                                        <label for="onbProjetoComercial">Projeto (nome comercial)</label>
                                        <input id="onbProjetoComercial" type="text" autocomplete="off" placeholder="Ex.: Ocean View">
                                    </div>

                                    <div class="onb-field">
                                        <label for="onbCodigoInterno">Sigla / Código interno</label>
                                        <input id="onbCodigoInterno" type="text" autocomplete="off" maxlength="10" placeholder="Ex.: OCV_2026">
                                    </div>

                                    <div class="onb-field onb-field-hidden" id="onbClienteNovoField">
                                        <label for="onbClienteNovo">Novo cliente</label>
                                        <input id="onbClienteNovo" type="text" autocomplete="off" placeholder="Nome do cliente">
                                    </div>

                                    <div class="onb-field onb-field-span-3">
                                        <label for="onbObservacoes">Observações operacionais</label>
                                        <textarea id="onbObservacoes" rows="3" placeholder="Anotações relevantes para o start, briefing ou condições combinadas."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="onb-panel" data-step-panel="2">
                            <div class="onb-card">
                                <div class="onb-card-header">
                                    <div>
                                        <h3>2. Pacotes Contratados e SLAs</h3>
                                        <p>Selecione os pacotes contratados e detalhe os prazos operacionais do onboarding.</p>
                                    </div>
                                </div>

                                <div class="onb-package-grid">
                                    <label class="onb-package-card" data-package-card="still">
                                        <div class="onb-package-top">
                                            <input id="onbPackageStill" type="checkbox">
                                            <div>
                                                <strong>Imagens Still</strong>
                                                <span>Renderizações estáticas</span>
                                            </div>
                                        </div>
                                        <div class="onb-package-fields" data-package-fields="still">
                                            <div class="onb-field">
                                                <label for="onbStillQtd">Quantidade de imagens</label>
                                                <input id="onbStillQtd" type="number" min="0" placeholder="12">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbStillPrazo">Prazo contratual (dias úteis)</label>
                                                <input id="onbStillPrazo" type="number" min="0" placeholder="15">
                                            </div>
                                        </div>
                                    </label>

                                    <label class="onb-package-card" data-package-card="animation">
                                        <div class="onb-package-top">
                                            <input id="onbPackageAnimation" type="checkbox">
                                            <div>
                                                <strong>Animação</strong>
                                                <span>Vídeos e animações 3D</span>
                                            </div>
                                        </div>
                                        <div class="onb-package-fields" data-package-fields="animation">
                                            <div class="onb-field">
                                                <label for="onbAnimationSeconds">Segundos contratados</label>
                                                <input id="onbAnimationSeconds" type="number" min="0" placeholder="40">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbAnimationPrazo">Prazo contratual (dias úteis)</label>
                                                <input id="onbAnimationPrazo" type="number" min="0" placeholder="25">
                                            </div>
                                        </div>
                                    </label>

                                    <label class="onb-package-card" data-package-card="film">
                                        <div class="onb-package-top">
                                            <input id="onbPackageFilm" type="checkbox">
                                            <div>
                                                <strong>Filme</strong>
                                                <span>Filme / vídeo final</span>
                                            </div>
                                        </div>
                                        <div class="onb-package-fields" data-package-fields="film">
                                            <div class="onb-field">
                                                <label for="onbFilmDuration">Duração contratada</label>
                                                <input id="onbFilmDuration" type="text" placeholder="Ex.: 60s / 1min30s">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbFilmPrazo">Prazo contratual (dias úteis)</label>
                                                <input id="onbFilmPrazo" type="number" min="0" placeholder="30">
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="onb-panel" data-step-panel="3">
                            <div class="onb-card">
                                <div class="onb-card-header">
                                    <div>
                                        <h3>3. Lista de Imagens</h3>
                                        <p>Importe TXT, CSV ou XLSX e complemente a lista manualmente quando necessário.</p>
                                    </div>
                                </div>

                                <div class="onb-import-grid">
                                    <label class="onb-upload-box" for="onbImageFile">
                                        <input id="onbImageFile" type="file" accept=".txt,.csv,.xlsx,.xls" hidden>
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <strong>Arraste o arquivo aqui</strong>
                                        <span>ou clique para selecionar TXT, CSV ou XLSX</span>
                                    </label>

                                    <div class="onb-import-meta">
                                        <div class="onb-import-file" id="onbImportedFileName">Nenhum arquivo importado</div>
                                        <div class="onb-import-stats">
                                            <div>
                                                <span>Total</span>
                                                <strong id="onbTotalImages">0</strong>
                                            </div>
                                            <div>
                                                <span>Com nome</span>
                                                <strong id="onbNamedImages">0</strong>
                                            </div>
                                            <div>
                                                <span>Duplicadas</span>
                                                <strong id="onbDuplicateImages">0</strong>
                                            </div>
                                            <div>
                                                <span>Erros</span>
                                                <strong id="onbErrorImages">0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="onb-manual-block">
                                    <div class="onb-field onb-field-span-3">
                                        <label for="onbManualImages">Adicionar imagens manualmente</label>
                                        <textarea id="onbManualImages" rows="4" placeholder="Uma imagem por linha"></textarea>
                                    </div>
                                    <div class="onb-manual-actions">
                                        <button type="button" class="onb-secondary-btn" id="onbAddManualImages">Adicionar lista manual</button>
                                        <button type="button" class="onb-ghost-btn" id="onbClearImages">Limpar lista</button>
                                    </div>
                                </div>

                                <div class="onb-preview-card">
                                    <div class="onb-preview-header">
                                        <strong>Prévia da lista consolidada</strong>
                                        <span id="onbPreviewCaption">0 itens prontos para criação</span>
                                    </div>
                                    <ul id="onbImagePreviewList" class="onb-preview-list"></ul>
                                </div>
                            </div>
                        </div>

                        <div class="onb-panel" data-step-panel="4">
                            <div class="onb-card">
                                <div class="onb-card-header">
                                    <div>
                                        <h3>4. Contatos do Cliente</h3>
                                        <p>Selecione os contatos permanentes do cliente para esta obra e cadastre novos contatos inline quando necessario.</p>
                                    </div>
                                </div>

                                <div class="onb-contact-stage">
                                    <section class="onb-contact-zone">
                                        <div class="onb-contact-zone-head">
                                            <div>
                                                <span class="onb-kicker onb-kicker-inline">Base do cliente</span>
                                                <h4>Contatos existentes</h4>
                                                <p>O sistema carrega os contatos permanentes do cliente selecionado para voce escolher quem participa desta obra.</p>
                                            </div>
                                            <span id="onbContactsCounter" class="onb-contact-counter">0 selecionado(s)</span>
                                        </div>

                                        <div id="onbContactsState" class="onb-contact-state">Selecione um cliente para carregar a base de contatos.</div>
                                        <div id="onbContactsList" class="onb-contacts-list"></div>
                                    </section>

                                    <section class="onb-contact-zone">
                                        <div class="onb-contact-zone-head">
                                            <div>
                                                <span class="onb-kicker onb-kicker-inline">Cadastro inline</span>
                                                <h4>Novo contato</h4>
                                                <p>Cadastre um novo contato operacional sem sair do onboarding e deixe-o pronto para selecao na obra.</p>
                                            </div>
                                        </div>

                                        <div class="onb-contact-form-grid">
                                            <div class="onb-field">
                                                <label for="onbContactName">Nome</label>
                                                <input type="text" id="onbContactName" placeholder="Nome completo do contato">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbContactRole">Cargo</label>
                                                <input type="text" id="onbContactRole" placeholder="Cargo ou funcao">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbContactType">Tipo</label>
                                                <select id="onbContactType">
                                                    <option value="OUTRO">Outro</option>
                                                    <option value="COMERCIAL">Comercial</option>
                                                    <option value="APROVACAO">Aprovacao</option>
                                                    <option value="FINANCEIRO">Financeiro</option>
                                                    <option value="MARKETING">Marketing</option>
                                                    <option value="ARQUITETO">Arquiteto</option>
                                                </select>
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbContactEmail">E-mail</label>
                                                <input type="email" id="onbContactEmail" placeholder="email@cliente.com.br">
                                            </div>
                                            <div class="onb-field">
                                                <label for="onbContactPhone">Telefone</label>
                                                <input type="text" id="onbContactPhone" placeholder="(11) 99999-9999">
                                            </div>
                                            <div class="onb-field onb-field-span-3">
                                                <label for="onbContactNotes">Observacoes</label>
                                                <textarea id="onbContactNotes" rows="3" placeholder="Observacoes operacionais do contato"></textarea>
                                            </div>
                                        </div>

                                        <p id="onbContactModeNote" class="onb-contact-state is-inline">Em cliente novo, os contatos ficam em rascunho e sao criados automaticamente ao concluir o onboarding.</p>
                                        <div id="onbDraftContactsList" class="onb-contact-drafts"></div>

                                        <div class="onb-manual-actions">
                                            <button type="button" class="onb-secondary-btn" id="onbAddContact">Salvar novo contato</button>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>

                        <div class="onb-footer">
                            <button type="button" class="onb-ghost-btn" id="onbPrevStep">Voltar</button>
                            <div class="onb-footer-actions">
                                <button type="button" class="onb-ghost-btn" id="onbCancelFlow">Cancelar</button>
                                <button type="button" class="onb-primary-btn" id="onbNextStep">Próximo</button>
                                <button type="button" class="onb-primary-btn onb-final-btn" id="onbSubmitFlow">Criar projeto</button>
                            </div>
                        </div>
                    </section>

                    <aside class="onb-sidebar">
                        <div class="onb-sidebar-card">
                            <h3>Resumo do Projeto</h3>
                            <div class="onb-summary-list" id="onbSummaryList"></div>
                        </div>

                        <div class="onb-sidebar-card">
                            <h3>Checklist Operacional</h3>
                            <div class="onb-checklist-list" id="onbChecklistList"></div>
                            <p class="onb-sidebar-note">Os grupos de cliente e interno serão concluídos depois, diretamente na tela da obra.</p>
                        </div>

                        <div class="onb-sidebar-card">
                            <h3>Timeline Automática</h3>
                            <p class="onb-sidebar-note">As ações do onboarding serão registradas em <strong>acompanhamento_email</strong> com tipos operacionais padronizados.</p>
                            <div class="onb-timeline-types">
                                <span>PROJECT_START</span>
                                <span>SLA_DEFINED</span>
                                <span>IMAGES_IMPORTED</span>
                                <span>GROUPS_CREATED</span>
                                <span>ONBOARDING_COMPLETED</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const idColaborador = <?php echo json_encode($idcolaborador); ?>;
        localStorage.setItem('idcolaborador', idColaborador);
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>" defer></script>
    <script src="<?php echo asset_url('script.js'); ?>" defer></script>
    <?php if (in_array((int) $nivel_acesso, [1, 5], true)): ?>
        <script src="<?php echo asset_url('scriptAddClienteObra.js'); ?>" defer></script>
    <?php endif; ?>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>