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

$obraId = (int) ($_GET['obra_id'] ?? 0);
$entregaId = (int) ($_GET['entrega_id'] ?? 0);
$tema = ($_GET['tema'] ?? '') === 'light' ? 'light' : '';
$conn = conectarBanco();
if ($obraId <= 0 && $entregaId > 0) {
    $stmtEntrega = $conn->prepare('SELECT obra_id FROM entregas WHERE id = ? LIMIT 1');
    if ($stmtEntrega) {
        $stmtEntrega->bind_param('i', $entregaId);
        $stmtEntrega->execute();
        $obraId = (int) (($stmtEntrega->get_result()->fetch_assoc()['obra_id'] ?? 0));
        $stmtEntrega->close();
    }
}
if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
    $conn->close();
    header('Location: ../acesso_negado.php');
    exit();
}

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
  <title>Planejamento de Produção · Flow</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=13">
  <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
    type="image/x-icon">
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

<body class="planning-page <?= $tema ?>" data-obra-id="<?= $obraId ?>" data-entrega-id="<?= $entregaId ?>">

  <?php include '../sidebar.php'; ?>

  <main class="planning-shell" aria-live="polite">
    <header class="planning-header" aria-labelledby="planning-title">
      <section class="planning-title-block">
        <i class="fa-regular fa-calendar-check planning-title-icon" aria-hidden="true"></i>
        <div>
          <h1 id="planning-title">Plano de Produção por Função</h1>
          <p>R00 · Planejamento automático</p>
        </div>
        <div class="planning-topbar-actions">
          <a class="planning-back" href="../Dashboard/obra.php?obra_id=<?= $obraId ?>" aria-label="Voltar para a obra"><i class="fa-solid fa-arrow-left"></i><span>Obra</span></a>
          <span class="planning-prototype-label" id="planning-mode-label"><i class="fa-solid fa-flask"></i> Simulação</span>
          <!-- <button class="planning-icon-button" id="theme-toggle" type="button" aria-label="Alternar tema" title="Alternar tema"><i class="fa-solid fa-gear"></i></button> -->
        </div>
      </section>

      <section class="planning-summary" aria-label="Resumo do planejamento">
        <article class="planning-work-name"><span>Obra</span><strong data-plan-title>Carregando…</strong></article>
        <article><span>Início da produção</span><strong id="summary-start">—</strong></article>
        <article class="planning-result-card"><span>Fim planejado</span><strong id="summary-finish">—</strong><small id="summary-projection" hidden></small></article>
        <article><span>Entrega R00</span><strong id="summary-due">—</strong></article>
        <article class="planning-margin-card planning-result-card" id="summary-margin"><span>Margem planejada</span><strong>—</strong><small id="summary-projected-margin" hidden></small></article>
        <article class="planning-summary-today"><span>Hoje</span><strong id="summary-today">—</strong></article>
      </section>
    </header>

    <section class="planning-diagnosis" id="planning-diagnosis" aria-label="Diagnóstico do planejamento">
      <div class="planning-hero-status" id="plan-status-card" aria-live="polite"><span>Status do plano</span><strong>Calculando…</strong><small id="plan-exception-count" hidden></small></div>
      <div class="planning-diagnosis-main"><i class="fa-solid fa-tower-broadcast"></i>
        <div><strong id="diagnosis-summary">Gargalo atual: calculando…</strong><span id="diagnosis-bottleneck"></span></div>
      </div>
      <div class="planning-scenario-list" id="scenario-list" aria-live="polite"></div>
      <!-- <button type="button" class="planning-scenarios-button" id="show-scenarios"><i class="fa-solid fa-chart-column"></i> Ver mais cenários</button> -->
      <section class="planning-lifecycle" id="planning-lifecycle" aria-live="polite">
        <div><span class="planning-eyebrow" id="planning-lifecycle-label">Estado do plano</span><strong id="planning-lifecycle-title">Calculando plano para revisão…</strong><small id="planning-lifecycle-detail"></small></div>
        <div class="planning-lifecycle-actions">
          <select id="planning-replan-reason" hidden aria-label="Motivo do replanejamento">
            <option value="">Motivo</option>
            <option value="AUMENTO_ESCOPO">Aumento de escopo</option>
            <option value="ATRASO_OPERACIONAL">Atraso operacional</option>
            <option value="REDISTRIBUICAO_EQUIPE">Redistribuição de equipe</option>
            <option value="ANTECIPACAO">Antecipação</option>
            <option value="ALTERACAO_PRAZO">Alteração de prazo</option>
            <option value="MUDANCA_PRIORIDADE">Mudança de prioridade</option>
            <option value="OUTRO">Outro</option>
          </select>
          <input id="planning-replan-note" hidden maxlength="500" placeholder="Descreva brevemente o motivo">
          <button type="button" class="planning-primary-button" id="confirm-plan"><i class="fa-solid fa-check"></i> Confirmar plano</button>
          <button type="button" class="planning-text-button" id="show-plan-history" hidden><i class="fa-solid fa-clock-rotate-left"></i> Histórico</button>
        </div>
      </section>
    </section>

    <section class="planning-workspace" aria-label="Cronograma de produção">
      <div class="planning-toolbar">
        <div><strong>Resumo por função</strong><span id="planning-toolbar-hint">Use +/− para ajustar a capacidade da proposta.</span></div>
        <button type="button" class="planning-text-button" id="reset-simulation"><i class="fa-solid fa-rotate-left"></i> Restaurar cenário</button>
      </div>
      <div class="planning-board" id="planning-board">
        <div class="planning-stage-head"><span>#</span><span>Etapa</span><span>Volume</span><span>Duração</span><span>Início</span><span>Limite</span><span>Pessoas</span><span>Dependências</span></div>
        <div class="planning-timeline-head" aria-label="Escala de datas">
          <div class="planning-timeline-controls"><button type="button" aria-label="Visualização mensal" data-scale="month">Mês</button><button type="button" aria-label="Visualização semanal" data-scale="week" class="is-active">Semana</button><button type="button" aria-label="Visualização diária" data-scale="day">Dia</button><span class="planning-legend"><b class="legend-today"></b>Hoje <b class="legend-due"></b>Entrega R00 <b class="legend-finish"></b>Fim planejado <i class="legend-progress"></i>Realizado</span></div>
          <div id="timeline-head"></div>
        </div>
        <div class="planning-stage-list" id="stage-list"></div>
        <div class="planning-timeline" id="timeline" tabindex="0" aria-label="Timeline do planejamento"></div>
      </div>
    </section>

  </main>

  <aside class="planning-detail" id="planning-detail" aria-hidden="true" aria-labelledby="detail-title">
    <button type="button" class="planning-detail-close" id="detail-close" aria-label="Fechar detalhes"><i class="fa-solid fa-xmark"></i></button>
    <div id="detail-content"></div>
  </aside>
  <div class="planning-scrim" id="planning-scrim" hidden></div>

  <script src="script.js?v=13" defer></script>
  <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.0/dist/js/tabulator.min.js"></script>

</body>

</html>
