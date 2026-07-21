<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit;
}
require_once __DIR__ . '/../config/version.php';
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flow Block</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
    <link rel="stylesheet" href="<?= asset_url('../css/styleSidebar.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('style.css') ?>&fb=<?= filemtime(__DIR__ . '/style.css') ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
</head>

<body class="flow-block-page">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main class="flow-block-shell" id="flow-block-app">
        <header class="fb-page-header">
            <div>
                <h1>Flow Block</h1>
                <p>Issues operacionais das tarefas</p>
            </div>
            <button class="fb-button fb-button--primary" id="new-issue"><i class="ri-add-line"></i> Nova Issue</button>
        </header>

        <section class="fb-toolbar" aria-label="Pesquisa e filtros">
            <label class="fb-search"><i class="ri-search-line"></i><input id="search" placeholder="Buscar código, tarefa, obra ou observação"></label>
            <button class="fb-filter-toggle" id="filter-toggle"><i class="ri-equalizer-2-line"></i> Filtros</button>
            <div class="fb-filter-panel" id="filter-panel" hidden>
                <select data-filter="tipo_id">
                    <option value="">Todos os tipos</option>
                </select>
                <select data-filter="fila_id">
                    <option value="">Todas as filas</option>
                </select>
                <select data-filter="responsavel_id">
                    <option value="">Todos os responsáveis</option>
                </select>
                <select data-filter="urgencia">
                    <option value="">Todas as urgências</option>
                    <option value="CRITICA">Crítica</option>
                    <option value="ALTA">Alta</option>
                    <option value="NORMAL">Normal</option>
                    <option value="BAIXA">Baixa</option>
                </select>
                <select data-filter="funcao_id">
                    <option value="">Todas as funções</option>
                </select>
                <label>De <input type="date" data-filter="from"></label>
                <label>Até <input type="date" data-filter="to"></label>
                <button class="fb-text-button" id="clear-filters">Limpar filtros</button>
            </div>
        </section>

        <nav class="fb-tabs" id="status-tabs" aria-label="Status das Issues">
            <button data-status="" class="is-active">Todas <span>0</span></button>
            <button data-status="ABERTA">Abertas <span>0</span></button>
            <!-- <button data-status="AGUARDANDO_ACAO">Aguardando ação <span>0</span></button> -->
            <button data-status="PAUSADA">Pausadas <span>0</span></button>
            <button data-status="RESOLVIDA">Resolvidas <span>0</span></button>
            <button data-status="CANCELADA">Canceladas <span>0</span></button>
            <button data-mentioned="1">Mencionaram você <span>0</span></button>
        </nav>

        <section class="fb-list-wrap">
            <div class="fb-loading" id="loading"><i class="ri-loader-4-line"></i> Carregando Issues…</div>
            <div class="fb-table-scroll">
                <table class="fb-table" id="issues-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Tarefa / Função</th>
                            <th>Obra</th>
                            <th>Tipo</th>
                            <th>Responsável</th>
                            <th>Estado</th>
                            <th>Próxima cobrança</th>
                            <th>Urgência</th>
                            <th>Aberta em</th>
                            <th>Tempo bloqueado</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="fb-empty" id="empty" hidden>Nenhuma Issue encontrada para estes filtros.</div>
            <footer class="fb-pagination"><span id="pagination-label"></span>
                <div id="pagination-buttons"></div>
            </footer>
        </section>
    </main>

    <dialog class="fb-dialog" id="issue-dialog">
        <form method="dialog" id="issue-form" class="fb-dialog-card">
            <header>
                <div>
                    <h2>Nova Issue</h2>
                    <p>O impedimento colocará a tarefa em HOLD automaticamente.</p>
                </div><button type="button" class="fb-icon-button" data-close-dialog><i class="ri-close-line"></i></button>
            </header>
            <label>Tarefa
                <input type="search" id="task-search" autocomplete="off" placeholder="Busque por imagem, função ou obra" required>
                <input type="hidden" id="task-id" required>
                <div class="fb-task-results" id="task-results"></div>
            </label>
            <div class="fb-form-grid"><label>Tipo <select id="issue-type" required></select></label><label>Fila responsável <select id="issue-queue">
                        <option value="">Não definida</option>
                    </select></label><label>Responsável <select id="issue-responsible">
                        <option value="">Não definido</option>
                    </select></label><label>Urgência <select id="issue-urgency">
                        <option value="NORMAL">Normal</option>
                        <option value="BAIXA">Baixa</option>
                        <option value="ALTA">Alta</option>
                        <option value="CRITICA">Crítica</option>
                    </select></label></div>
            <label>Observação<textarea id="issue-description" rows="4" maxlength="5000" placeholder="Explique de forma objetiva o que impede a continuidade." required></textarea></label>
            <footer><button type="button" class="fb-button fb-button--ghost" data-close-dialog>Cancelar</button><button class="fb-button fb-button--primary" value="default"><i class="ri-forbid-2-line"></i> Criar Issue</button></footer>
        </form>
    </dialog>
    <script>
        window.FlowBlockConfig = {
            api: 'api.php',
            detail: 'issue.php'
        };
    </script>
    <script src="<?= asset_url('app.js') ?>&fb=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
    <script src="<?= asset_url('../script/sidebar.js') ?>"></script>
    <script src="<?= asset_url('../script/controleSessao.js') ?>"></script>
</body>

</html>