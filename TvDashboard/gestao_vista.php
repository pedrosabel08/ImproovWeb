<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão à Vista – Improov</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="gestao_vista.css">
</head>

<body>

  <!-- ── Header ─────────────────────────────────────────── -->
  <header class="gv-header">
    <div class="gv-header-logo">
      <img src="../gif/assinatura_branco.gif" alt="Improov" class="gv-logo">
    </div>
    <div class="gv-header-divider"></div>
    <span class="gv-header-label">Gestão à Vista</span>

    <div class="gv-header-center">
      <h1 class="gv-title">Gestão à Vista</h1>
      <span class="gv-period" id="gvPeriod"></span>
    </div>

    <div class="gv-header-right">
      <div class="gv-clock" id="gvClock"></div>
      <div class="gv-header-right-info">
        <span class="gv-dias-badge" id="gvDiasRestantes"></span>
        <span class="gv-updated" id="gvUpdated">Carregando…</span>
      </div>
    </div>
  </header>

  <!-- ── Summary Bar ────────────────────────────────────── -->
  <div class="gv-summary-bar" id="gvSummaryBar">
    <!-- preenchido pelo JS -->
  </div>

  <!-- ── Body ───────────────────────────────────────────── -->
  <div class="gv-body">
    <div class="gv-main">

      <!-- Perspectivas (full-width) -->
      <section class="gv-section gv-section--persp" id="secPerspectivas">
        <div class="gv-section-header">
          <div class="gv-section-title-wrap">
            <div class="gv-section-icon"><i class="fa-solid fa-bullseye"></i></div>
            <span class="gv-section-title">Perspectivas</span>
          </div>
        </div>
        <div class="gv-col-headers">
          <div class="gv-col-h">Funcionário</div>
          <div class="gv-col-h">Progresso</div>
          <div class="gv-col-h c">Qtd Parcial</div>
          <div class="gv-col-h c">Falta para Meta</div>
          <div class="gv-col-h c">Recorde</div>
          <div class="gv-col-h r">Ritmo</div>
        </div>
        <div class="gv-rows" id="bodyPerspectivas"></div>
        <div class="gv-section-footer" id="footPerspectivas">
          <span class="gv-section-meta-label" id="metaPerspectivas">Meta mensal: <strong>–</strong></span>
          <div class="gv-foot-left">
            <span class="gv-foot-label">Total atual</span>
            <span class="gv-foot-val">0</span>
          </div>
          <div class="gv-foot-right">
            <span class="gv-foot-label">Falta para meta</span>
            <span class="gv-foot-val">–</span>
          </div>
        </div>
      </section>

      <!-- Linha inferior: Plantas + Alterações -->
      <div class="gv-row-bottom">

        <section class="gv-section gv-section--plants" id="secPlantas">
          <div class="gv-section-header">
            <div class="gv-section-title-wrap">
              <div class="gv-section-icon green"><i class="fa-solid fa-house-chimney"></i></div>
              <span class="gv-section-title">Plantas Humanizadas</span>
            </div>
          </div>
          <div class="gv-col-headers">
            <div class="gv-col-h">Funcionário</div>
            <div class="gv-col-h">Progresso</div>
            <div class="gv-col-h c">Recorde</div>
            <div class="gv-col-h c">Qtd Parcial</div>
            <div class="gv-col-h c">Falta para Meta</div>
            <div class="gv-col-h r">Ritmo</div>
          </div>
          <div class="gv-rows" id="bodyPlantas"></div>
          <div class="gv-section-footer" id="footPlantas">
            <span class="gv-section-meta-label" id="metaPlantas">Meta mensal: <strong>–</strong></span>
            <div class="gv-foot-left">
              <span class="gv-foot-label">Total atual</span>
              <span class="gv-foot-val">0</span>
            </div>
            <div class="gv-foot-right">
              <span class="gv-foot-label">Falta para meta</span>
              <span class="gv-foot-val">–</span>
            </div>
          </div>
        </section>

        <section class="gv-section gv-section--alter" id="secAlteracoes">
          <div class="gv-section-header">
            <div class="gv-section-title-wrap">
              <div class="gv-section-icon orange"><i class="fa-solid fa-arrows-rotate"></i></div>
              <span class="gv-section-title">Alterações</span>
            </div>
          </div>
          <div class="gv-col-headers">
            <div class="gv-col-h">Funcionário</div>
            <div class="gv-col-h">Progresso</div>
            <div class="gv-col-h c">Recorde</div>
            <div class="gv-col-h c">Qtd Parcial</div>
            <div class="gv-col-h c">Falta para Meta</div>
            <div class="gv-col-h r">Ritmo</div>
          </div>
          <div class="gv-rows" id="bodyAlteracoes"></div>
          <div class="gv-section-footer" id="footAlteracoes">
            <span class="gv-section-meta-label" id="metaAlteracoes">Meta mensal: <strong>–</strong></span>
            <div class="gv-foot-left">
              <span class="gv-foot-label">Total atual</span>
              <span class="gv-foot-val">0</span>
            </div>
            <div class="gv-foot-right">
              <span class="gv-foot-label">Falta para meta</span>
              <span class="gv-foot-val">–</span>
            </div>
          </div>
        </section>

      </div><!-- .gv-row-bottom -->
    </div><!-- .gv-main -->
  </div><!-- .gv-body -->

  <!-- ── Footer tagline ─────────────────────────────────── -->
  <footer class="gv-footer-tagline">
    HEARTMADE &bull; ARTE &bull; FOCO &bull; FLUXO &bull; RESULTADO
  </footer>

  <div id="gvOffline" class="gv-offline" hidden>
    <i class="fa-solid fa-triangle-exclamation"></i> Sem conexão com o servidor
  </div>

  <script src="gestao_vista.js"></script>
</body>

</html>