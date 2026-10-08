<?php
ini_set('display_errors', '0');
require_once __DIR__.'/pagamento_auth.php';
require_once __DIR__.'/../config/pagamento_fechamento.php';
if (!function_exists('asset_url')) {
    try {
        require_once __DIR__.'/../config/version.php';
    } catch (Throwable $e) {
        if (!function_exists('asset_url')) {
            function asset_url(string $path): string
            {
                return $path.'?v=dev';
            }
        }
    }
}
if (($_SESSION['logado'] ?? false) !== true || !pagamento_is_gestor()) {
    header('Location: ../index.html');
    exit;
}
$unavailable = null;
if (!pagamento_fechamento_enabled()) {
    http_response_code(404);
    $unavailable = 'O fechamento mensal está indisponível neste ambiente.';
}
$ref = (string)($_GET['competencia'] ?? '');
if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/D', $ref)) {
    $ref = (new DateTimeImmutable('first day of last month', new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="pagamento-csrf" content="<?= htmlspecialchars(pagamento_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<title>Fechamento mensal — Pagamento</title>
<link rel="stylesheet" href="<?= asset_url('style.css') ?>">
<link rel="stylesheet" href="<?= asset_url('../css/styleSidebar.css') ?>">
<link rel="stylesheet" href="<?= asset_url('visao-geral.css') ?>&ui=<?= filemtime(__DIR__.'/visao-geral.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= asset_url('fechamento.css') ?>">
<link rel="stylesheet" href="<?= asset_url('mensal.css') ?>&m=<?= filemtime(__DIR__.'/mensal.css') ?>">
</head>
<body>
<?php include __DIR__.'/../sidebar.php'; ?>
<main class="container fechamento-page fm-page" id="fm-page-root" aria-busy="true">
<header class="page-header payment-header">
<div class="payment-heading"><img src="../gif/assinatura_branco.gif" id="gif" alt="ImproovWeb"><div><h1 class="page-title">Pagamento</h1><p class="payment-subtitle">Custos de produção, pagamentos e adendos</p></div></div>
<nav class="payment-header-actions" aria-label="Pagamento"><div class="payment-view-toggle" role="group" aria-label="Visão de pagamentos"><a data-payment-link="geral" href="./?view=geral">Visão geral</a><a data-payment-link="colaborador" href="./?view=colaborador">Por colaborador</a><a href="fechamento.php" class="is-active" aria-current="page">Fechamento</a></div><a class="btn btn-status-geral" id="fm-status-link" href="./?view=colaborador&amp;adendos=1"><i class="fa-regular fa-file-lines" aria-hidden="true"></i>Status geral dos adendos</a></nav>
</header>
<?php if ($unavailable): ?>
<p role="alert" class="fc-notice"><?= htmlspecialchars($unavailable, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
<section class="fm-context" id="fm-context" aria-label="Competência do fechamento">
<input id="fm-month" type="hidden" value="<?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?>">
<label for="fm-select-month">Mês<select id="fm-select-month"><?php foreach (['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'] as $i => $mes): ?><option value="<?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?>" <?= (int)substr($ref, 5, 2) === $i + 1 ? 'selected' : '' ?>><?= $mes ?></option><?php endforeach ?></select></label>
<label for="fm-select-year">Ano<select id="fm-select-year"><?php for ($ano = max((int)date('Y') + 1, (int)substr($ref, 0, 4));$ano >= min((int)date('Y') - 5, (int)substr($ref, 0, 4));$ano--): ?><option <?= $ano === (int)substr($ref, 0, 4) ? 'selected' : '' ?>><?= $ano ?></option><?php endfor ?></select></label>
<span class="fm-context-count"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span id="fm-context-count">Carregando competência</span></span>
<span class="fm-started" id="fm-started" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Fechamento iniciado</span>
<button type="button" class="btn btn-primary" id="fm-start">Iniciar fechamento</button>
<button type="button" class="btn btn-secondary" id="fm-refresh">Atualizar cadastros</button>
</section>
<div id="fm-message" class="fc-notice" role="status" aria-live="polite" hidden></div>
<div id="fm-busy" class="fc-busy" role="status" aria-live="polite"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Carregando fechamento"></canvas><span id="fm-busy-label">Carregando fechamento</span></div>
<div id="fm-retry" class="fc-notice" hidden><p>A ação ainda precisa ser concluída. Tente novamente para continuar com segurança.</p><button type="button" class="btn btn-secondary" id="fm-retry-button">Tentar novamente</button></div>
<aside id="fm-config" class="fm-config" hidden aria-labelledby="fm-config-title">
<details><summary><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span id="fm-config-title"></span><span class="fm-config-action">Revisar cadastros</span></summary><p>Defina “Participa do fechamento mensal?” no cadastro. Esses colaboradores ficam fora da fila até serem marcados como Sim.</p><ul id="fm-config-list"></ul></details>
</aside>
<div class="fm-cycle-strip"><span id="fm-cycle-state" class="fm-badge"></span><p id="fm-cycle-financial" role="status"></p><button type="button" id="fm-conclude" class="btn btn-primary" hidden>Concluir fechamento</button></div>
<section class="fm-summary" aria-label="Resumo do fechamento"><h2 id="fm-period" class="fm-sr-only"></h2><div class="fm-stats" id="fm-stats"></div>
<div class="fm-progress"><span id="fm-progress-label"></span><div><progress id="fm-progress" value="0" max="1" aria-label="Colaboradores revisados"></progress><span id="fm-progress-percent">0%</span></div></div></section>
<section id="fm-overview">
<div class="fm-list-heading"><h2>Colaboradores da competência</h2>
<button class="btn btn-primary" type="button" id="fm-review" disabled>Revisar fechamento</button>
</div>
<div class="fm-finish" id="fm-finish" hidden aria-live="polite"></div>
<table class="fm-list"><thead><tr><th>Colaborador</th><th>Remuneração</th><th>Fixo</th><th>Adendos</th><th>Extras / bônus</th><th>Descontos</th><th>Total</th><th>Revisão</th><th>Pagamento</th></tr></thead><tbody id="fm-list"></tbody></table>
</section>
<section id="fm-step" hidden>
<div class="fm-review-layout">
<article class="fm-financial" aria-label="Revisão do colaborador">
<div class="fm-person-navigation"><button type="button" class="btn btn-secondary fm-icon-button" id="fm-person-previous" aria-label="Colaborador anterior" title="Colaborador anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button><button type="button" class="btn btn-secondary fm-icon-button" id="fm-person-next" aria-label="Próximo colaborador" title="Próximo colaborador"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button><span>Colaborador <strong id="fm-position"></strong></span><button type="button" class="btn btn-secondary fm-icon-button" id="fm-back" aria-label="Voltar ao fechamento" title="Ver lista de colaboradores"><i class="fa-solid fa-list" aria-hidden="true"></i></button></div>
<header class="fm-review-header"><span class="fm-avatar" id="fm-avatar" aria-hidden="true"></span><div class="fm-person-name"><h2 id="fm-name"></h2><span id="fm-type" class="fm-badge"></span></div><div class="fm-person-status"><small>Status do adendo</small><span id="fm-person-status" class="fm-badge"></span></div></header>
<div class="fm-financial-scroll">
<div id="fm-attention"></div><dl class="fm-breakdown" id="fm-breakdown"></dl>
<details id="fm-service-details"><summary>Ver detalhes dos serviços</summary><p class="fm-service-help">Retiradas valem apenas para este colaborador nesta competência. Tarefas pagas permanecem no histórico.</p><div class="fm-service-batch"><label for="fm-service-function">Função</label><select id="fm-service-function"></select><button type="button" class="btn btn-secondary" id="fm-withdraw-function">Retirar função</button><button type="button" class="btn btn-secondary" id="fm-restore-function">Restaurar função</button></div><div class="fc-table-wrap"><table class="fc-table"><thead><tr><th>Imagem / função</th><th>Valor devido</th><th>Situação</th><th>Ação</th></tr></thead><tbody id="fm-services"></tbody></table></div></details>
<div id="fm-extras"></div>
<div class="fc-actions"><button class="btn btn-secondary" id="fm-discount" type="button">Desconto</button><button class="btn btn-secondary" id="fm-extra" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i>Adicionar extra</button><a class="btn btn-secondary" id="fm-edit" href="../Colaborador/" target="_blank" rel="noopener"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar cadastro</a><button class="btn btn-secondary" id="fm-recalculate" type="button"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>Atualizar valores</button></div>
</div></article>
<footer class="fm-action-footer"><dl class="fm-breakdown" id="fm-total-summary"></dl><div class="fm-review-actions"><button class="btn btn-primary" id="fm-confirm" type="button" disabled>Confirmar e próximo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button><button class="btn btn-secondary" id="fm-skip" type="button">Pular por enquanto</button></div><p id="fm-document-state" role="status" aria-live="polite"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><span></span></p></footer>
<aside class="fm-document" id="fm-document" aria-label="Adendo para conferência">
<div class="fm-document-header"><h3>Adendo para conferência</h3><a id="fm-download" class="btn btn-secondary" hidden><i class="fa-solid fa-download" aria-hidden="true"></i>Baixar PDF</a></div>
<div class="fm-pdf-toolbar" aria-label="Controles do PDF"><div class="fm-pdf-navigation"><button id="fm-pdf-previous" class="btn btn-secondary fm-icon-button" aria-label="Página anterior" title="Página anterior" disabled><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button><span id="fm-pdf-page"></span><button id="fm-pdf-next" class="btn btn-secondary fm-icon-button" aria-label="Próxima página" title="Próxima página" disabled><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div><div class="fm-pdf-tools"><button id="fm-zoom-out" class="btn btn-secondary fm-icon-button" aria-label="Diminuir zoom" title="Diminuir zoom" disabled><i class="fa-solid fa-minus" aria-hidden="true"></i></button><span id="fm-zoom">100%</span><button id="fm-zoom-in" class="btn btn-secondary fm-icon-button" aria-label="Aumentar zoom" title="Aumentar zoom" disabled><i class="fa-solid fa-plus" aria-hidden="true"></i></button><button id="fm-fullscreen" class="btn btn-secondary fm-icon-button" aria-label="Tela cheia" title="Tela cheia" aria-pressed="false"><i class="fa-solid fa-expand" aria-hidden="true"></i></button></div></div>
<div class="fm-pdf-area"><div id="fm-pdf" class="fc-pdf-pages" aria-label="PDF do adendo" tabindex="0"></div><div id="fm-pdf-placeholder" class="fm-pdf-placeholder" role="status" aria-live="polite" aria-busy="false"><canvas id="fm-pdf-orb" class="fm-pdf-orb" data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Preparando PDF do adendo" hidden></canvas><i id="fm-pdf-placeholder-icon" class="fa-regular fa-file-lines" aria-hidden="true"></i><strong id="fm-pdf-placeholder-title">Adendo para conferência</strong><p id="fm-pdf-placeholder-text">Os documentos desta competência aparecerão aqui.</p></div></div>
</aside></div>
</section>
<dialog id="fm-service-dialog" class="fc-dialog" aria-labelledby="fm-service-title"><form id="fm-service-form"><header><h2 id="fm-service-title">Retirar tarefa</h2><button type="button" class="btn btn-secondary" id="fm-service-close">Fechar</button></header><p id="fm-service-target"></p><label>Motivo<textarea name="motivo" required maxlength="1000" rows="3"></textarea></label><p>A lista e o total serão atualizados. Será necessário conferir e confirmar o novo PDF.</p><button type="submit" class="btn btn-primary" id="fm-service-submit">Confirmar retirada</button></form></dialog>
<dialog id="fm-extra-dialog" class="fc-dialog" aria-labelledby="fm-extra-title"><form id="fm-extra-form">
<header><h2 id="fm-extra-title">Extra manual</h2><button class="btn btn-secondary" type="button" id="fm-extra-close">Fechar</button></header>
<p id="fm-extra-scope"></p><label id="fm-extra-description">Descrição<input name="categoria" required maxlength="160" placeholder="Bônus qualidade"></label>
<label>Valor (R$)<input name="valor" inputmode="decimal" required placeholder="500,00"></label>
<label>Motivo<textarea name="motivo" required maxlength="1000" rows="2"></textarea></label>
<footer><button type="submit" class="btn btn-primary">Salvar extra</button></footer></form></dialog>
<?php endif ?>
</main>
<script src="<?= asset_url('../script/sidebar.js') ?>"></script>
<?php if (!$unavailable): ?>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
<script src="<?= asset_url('animacoes.js') ?>&payment=<?= filemtime(__DIR__.'/animacoes.js') ?>"></script>
<script src="<?= asset_url('../assets/js/thinking-orbs.js') ?>"></script>
<script src="<?= asset_url('../assets/pdfjs/pdf.min.js') ?>"></script>
<script type="module" src="<?= asset_url('mensal.js') ?>&m=<?= filemtime(__DIR__.'/mensal.js') ?>"></script>
<?php endif ?>
</body></html>
