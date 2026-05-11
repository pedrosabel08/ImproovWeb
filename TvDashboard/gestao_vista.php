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
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include_once __DIR__ . '/../conexao.php';

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
  die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
  die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
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
  <title>Gestão à Vista – Improov</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
  <link rel="stylesheet" href="gestao_vista.css">
  <script>
    /* TV mode: ?tv=1 ativa, ?tv=0 desativa, persiste via localStorage */
    (function() {
      var p = new URLSearchParams(location.search);
      if (p.get('tv') === '1') localStorage.setItem('gvTvMode', '1');
      else if (p.get('tv') === '0') localStorage.removeItem('gvTvMode');
      if (localStorage.getItem('gvTvMode') === '1')
        document.documentElement.classList.add('gv-tv-mode');
    })();
  </script>
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
            <div class="gv-col-h c">Qtd Parcial</div>
            <div class="gv-col-h c">Falta para Meta</div>
            <div class="gv-col-h c">Recorde</div>
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
            <div class="gv-col-h c">Qtd Parcial</div>
            <div class="gv-col-h c">Falta para Meta</div>
            <div class="gv-col-h c">Recorde</div>
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

  <script src="<?php echo asset_url('gestao_vista.js'); ?>"></script>
</body>

</html>