<nav class="render-tabs" aria-label="Área de Renders" role="tablist">
    <?php foreach ([
        ['Pending', 'Pendentes', 'fa-solid fa-box-archive', 'pending', 'renderLibrary'],
        ['Approved', 'Aprovados', 'fa-regular fa-circle-check', 'approved', 'renderLibrary'],
        ['Farm', 'Farm', 'fa-solid fa-server', 'farm', 'renderFarm'],
    ] as [$id, $label, $icon, $view, $panel]): ?>
        <button type="button" role="tab" id="tab<?= $id ?>" aria-selected="<?= $view === 'pending' ? 'true' : 'false' ?>"
            aria-controls="<?= $panel ?>" tabindex="<?= $view === 'pending' ? '0' : '-1' ?>" data-render-view="<?= $view ?>">
            <i class="<?= $icon ?>" aria-hidden="true"></i> <?= $label ?>
            <span id="<?= $view ?>Count" <?= $view === 'farm' ? 'hidden' : '' ?>>—</span>
        </button>
    <?php endforeach; ?>
</nav>
