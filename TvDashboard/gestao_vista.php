<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão à Vista – Improov</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="gestao_vista.css">
</head>

<body>

  <header class="gv-header">
    <div class="gv-header-logo">
      <img src="../gif/logo_improov.gif" alt="Improov" class="gv-logo">
    </div>
    <div class="gv-header-center">
      <h1 class="gv-title">Gestão à Vista</h1>
      <span class="gv-period" id="gvPeriod"></span>
    </div>
    <div class="gv-header-right">
      <div class="gv-clock" id="gvClock"></div>
      <div class="gv-updated" id="gvUpdated">Carregando…</div>
    </div>
  </header>

  <main class="gv-main">

    <!-- Perspectivas (linha inteira) -->
    <section class="gv-card gv-card--full" id="secPerspectivas">
      <h2 class="gv-card-title">Gestão à vista Perspectivas</h2>
      <table class="gv-table" id="tblPerspectivas">
        <thead>
          <tr class="gv-thead-group">
            <th colspan="4" class="gv-th-group">Números atuais</th>
            <th colspan="2" class="gv-th-group gv-th-meta">Falta para a meta</th>
          </tr>
          <tr class="gv-thead-cols">
            <th>Funcionário</th>
            <th>Recorde / mês</th>
            <th>Qtnd parcial do mês</th>
            <th>Dia atual</th>
            <th class="gv-th-red">Qtde R00</th>
            <th class="gv-th-red">Dias</th>
          </tr>
        </thead>
        <tbody id="bodyPerspectivas"></tbody>
        <tfoot id="footPerspectivas"></tfoot>
      </table>
    </section>

    <!-- Linha inferior: Plantas Humanizadas + Alterações -->
    <div class="gv-row-bottom">

      <section class="gv-card" id="secPlantas">
        <h2 class="gv-card-title">Gestão à vista Plantas Humanizadas</h2>
        <table class="gv-table" id="tblPlantas">
          <thead>
            <tr class="gv-thead-group">
              <th colspan="4" class="gv-th-group">Números atuais</th>
              <th colspan="2" class="gv-th-group gv-th-meta">Falta para a meta</th>
            </tr>
            <tr class="gv-thead-cols">
              <th>Funcionário</th>
              <th>Recorde / mês</th>
              <th>Qtnd parcial do mês</th>
              <th>Dia atual</th>
              <th class="gv-th-red">Qtde R00</th>
              <th class="gv-th-red">Dias</th>
            </tr>
          </thead>
          <tbody id="bodyPlantas"></tbody>
          <tfoot id="footPlantas"></tfoot>
        </table>
      </section>

      <section class="gv-card" id="secAlteracoes">
        <h2 class="gv-card-title">Gestão à vista Alterações</h2>
        <table class="gv-table" id="tblAlteracoes">
          <thead>
            <tr class="gv-thead-group">
              <th colspan="4" class="gv-th-group">Números atuais</th>
              <th colspan="2" class="gv-th-group gv-th-meta">Falta para a meta</th>
            </tr>
            <tr class="gv-thead-cols">
              <th>Funcionário</th>
              <th>Recorde / mês</th>
              <th>Qtnd parcial do mês</th>
              <th>Dia atual</th>
              <th class="gv-th-red">Qtde R00</th>
              <th class="gv-th-red">Dias</th>
            </tr>
          </thead>
          <tbody id="bodyAlteracoes"></tbody>
          <tfoot id="footAlteracoes"></tfoot>
        </table>
      </section>

    </div>

  </main>

  <div id="gvOffline" class="gv-offline" hidden>
    <span>⚠ Sem conexão com o servidor</span>
  </div>

  <script src="gestao_vista.js"></script>
</body>

</html>