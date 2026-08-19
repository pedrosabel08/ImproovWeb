<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/kpi_access.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
  if ($__p && is_file($__p)) {
    require_once $__p;
    break;
  }
}
unset($__root, $__p);

// session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  // Se não estiver logado, redirecionar para a página de login
  header("Location: ../index.html");
  exit();
}

$idusuario = $_SESSION['idusuario'];
$frKpiPermissions = improov_kpi_permissions_for_user((int) $idusuario);
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

// Carrega conexão com o banco antes de executar atualizações de logs
include '../conexaoMain.php';
$conn = conectarBanco();

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

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
    integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
    type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

  <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link rel="stylesheet" href="https://unpkg.com/tributejs@5.1.3/dist/tribute.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">


  <title>Briefings - Flow</title>
  <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>


<body>
  <?php include __DIR__ . '/../sidebar.php'; ?>
  <main class="briefing-main">
    <header class="briefing-header">
      <div>
        <h1>Briefings</h1>
        <p class="muted">Gerencie e acompanhe os briefings dos projetos.</p>
      </div>
      <div class="header-actions">
        <button class="button secondary" id="open-templates">Templates</button>
        <button class="button" id="new-briefing">+ Novo briefing</button>
      </div>
    </header>

    <div class="notice" id="notice" role="status" aria-live="polite"></div>
    <nav class="module-tabs" aria-label="Área do Briefing">
      <button type="button" class="module-tab is-active" id="view-briefings" aria-current="page">Briefings</button>
      <button type="button" class="module-tab" id="view-templates">Templates</button>
    </nav>

    <section class="workspace-view" id="briefings-view">
      <div class="quick-filters" id="quick-filters" aria-label="Filtros rápidos por status"></div>
      <div class="filter-toolbar">
        <label class="search-control"><i class="fa-solid fa-magnifying-glass"></i><input id="briefing-search" type="search" placeholder="Buscar por projeto, título ou responsável..."></label>
        <select id="filter-status" aria-label="Filtrar por status">
          <option value="">Status</option>
        </select>
        <select id="filter-reviewer" aria-label="Filtrar por responsável">
          <option value="">Responsável</option>
        </select>
        <select id="filter-due" aria-label="Filtrar por prazo">
          <option value="">Prazo</option>
          <option value="upcoming">Próximos 7 dias</option>
          <option value="late">Atrasados</option>
          <option value="none">Sem prazo</option>
        </select>
        <select id="filter-sort" aria-label="Ordenar briefings">
          <option value="activity">Atividade recente</option>
          <option value="due">Prazo</option>
          <option value="progress">Progresso</option>
          <option value="title">Projeto</option>
          <option value="status">Status</option>
        </select>
        <button class="icon-button" type="button" id="clear-filters" aria-label="Limpar filtros" title="Limpar filtros"><i class="fa-solid fa-arrow-rotate-left"></i></button>
      </div>

      <section class="workspace-grid">
        <div class="list-surface">
          <div class="list-heading"><span>Projeto</span><span>Progresso</span><span>Status</span><span>Prazo</span><span>Última atividade</span><span class="visually-hidden">Abrir</span></div>
          <div id="briefing-list" class="briefing-list" aria-live="polite"></div>
          <footer class="pagination" id="pagination"></footer>
        </div>
        <aside class="detail-panel" id="detail" aria-live="polite">
          <div class="detail-empty"><strong>Selecione um briefing</strong>
            <p>Consulte o progresso, as respostas, participantes e histórico sem sair da listagem.</p>
          </div>
        </aside>
      </section>
    </section>

    <section class="workspace-view" id="templates-view" hidden>
      <div class="templates-header"><label class="search-control"><span aria-hidden="true">⌕</span><input id="template-search" type="search" placeholder="Buscar template..."></label><button class="button" type="button" id="new-template">+ Novo template</button></div>
      <section class="workspace-grid templates-grid">
        <div class="list-surface">
          <div class="list-heading template-heading"><span>Nome</span><span>Perguntas</span><span>Última alteração</span><span class="visually-hidden">Abrir</span></div>
          <div id="template-list" class="briefing-list"></div>
        </div>
        <aside class="detail-panel" id="template-detail">
          <div class="detail-empty"><strong>Selecione um template</strong>
            <p>Visualize sua estrutura, edite-o ou crie um briefing a partir dele.</p>
          </div>
        </aside>
      </section>
    </section>
  </main>

  <dialog id="template-dialog" class="dialog wide template-dialog" aria-labelledby="template-dialog-title">
    <form method="dialog" id="template-form" class="template-form">
      <header class="template-dialog-header">
        <div>
          <p class="eyebrow">CONSTRUTOR DE BRIEFING</p>
          <h2 id="template-dialog-title">Novo template</h2>
          <p class="template-dialog-subtitle">Monte as perguntas exatamente como o cliente irá respondê-las.</p>
        </div>
        <button type="button" class="icon" id="close-template" aria-label="Fechar editor">×</button>
      </header>

      <div class="template-dialog-body">
        <section class="template-settings" aria-labelledby="template-settings-title">
          <div class="template-section-heading">
            <div>
              <p class="eyebrow">CONFIGURAÇÕES DO TEMPLATE</p>
              <h3 id="template-settings-title">Dados gerais</h3>
            </div>
            <span class="template-settings-hint">Essas informações orientam o fluxo interno.</span>
          </div>
          <div class="template-settings-grid">
            <label>Nome do template<input id="template-name" required maxlength="180" placeholder="Ex.: Briefing de arquitetura"></label>
            <label>Revisor padrão<select id="template-reviewer">
                <option value="">Definir por briefing</option>
              </select></label>
          </div>
          <label class="check template-review-check"><input type="checkbox" id="template-review" checked> Exige conferência interna</label>
        </section>

        <section class="template-content" aria-labelledby="template-content-title">
          <div class="template-content-heading">
            <div>
              <p class="eyebrow">CONTEÚDO DO BRIEFING</p>
              <h3 id="template-content-title">Seções e perguntas</h3>
            </div>
            <div class="template-mode-switch" role="tablist" aria-label="Modo do editor">
              <button type="button" class="template-mode is-active" id="template-mode-edit" role="tab" aria-selected="true">Editar</button>
              <button type="button" class="template-mode" id="template-mode-preview" role="tab" aria-selected="false">Visualizar</button>
            </div>
          </div>
          <div id="template-sections" class="template-builder" role="tabpanel" aria-live="polite"></div>
          <div id="template-preview" class="template-preview-mode" role="tabpanel" hidden></div>
        </section>
      </div>

      <footer class="template-dialog-footer">
        <button type="button" class="button secondary" id="cancel-template">Cancelar</button>
        <button type="submit" class="button" id="save-template">Salvar template</button>
      </footer>
    </form>
  </dialog>
  <dialog id="briefing-dialog" class="dialog briefing-dialog" aria-labelledby="briefing-dialog-title">
    <form method="dialog" id="briefing-form" class="briefing-form">
      <header class="template-dialog-header">
        <div>
          <p class="eyebrow">NOVO BRIEFING</p>
          <h2 id="briefing-dialog-title">Criar briefing</h2>
          <p class="template-dialog-subtitle">Defina o formulário, o projeto e o responsável pela conferência.</p>
        </div>
        <button type="button" class="icon" id="close-briefing" aria-label="Fechar criação de briefing">×</button>
      </header>

      <div class="template-dialog-body briefing-dialog-body">
        <section class="template-settings" aria-labelledby="briefing-settings-title">
          <div class="template-section-heading">
            <div>
              <p class="eyebrow">CONFIGURAÇÕES DO BRIEFING</p>
              <h3 id="briefing-settings-title">Dados gerais</h3>
            </div>
            <span class="template-settings-hint">Os campos com * são obrigatórios.</span>
          </div>
          <div class="briefing-settings-grid">
            <label>Template <span class="required-mark">*</span><select id="briefing-template" required></select></label>
            <label>Obra <span class="required-mark">*</span><select id="briefing-obra" required></select></label>
            <label class="briefing-title-field">Título <span class="required-mark">*</span><input id="briefing-title" required maxlength="180" placeholder="Ex.: Briefing do projeto residencial"></label>
            <label>Prazo<input id="briefing-due" type="datetime-local"></label>
            <label>Responsável pela conferência<select id="briefing-reviewer">
                <option value="">Qualquer pessoa interna</option>
              </select></label>
          </div>
          <label class="check template-review-check"><input type="checkbox" id="briefing-requires-review" checked> Exige conferência interna</label>
        </section>
      </div>

      <footer class="template-dialog-footer">
        <button type="button" class="button secondary" id="cancel-briefing">Cancelar</button>
        <button type="submit" class="button" id="save-briefing">Criar briefing</button>
      </footer>
    </form>
  </dialog>
  <dialog id="complement-dialog" class="dialog">
    <form method="dialog" id="complement-form">
      <header>
        <div>
          <p class="eyebrow">CONFERÊNCIA</p>
          <h2>Solicitar complemento</h2>
        </div><button type="submit" class="icon" value="cancel" formnovalidate aria-label="Fechar">×</button>
      </header>
      <label>Pergunta<select id="complement-question" required></select></label><label>O que precisa ser complementado?<textarea id="complement-message" required maxlength="5000" rows="5" placeholder="Explique para o cliente o que precisa ser revisto."></textarea></label>
      <footer><button class="button" value="default" id="save-complement">Solicitar complemento</button></footer>
    </form>
  </dialog>
  <script src="../assets/js/briefing-ws.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/briefing-ws.js') ?>" defer></script>
  <script src="app.js?v=<?= (int) filemtime(__DIR__ . '/app.js') ?>" defer></script>
  <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/tributejs@5.1.3/dist/tribute.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

  <script src="<?php echo asset_url('../assets/pdfjs/pdf.min.js'); ?>"></script>
  <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>

  <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>