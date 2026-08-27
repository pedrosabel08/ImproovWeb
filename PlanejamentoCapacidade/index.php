<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
include_once __DIR__ . '/../conexao.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit();
}

$podeAcessar = improov_usuario_eh_gestor_sidebar($conn);
if (!$podeAcessar) {
    header('Location: ../acesso_negado.php');
    exit();
}

$tema = ($_GET['tema'] ?? '') === 'light' ? 'light' : '';


$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();

?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark light">
  <title>Planejamento Global de Capacidade · Flow</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=5">
  <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
    integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
    type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
  <link href="https://unpkg.com/tabulator-tables@6.2.0/dist/css/tabulator.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
</head>

<body class="capacity-page <?= $tema ?>" data-api-url="consultar.php" data-allocation-api-url="alocacao_consultar.php" data-allocation-simulate-url="alocacao_simular.php" data-allocation-apply-url="alocacao_aplicar.php" data-allocation-validation-url="alocacao_validar_capacidade.php" data-allocation-suggest-url="alocacao_sugerir.php" data-operational-projection-api-url="projecao_operacional.php" data-queue-simulate-url="fila_operacional_simular.php" data-queue-confirm-url="fila_operacional_confirmar.php" data-queue-suggest-url="fila_operacional_sugerir.php" data-simulation-url="simular.php" data-apply-simulation-url="aplicar_cenario.php">

  <?php include '../sidebar.php'; ?>

  <main class="capacity-shell" aria-live="polite">
    <header class="capacity-header">
      <section class="capacity-title-block">
        <span class="capacity-icon"><i class="fa-solid fa-chart-column"></i></span>
        <div>
          <p>Planejamento global</p>
          <h1>Capacidade da Produção</h1>
          <span>Visão consolidada da demanda planejada e da capacidade operacional.</span>
        </div>
      </section>
      <section class="capacity-controls" aria-label="Controles do período">
        <div class="capacity-horizons" role="group" aria-label="Horizonte de planejamento">
          <button type="button" data-weeks="4">4 semanas</button>
          <button type="button" data-weeks="8" class="is-active">8 semanas</button>
          <button type="button" data-weeks="12">12 semanas</button>
        </div>
        <div class="capacity-period-controls">
          <button type="button" id="period-prev" aria-label="Período anterior"><i class="fa-solid fa-chevron-left"></i></button>
          <label><span>Período</span><input id="period-start" type="date" aria-label="Início do período"></label>
          <label><span>até</span><input id="period-end" type="date" aria-label="Fim do período"></label>
          <button type="button" id="period-next" aria-label="Próximo período"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </section>
    </header>

    <nav class="capacity-product-tabs" aria-label="Área do planejamento global">
      <button type="button" class="is-active" data-capacity-workspace="capacity"><i class="fa-solid fa-chart-column"></i> Capacidade</button>
      <button type="button" data-capacity-workspace="allocation"><i class="fa-solid fa-people-group"></i> Alocação <span>V1.4</span></button>
    </nav>

    <div id="capacity-workspace">
    <section class="capacity-overview" aria-label="Resumo executivo">
      <div class="capacity-kpis">
        <article><strong id="kpi-plans">—</strong><span>Projetos planejados</span></article>
        <article class="is-healthy"><strong id="kpi-healthy">—</strong><span>Funções saudáveis</span></article>
        <article class="is-support"><strong id="kpi-support">—</strong><span>Necessitam apoio</span></article>
        <article class="is-conflict"><strong id="kpi-conflict">—</strong><span>Conflitos críticos</span></article>
      </div>
      <aside class="capacity-priority" id="priority-card" hidden>
        <span><i class="fa-solid fa-triangle-exclamation"></i> Atenção prioritária</span>
        <strong id="priority-title">—</strong>
        <p id="priority-detail">—</p>
        <button type="button" id="priority-open">Ver detalhe <i class="fa-solid fa-arrow-right"></i></button>
      </aside>
    </section>

    <section class="capacity-toolbar" aria-label="Filtros de capacidade">
      <div class="capacity-view-switch" role="group" aria-label="Visualização">
        <button type="button" class="is-active" data-view="heatmap"><i class="fa-solid fa-table-cells-large"></i> Heatmap</button>
        <button type="button" data-view="timeline"><i class="fa-solid fa-bars-staggered"></i> Timeline</button>
      </div>
      <div class="capacity-filters">
        <label class="capacity-select"><span>Função</span><select id="filter-stage">
            <option value="">Todas as funções</option>
          </select></label>
        <label class="capacity-select"><span>Status</span><select id="filter-status">
            <option value="">Todos os status</option>
            <option value="PROBLEMAS">Somente problemas</option>
            <option value="SAUDAVEL">Saudáveis</option>
            <option value="NECESSITA_APOIO">Necessitam apoio</option>
            <option value="CONFLITO">Conflitos</option>
            <option value="SEM_PRINCIPAIS_CONFIGURADOS">Sem principais</option>
          </select></label>
      </div>
    </section>

    <section class="capacity-panel" id="capacity-panel" aria-label="Mapa semanal de capacidade">
      <div class="capacity-panel-heading">
        <div>
          <p>Diagnóstico semanal</p>
          <h2>Demanda × capacidade principal</h2>
        </div>
        <span class="capacity-key"><i class="key-healthy"></i> Carga principal <b class="key-limit"></b> No limite <em class="key-support">+ Apoio</em><em class="key-conflict">! Conflito</em></span>
      </div>
      <div class="capacity-loading" id="capacity-loading" aria-label="Carregando capacidade">
        <span></span><span></span><span></span><span></span><span></span><span></span>
      </div>
      <div class="capacity-heatmap-wrap" id="heatmap-view" hidden>
        <div class="capacity-heatmap" id="capacity-heatmap"></div>
      </div>
      <div class="capacity-timeline" id="timeline-view" hidden></div>
      <div class="capacity-empty" id="capacity-empty" hidden></div>
    </section>

    <p class="capacity-footnote"><i class="fa-solid fa-circle-info"></i> Secundários representam apoio potencial e exigem alocação gerencial; não são disponibilidade garantida.</p>
    </div>

    <section class="allocation-workspace" id="allocation-workspace" aria-label="Central de Alocação" hidden>
      <header class="allocation-header">
        <div>
          <p><i class="fa-solid fa-shuffle"></i> Alocação operacional · simulação antes da confirmação</p>
          <h2>Central de Alocação</h2>
          <span>Planejado → materializado → alocado, usando as tarefas reais do Flow.</span>
        </div>
        <aside><i class="fa-solid fa-circle-info"></i> A seleção e a simulação não alteram tarefas. A responsabilidade só muda após confirmação explícita.</aside>
      </header>

      <section class="allocation-kpis" aria-label="Resumo da alocação">
        <article><span>Planejado</span><strong id="allocation-planned">—</strong><small>unidades previstas</small></article>
        <article><span>Materializado</span><strong id="allocation-materialized">—</strong><small>tarefas reais disponíveis</small></article>
        <article><span>Sem responsável</span><strong id="allocation-unassigned">—</strong><small>tarefas reais sem pessoa</small></article>
        <article class="is-warning"><span>Pendente de materialização</span><strong id="allocation-pending">—</strong><small>necessidades ainda sem tarefa real</small></article>
        <article class="is-danger"><span>Sobrecargas</span><strong id="allocation-overloads">—</strong><small>cargas acima de 100%</small></article>
        <article class="is-warning"><span>Aguardando validação</span><strong id="allocation-awaiting">—</strong><small>exceções ainda não confirmadas</small></article>
      </section>

      <section class="allocation-panel" aria-live="polite">
        <div class="allocation-panel-heading">
          <div><p>Visão por função</p><h3>Demanda, operação e alocação nominal</h3></div>
          <span id="allocation-period-label">—</span>
        </div>
        <div class="allocation-loading" id="allocation-loading" hidden><span></span><span></span><span></span></div>
        <div class="allocation-empty" id="allocation-empty" hidden></div>
        <div class="allocation-stage-list" id="allocation-stage-list"></div>
        <div class="allocation-action-status" id="allocation-action-status" aria-live="polite" hidden></div>
        <section class="allocation-simulation-panel" id="allocation-simulation-panel" hidden aria-live="polite">
          <header><div><p>Simulação de redistribuição</p><h3>Atual → simulado</h3></div><button type="button" id="allocation-simulation-close" aria-label="Fechar simulação"><i class="fa-solid fa-xmark"></i></button></header>
          <div id="allocation-simulation-content"></div>
          <footer><button type="button" class="capacity-button-secondary" id="allocation-simulation-cancel">Cancelar</button><button type="button" class="capacity-button-primary" id="allocation-simulation-apply">Aplicar redistribuição</button></footer>
        </section>
      </section>

      <section class="allocation-notes" id="allocation-notes" hidden></section>
    </section>
  </main>

  <aside class="capacity-drawer" id="capacity-drawer" aria-label="Detalhes da capacidade" aria-hidden="true">
    <button type="button" class="capacity-drawer-close" id="drawer-close" aria-label="Fechar detalhes"><i class="fa-solid fa-xmark"></i></button>
    <div id="drawer-content"></div>
  </aside>
  <div class="capacity-scrim" id="capacity-scrim" hidden></div>
  <dialog class="allocation-validation-dialog" id="allocation-validation-dialog">
    <form method="dialog" id="allocation-validation-form">
      <p class="allocation-dialog-eyebrow">Validação contextual · nada permanente</p>
      <h2>Validar capacidade excepcional</h2>
      <p id="allocation-validation-summary"></p>
      <label>Motivo / observação<textarea id="allocation-validation-observation" minlength="5" maxlength="500" required placeholder="Ex.: Confirmado com o colaborador para esta janela."></textarea></label>
      <label class="allocation-confirm-check"><input id="allocation-validation-confirm" type="checkbox" required> Confirmo que esta capacidade foi validada com o colaborador.</label>
      <div class="allocation-dialog-actions"><button type="button" class="capacity-button-secondary" id="allocation-validation-cancel">Cancelar</button><button type="submit" class="capacity-button-primary">Confirmar capacidade</button></div>
    </form>
  </dialog>
  <dialog class="queue-dialog" id="queue-dialog" aria-labelledby="queue-dialog-title">
    <section>
      <header class="queue-dialog-header"><div><p>Fila operacional · simulação antes da confirmação</p><h2 id="queue-dialog-title">Organizar fila</h2></div><button type="button" id="queue-dialog-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button></header>
      <div id="queue-dialog-content"></div>
      <footer class="queue-dialog-actions"><button type="button" class="capacity-button-secondary" id="queue-dialog-suggest"><i class="fa-solid fa-wand-magic-sparkles"></i> Encontrar melhor ordem</button><span></span><button type="button" class="capacity-button-secondary" id="queue-dialog-cancel">Cancelar</button><button type="button" class="capacity-button-primary" id="queue-dialog-confirm">Confirmar nova fila</button></footer>
    </section>
  </dialog>

  <script src="script.js?v=6" defer></script>
  <script src="alocacao.js?v=6" defer></script>
  <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.0/dist/js/tabulator.min.js"></script>

</body>

</html>
