<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit;
}
require_once __DIR__ . '/../config/version.php';
$issueId = max(0, (int) ($_GET['id'] ?? 0));
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
    <main class="flow-block-shell fb-detail" id="flow-block-detail" data-issue-id="<?= $issueId ?>">
        <a class="fb-back" href="index.php"><i class="ri-arrow-left-line"></i> Flow Block</a>
        <div class="fb-loading"><i class="ri-loader-4-line"></i> Carregando Issue…</div>
    </main>
    <dialog class="fb-dialog" id="transition-dialog">
        <form method="dialog" class="fb-dialog-card" id="transition-form">
            <header>
                <div>
                    <h2 id="transition-title">Resolver Issue</h2>
                    <p id="transition-help">A tarefa voltará para Em andamento se esta for a última Issue bloqueante.</p>
                </div><button type="button" class="fb-icon-button" data-close-dialog><i class="ri-close-line"></i></button>
            </header><label>Comentário final<textarea id="transition-comment" rows="4" required placeholder="Registre como o impedimento foi tratado."></textarea></label>
            <footer><button type="button" class="fb-button fb-button--ghost" data-close-dialog>Voltar</button><button class="fb-button fb-button--primary" id="transition-submit">Confirmar</button></footer>
        </form>
    </dialog>
    <dialog class="fb-dialog" id="pause-dialog">
        <form method="dialog" class="fb-dialog-card" id="pause-form">
            <header>
                <div>
                    <h2 id="pause-title">Pausar Issue</h2>
                    <p id="pause-help">A pausa encerra o SLA inicial e cria um novo compromisso de retorno para o responsável.</p>
                </div><button type="button" class="fb-icon-button" data-close-pause><i class="ri-close-line"></i></button>
            </header>
            <label>Motivo da pausa<textarea id="pause-reason" rows="3" required placeholder="Explique por que a Issue não pode ser tratada agora."></textarea></label>
            <label>Observações<textarea id="pause-observation" rows="3" placeholder="Registre o retorno recebido ou o que ainda falta para resolver."></textarea></label>
            <label>Prazo previsto para retorno
                <input id="pause-return-at" type="datetime-local" required>
            </label>
            <label id="pause-responsible-group">Responsável
                <select id="pause-responsible"></select>
            </label>
            <div class="fb-pause-shortcuts" aria-label="Prazos rápidos">
                <button type="button" data-pause-hours="2">+2 horas</button>
                <button type="button" data-pause-hours="4">+4 horas</button>
                <button type="button" data-pause-hours="8">+8 horas</button>
                <button type="button" data-pause-tomorrow>Amanhã, 09:00</button>
            </div>
            <footer><button type="button" class="fb-button fb-button--ghost" data-close-pause>Voltar</button><button class="fb-button fb-button--primary" id="pause-submit">Confirmar pausa</button></footer>
        </form>
    </dialog>
    <dialog class="fb-dialog" id="reassign-dialog">
        <form method="dialog" class="fb-dialog-card" id="reassign-form">
            <header>
                <div>
                    <h2>Reatribuir Issue</h2>
                    <p>A reatribuição registra a primeira tratativa e inicia uma nova cobrança de 2 horas para o novo responsável.</p>
                </div><button type="button" class="fb-icon-button" data-close-reassign><i class="ri-close-line"></i></button>
            </header>
            <label>Novo responsável<select id="reassign-responsible" required></select></label>
            <footer><button type="button" class="fb-button fb-button--ghost" data-close-reassign>Voltar</button><button class="fb-button fb-button--primary">Reatribuir Issue</button></footer>
        </form>
    </dialog>
    <script>
        window.FlowBlockConfig = {
            api: 'api.php',
            list: 'index.php'
        };
    </script>
    <script src="<?= asset_url('app.js') ?>&fb=<?= filemtime(__DIR__ . '/app.js') ?>"></script>
    <script src="<?= asset_url('../script/sidebar.js') ?>"></script>
    <script src="<?= asset_url('../script/controleSessao.js') ?>"></script>
</body>

</html>
