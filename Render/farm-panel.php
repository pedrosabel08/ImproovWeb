<section id="renderFarm" class="farm-view" role="tabpanel" aria-labelledby="tabFarm" hidden>
    <div class="farm-summary">
        <section class="farm-metrics" aria-label="Indicadores da farm">
            <?php foreach ([
                'total' => ['Workers', 'fa-server', 'slate'],
                'rendering' => ['Renderizando', 'fa-play', 'blue'],
                'idle' => ['Ociosos', 'fa-pause', 'green'],
                'offline' => ['Offline', 'fa-power-off', 'red'],
                'stalled' => ['Stalled', 'fa-triangle-exclamation', 'amber'],
            ] as $key => [$label, $icon, $tone]): ?>
                <div class="overview-metric tone-<?= $tone ?>">
                    <span><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i><?= $label ?></span>
                    <strong data-farm-metric="<?= $key ?>">—</strong>
                    <?php if ($key !== 'total'): ?>
                        <progress data-farm-share="<?= $key ?>" aria-label="<?= $label ?> na farm" max="100" hidden></progress>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="farm-alerts" aria-labelledby="farmAlertsHeading">
            <header>
                <h2 id="farmAlertsHeading"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Alertas da farm <span id="farmAlertsCount"></span></h2>
                <button type="button" id="farmAlertsToggle" aria-expanded="false" aria-controls="farmAlertList" hidden>Ver histórico</button>
            </header>
            <ul id="farmAlertList"><li>Aguardando a primeira consulta.</li></ul>
        </section>
    </div>

    <div class="farm-toolbar">
        <label class="farm-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" id="farmSearch" aria-label="Buscar Worker ou job" placeholder="Buscar Worker ou job…"></label>
        <label><span>Status</span><select id="farmStatus"><option value="">Todos</option></select></label>
        <label><span>Pool</span><select id="farmPool"><option value="">Todos</option></select></label>
        <label><span>Grupo</span><select id="farmGroup"><option value="">Todos</option></select></label>
        <button type="button" id="farmRefresh" aria-label="Atualizar farm" title="Atualizar farm"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button>
    </div>

    <div id="farmNotice" class="farm-notice" role="status" hidden></div>
    <div id="farmLoading" class="render-loading" role="status" hidden>
        <canvas data-thinking-orb data-orb-state="searching" data-orb-size="44" aria-label="Consultando Workers"></canvas>
        <span>Consultando Workers…</span>
    </div>
    <div class="farm-workspace" id="farmWorkspace" hidden>
        <section class="farm-table-panel" aria-label="Workers do Deadline">
            <div class="farm-table-scroll">
                <table class="farm-table">
                    <thead><tr><th scope="col">Worker</th><th scope="col">Status</th><th scope="col">Job atual</th><th scope="col">Progresso</th><th scope="col">Tempo</th></tr></thead>
                    <tbody id="farmWorkers"></tbody>
                </table>
            </div>
            <footer><span id="farmShowing"></span><span id="farmUpdated"></span></footer>
        </section>

        <aside id="farmDetail" class="farm-detail" aria-labelledby="farmWorkerName" hidden>
            <header>
                <span id="farmWorkerDot" class="farm-worker-dot" aria-hidden="true"></span>
                <div><h2 id="farmWorkerName"></h2><p id="farmWorkerDescription"></p></div>
                <button type="button" id="farmDetailClose" aria-label="Fechar detalhes do Worker"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="history-tabs" aria-label="Detalhes do Worker" role="group">
                <button type="button" data-farm-detail="overview" aria-pressed="true" aria-controls="farmDetailBody"><i class="fa-solid fa-gauge" aria-hidden="true"></i> Visão geral</button>
                <button type="button" data-farm-detail="tasks" aria-pressed="false" aria-controls="farmDetailBody"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Job e tarefas</button>
                <button type="button" data-farm-detail="history" aria-pressed="false" aria-controls="farmDetailBody"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Histórico</button>
            </div>
            <div id="farmDetailBody"></div>
        </aside>
    </div>
</section>
