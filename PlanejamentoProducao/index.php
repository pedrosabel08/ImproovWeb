<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit();
}

$obraId = (int) ($_GET['obra_id'] ?? 116);
$conn = conectarBanco();
if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
    $conn->close();
    header('Location: ../acesso_negado.php');
    exit();
}
$conn->close();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Planejamento de Produção · Flow</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=9">
  <link rel="stylesheet" href="redesign.css?v=3">
</head>
<body class="planning-page" data-obra-id="<?= $obraId ?>">
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
          <span class="planning-prototype-label"><i class="fa-solid fa-flask"></i> Simulação</span>
          <button class="planning-icon-button" id="theme-toggle" type="button" aria-label="Alternar tema" title="Alternar tema"><i class="fa-solid fa-gear"></i></button>
        </div>
      </section>

      <section class="planning-summary" aria-label="Resumo do planejamento">
        <article class="planning-work-name"><span>Obra</span><strong data-plan-title>Carregando…</strong></article>
        <article><span>Início da produção</span><strong id="summary-start">—</strong></article>
        <article><span>Hoje</span><strong id="summary-today">—</strong></article>
        <article><span>Entrega prevista (R00)</span><strong id="summary-due">—</strong></article>
        <article class="planning-result-card"><span>Fim previsto</span><strong id="summary-finish">—</strong></article>
        <article class="planning-margin-card planning-result-card" id="summary-margin"><span>Margem</span><strong>—</strong></article>
      </section>
      <div class="planning-hero-status" id="plan-status-card" aria-live="polite"><span>Status do plano</span><strong>Calculando…</strong><small id="plan-exception-count" hidden></small></div>
    </header>

    <section class="planning-diagnosis" id="planning-diagnosis" aria-label="Diagnóstico do planejamento">
      <div class="planning-diagnosis-main"><i class="fa-solid fa-tower-broadcast"></i><div><strong id="diagnosis-summary">Calculando diagnóstico…</strong><span id="diagnosis-bottleneck"></span></div></div>
      <div class="planning-diagnosis-goal" id="diagnosis-goal">Para cumprir a entrega</div>
      <div class="planning-scenario-list" id="scenario-list" aria-live="polite"></div>
      <button type="button" class="planning-scenarios-button" id="show-scenarios"><i class="fa-solid fa-chart-column"></i> Ver mais cenários</button>
    </section>

    <section class="planning-workspace" aria-label="Cronograma de produção">
      <div class="planning-toolbar">
        <div><strong>Resumo por função</strong><span>Use +/− para simular capacidade; não há persistência.</span></div>
        <button type="button" class="planning-text-button" id="reset-simulation"><i class="fa-solid fa-rotate-left"></i> Restaurar cenário</button>
      </div>
      <div class="planning-board" id="planning-board">
        <div class="planning-stage-head"><span>#</span><span>Etapa</span><span>Volume</span><span>Duração</span><span>Início</span><span>Limite</span><span>Pessoas</span><span>Dependências</span></div>
        <div class="planning-timeline-head" aria-label="Escala de datas"><div class="planning-timeline-controls"><button type="button" aria-label="Visualização mensal" data-scale="month">Mês</button><button type="button" aria-label="Visualização semanal" data-scale="week" class="is-active">Semana</button><button type="button" aria-label="Visualização diária" data-scale="day">Dia</button><span class="planning-legend"><b class="legend-today"></b>Hoje <b class="legend-due"></b>Entrega <b class="legend-finish"></b>Fim previsto <i></i>Caminho crítico</span></div><div id="timeline-head"></div></div>
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

  <script src="script.js?v=9" defer></script>
</body>
</html>
