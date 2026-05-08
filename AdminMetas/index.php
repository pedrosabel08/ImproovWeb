<?php

/**
 * AdminMetas/index.php
 * Painel de administração de metas por colaborador/função.
 */

require_once __DIR__ . '/../config/session_bootstrap.php';

$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit;
}

$mesSel = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
$anoSel = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

// Validação
if ($mesSel < 1 || $mesSel > 12) $mesSel = (int) date('m');
if ($anoSel < 2020 || $anoSel > 2100) $anoSel = (int) date('Y');

$nomeMeses = [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro',
];

$anoAtual = (int) date('Y');

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Metas por Colaborador</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6.6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Sidebar & Modal de Sessão -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <!-- Módulo -->
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- ── Page Header ─────────────────────────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <img
                    src="../gif/assinatura_preto.gif"
                    class="page-header-logo"
                    id="gif"
                    style="height:36px; opacity:0.85"
                    alt="ImproovWeb">
                <h1 class="page-title">Admin — Metas por Colaborador</h1>
            </div>

            <div class="page-header-right">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-users"></i>
                    <span id="resultsCount">…</span> colaboradores
                </span>

                <button class="btn-salvar" id="btnSalvar" type="button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Salvar metas
                    <span id="pendingBadge" class="pending-badge" style="display:none">0</span>
                </button>

                <a href="../TvDashboard/index.php" target="_blank" class="btn-salvar" style="background:var(--text-secondary);text-decoration:none;font-size:12px;">
                    <i class="fa-solid fa-tv"></i> TV
                </a>
            </div>
        </div>

        <!-- ── Filter Bar ──────────────────────────────────────────────────── -->
        <div class="filters">
            <div class="filter-group">
                <label class="filter-label" for="selMes">Mês</label>
                <select id="selMes" class="filter-select">
                    <?php foreach ($nomeMeses as $i => $nome): ?>
                        <option value="<?= $i + 1 ?>" <?= ($i + 1 === $mesSel) ? 'selected' : '' ?>>
                            <?= $nome ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label" for="selAno">Ano</label>
                <select id="selAno" class="filter-select">
                    <?php for ($a = $anoAtual; $a >= $anoAtual - 4; $a--): ?>
                        <option value="<?= $a ?>" <?= ($a === $anoSel) ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button id="btnAplicar" class="btn-apply" type="button">
                    <i class="fa-solid fa-magnifying-glass"></i> Aplicar
                </button>
            </div>

            <div style="margin-left:auto; display:flex; align-items:flex-end; gap:12px;">
                <div style="font-size:11px; color:var(--text-muted); line-height:1.6;">
                    <span style="display:inline-flex;align-items:center;gap:5px;margin-right:10px;">
                        <span class="ind ind-below" style="width:16px;height:16px;font-size:9px;"><i class="fa-solid fa-arrow-down"></i></span>
                        Abaixo
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:5px;margin-right:10px;">
                        <span class="ind ind-atingida" style="width:16px;height:16px;font-size:9px;"><i class="fa-solid fa-check"></i></span>
                        Atingida
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:5px;margin-right:10px;">
                        <span class="ind ind-superada" style="width:16px;height:16px;font-size:9px;"><i class="fa-solid fa-arrow-up"></i></span>
                        Superada
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:5px;">
                        <span class="ind ind-recorde" style="width:16px;height:16px;font-size:9px;"><i class="fa-solid fa-trophy"></i></span>
                        Recorde
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Accordion List ──────────────────────────────────────────────── -->
        <div class="list-scroll-area" id="listaAcordoes">
            <!-- Preenchido via JS -->
        </div>

    </div>

    <?php include '../css/modalSessao.php'; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Toastify -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Sidebar & Controle de sessão -->
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>

    <!-- Passa dados do PHP para o JS -->
    <script>
        window.APP_MES = <?= $mesSel ?>;
        window.APP_ANO = <?= $anoSel ?>;
    </script>

    <!-- Módulo principal -->
    <script src="<?php echo asset_url('js/app.js'); ?>"></script>
</body>

</html>