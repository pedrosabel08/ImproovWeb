
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

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// $ultima_atividade = date('Y-m-d H:i:s');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

include '../conexaoMain.php';
$conn = conectarBanco();

$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);
if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}
$stmt2->bind_param("si", $tela_atual, $idusuario);
if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);

$status_imagens = obterStatusImagens($conn);
$conn->close();

$nomeUsuario = trim((string) ($_SESSION['nome_usuario'] ?? 'Operação'));
$primeiroNome = $nomeUsuario !== '' ? explode(' ', $nomeUsuario)[0] : 'Operação';
$iniciais = strtoupper(substr($primeiroNome, 0, 1));
$sessaoAtiva = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Projetos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
</head>

<body class="gestao-body">
    <?php

    include '../sidebar.php';

    ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="gestao-shell">
        <header class="topbar" id="visao-geral">
            <div class="topbar-copy">
                <div>
                    <p class="eyebrow">Operação</p>
                    <h1>Gestão de Projetos</h1>
                    <p class="subheadline">Visão geral da operação</p>
                </div>
            </div>

            <div class="topbar-actions">
                <label class="period-pill" for="periodFilter">
                    <i class="fa-solid fa-calendar-days"></i>
                    <select id="periodFilter" aria-label="Filtro de período">
                        <option value="7" selected>Últimos 7 dias</option>
                        <option value="14">Últimos 14 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </label>

                <button class="ghost-button" id="filtersButton" type="button" aria-expanded="false" aria-controls="filtros-operacionais">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Filtros</span>
                </button>
            </div>
        </header>

        <section class="filters-drawer" id="filtros-operacionais" hidden>
            <div class="filters-header">
                <div>
                    <p class="eyebrow">Refinar visualização</p>
                    <h2>Filtros operacionais</h2>
                </div>

                <button class="primary-button" id="openEntregaModal" type="button">
                    <i class="fa-solid fa-plus"></i>
                    <span>Nova entrega</span>
                </button>
            </div>

            <div class="filters-grid">
                <label class="toggle-card" for="criticalOnly">
                    <input type="checkbox" id="criticalOnly">
                    <span>Somente críticos</span>
                    <small>Foco em risco, atraso e revisão.</small>
                </label>

                <label class="toggle-card" for="includeHold">
                    <input type="checkbox" id="includeHold" checked>
                    <span>Exibir HOLD</span>
                    <small>Mantém entregas pausadas no radar.</small>
                </label>

                <label class="toggle-card" for="onlyOverloaded">
                    <input type="checkbox" id="onlyOverloaded">
                    <span>Equipe sobrecarregada</span>
                    <small>Destaca somente capacidade crítica.</small>
                </label>

                <label class="search-card" for="projectSearch">
                    <span>Busca rápida</span>
                    <input type="search" id="projectSearch" placeholder="Filtrar por projeto, equipe ou tema">
                </label>
            </div>
        </section>


        <div class="content-scroll">
            <section class="kpi-grid" id="kpiGrid" aria-label="Indicadores principais"></section>

            <section class="dashboard-row dashboard-row--three">
                <article class="panel panel--risk" id="radar-risco">
                    <div class="panel-header">
                        <div>
                            <p class="panel-kicker">Radar executivo</p>
                            <h2>RADAR DE RISCO - PROJETOS</h2>
                        </div>
                        <button class="panel-link" type="button">Ver prioridades</button>
                    </div>
                    <div class="panel-body" id="riskRadarPanel"></div>
                </article>

                <article class="panel panel--bottlenecks" id="gargalos-funcao">
                    <div class="panel-header">
                        <div>
                            <p class="panel-kicker">Fluxo operacional</p>
                            <h2>GARGALOS POR FUNÇÃO</h2>
                        </div>
                        <button class="panel-link" type="button">Ver gargalos</button>
                    </div>
                    <div class="panel-body" id="bottlenecksPanel"></div>
                </article>

                <article class="panel panel--capacity" id="capacidade-equipe">
                    <div class="panel-header">
                        <div>
                            <p class="panel-kicker">Balanceamento</p>
                            <h2>CAPACIDADE EQUIPE</h2>
                        </div>
                        <button class="panel-link" type="button">Ver toda a equipe</button>
                    </div>
                    <div class="panel-body" id="capacityPanel"></div>
                </article>
            </section>

            <section class="dashboard-row dashboard-row--two">
                <article class="panel panel--schedule" id="entregas-semana">
                    <div class="panel-header">
                        <div>
                            <p class="panel-kicker">Planejamento</p>
                            <h2>ENTREGAS - 7 DIAS</h2>
                        </div>
                        <button class="panel-link" type="button">Próxima semana</button>
                    </div>
                    <div class="panel-body" id="schedulePanel"></div>
                </article>

                <article class="panel panel--activity" id="atividades-recentes">
                    <div class="panel-header">
                        <div>
                            <p class="panel-kicker">Movimentação</p>
                            <h2>ATIVIDADES RECENTES</h2>
                        </div>
                        <button class="panel-link" type="button">Timeline</button>
                    </div>
                    <div class="panel-body" id="activitiesPanel"></div>
                </article>
            </section>

            <section class="status-strip" id="status-operacional" aria-label="Atalhos operacionais"></section>
        </div>
    </main>

    <div class="entrega-modal" id="entregaModal" aria-hidden="true">
        <div class="entrega-modal-backdrop" data-close-entrega></div>
        <div class="entrega-modal-panel">
            <div class="entrega-modal-header">
                <div>
                    <p class="panel-kicker">Ação rápida</p>
                    <h2>Adicionar entrega</h2>
                    <p class="modal-copy">Crie uma nova entrega sem sair da visão executiva.</p>
                </div>

                <button class="icon-button" id="closeEntregaModal" type="button" aria-label="Fechar modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="formAdicionarEntrega" class="entrega-form">
                <div class="form-grid">
                    <label class="form-field">
                        <span>Obra</span>
                        <select name="obra_id" id="obra_id" required>
                            <option value="">Selecione a obra</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= (int) $obra['idobra']; ?>">
                                    <?= htmlspecialchars($obra['nomenclatura']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="form-field">
                        <span>Status</span>
                        <select name="status_id" id="status_id" required>
                            <option value="">Selecione o status</option>
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= (int) $status['idstatus']; ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="form-field">
                        <span>Prazo</span>
                        <input type="date" name="prazo" id="prazo">
                    </label>

                    <label class="form-field">
                        <span>Observações</span>
                        <textarea name="observacoes" id="observacoes" rows="3" placeholder="Detalhes da entrega, riscos ou contexto adicional"></textarea>
                    </label>
                </div>

                <div class="form-field form-field--wide">
                    <div class="field-header">
                        <span>Imagens vinculadas</span>
                        <small>Selecione uma obra e status para carregar os itens.</small>
                    </div>

                    <div id="imagens_container" class="imagens-container">
                        <p>Selecione uma obra e um status para listar as imagens.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="ghost-button" type="button" data-close-entrega>
                        <span>Cancelar</span>
                    </button>
                    <button class="primary-button" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Salvar entrega</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.GESTAO_CONFIG = {
            dashboardUrl: <?php echo json_encode(asset_url('getDashboardData.php')); ?>,
            getImagensUrl: <?php echo json_encode('../Entregas/get_imagens.php'); ?>,
            saveEntregaUrl: <?php echo json_encode('../Entregas/save_entrega.php'); ?>,
            currentUser: <?php echo json_encode($nomeUsuario); ?>
        };
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <?php if ($sessaoAtiva): ?>
        <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <?php endif; ?>
</body>

</html>