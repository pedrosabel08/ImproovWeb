<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/version.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

if (!isset($_SESSION['nivel_acesso']) || !in_array((int) $_SESSION['nivel_acesso'], [1, 5], true)) {
    http_response_code(403);
    header('Location: ../index.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('./style.css'); ?>&contracts=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <title>Contratos</title>
</head>
<body>
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main class="contratos-main" aria-label="Central de contratos">
        <header class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" alt="" class="page-header-logo">
                <div>
                    <h1>Contratos</h1>
                    <p id="colaboradores-total" class="page-subtitle">Carregando colaboradores…</p>
                </div>
            </div>
            <label class="competencia-picker" for="competencia">
                <span>Competência</span>
                <select id="competencia" aria-label="Competência dos contratos"></select>
            </label>
        </header>

        <section class="summary-grid" aria-label="Resumo da competência">
            <article class="summary-card summary-assinado"><span class="summary-label"><i class="fa-solid fa-signature"></i> Assinados</span><strong id="resumo-assinado">—</strong></article>
            <article class="summary-card summary-pendente"><span class="summary-label"><i class="fa-regular fa-clock"></i> Aguardando assinatura</span><strong id="resumo-pendente">—</strong></article>
            <article class="summary-card summary-expirado"><span class="summary-label"><i class="fa-solid fa-triangle-exclamation"></i> Expirados</span><strong id="resumo-expirado">—</strong></article>
            <article class="summary-card summary-nao-gerado"><span class="summary-label"><i class="fa-regular fa-file"></i> Não gerados</span><strong id="resumo-nao-gerado">—</strong></article>
        </section>

        <section class="contratos-card">
            <div class="table-toolbar">
                <div class="filters" aria-label="Filtros de contratos">
                    <label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input id="pesquisa" type="search" placeholder="Pesquisar colaborador"></label>
                    <select id="filtro-status" aria-label="Filtrar por status">
                        <option value="">Todos os status</option>
                        <option value="nao_gerado">Não gerado</option>
                        <option value="gerado">Gerado</option>
                        <option value="enviado">Enviado</option>
                        <option value="visualizado">Visualizado</option>
                        <option value="assinado">Assinado</option>
                        <option value="expirado">Expirado</option>
                        <option value="recusado">Recusado</option>
                    </select>
                    <label class="pending-toggle"><input id="filtro-pendentes" type="checkbox"> <span>Somente pendentes</span></label>
                </div>
                <span id="table-count" class="table-count"></span>
            </div>

            <div id="bulk-actions" class="bulk-actions" hidden>
                <strong id="bulk-count">0 contratos selecionados</strong>
                <button type="button" class="bulk-btn" id="bulk-gerar"><i class="fa-solid fa-file-circle-plus"></i> Gerar contratos</button>
                <button type="button" class="bulk-btn" id="bulk-zip"><i class="fa-solid fa-file-zipper"></i> Baixar ZIP</button>
                <button type="button" class="bulk-btn" id="bulk-exportar"><i class="fa-solid fa-file-export"></i> Exportar</button>
                <button type="button" class="bulk-close" id="bulk-limpar" aria-label="Limpar seleção"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="table-scroll">
                <table id="contratos-table">
                    <thead>
                        <tr>
                            <th class="select-column"><input id="select-all" type="checkbox" aria-label="Selecionar contratos visíveis"></th>
                            <th><button class="sort-button" data-sort="colaborador">Colaborador <i class="fa-solid fa-sort"></i></button></th>
                            <th><button class="sort-button" data-sort="competencia">Competência <i class="fa-solid fa-sort"></i></button></th>
                            <th><button class="sort-button" data-sort="status">Status <i class="fa-solid fa-sort"></i></button></th>
                            <th><button class="sort-button" data-sort="atualizacao">Última atualização <i class="fa-solid fa-sort"></i></button></th>
                            <th class="actions-column">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="contratos-body">
                        <tr><td colspan="6" class="table-empty">Carregando contratos…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div id="menu-acoes" class="actions-menu" hidden></div>
    <div id="history-modal" class="history-modal" hidden role="dialog" aria-modal="true" aria-labelledby="history-title">
        <div class="history-dialog">
            <header><div><span class="eyebrow">Acompanhamento</span><h2 id="history-title">Histórico do contrato</h2></div><button id="history-close" class="icon-button" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button></header>
            <div id="history-content" class="history-content"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('./contratos.js'); ?>&contracts=<?php echo filemtime(__DIR__ . '/contratos.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>
</html>
