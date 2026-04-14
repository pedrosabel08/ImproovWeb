<?php

/**
 * TvDashboard/admin.php
 * Painel de configuração de metas por função (protegido por sessão).
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

// session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit;
}

include '../conexao.php';

$mesSel = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anoSel = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Lista de funções exibidas na TV
// funcao_id=4 → Finalização Completa | funcao_id=7 → Finalização de Planta Humanizada
// Finalização Parcial não tem meta — não aparece aqui
$funcoesTv = [
    ['idfuncao' => 1, 'nome_funcao' => 'Caderno'],
    ['idfuncao' => 8, 'nome_funcao' => 'Filtro de assets'],
    ['idfuncao' => 2, 'nome_funcao' => 'Modelagem'],
    ['idfuncao' => 3, 'nome_funcao' => 'Composição'],
    ['idfuncao' => 9, 'nome_funcao' => 'Pré-Finalização'],
    ['idfuncao' => 4, 'nome_funcao' => 'Finalização Completa'],
    ['idfuncao' => 7, 'nome_funcao' => 'Finalização de Planta Humanizada'],
    ['idfuncao' => 5, 'nome_funcao' => 'Pós-produção'],
];

// Busca metas salvas para o mês/ano selecionado
$ids = implode(',', array_map('intval', array_column($funcoesTv, 'idfuncao')));
$sqlMetas = "SELECT funcao_id, quantidade_meta FROM metas WHERE funcao_id IN ($ids) AND mes = ? AND ano = ?";
$stmtF = $conn->prepare($sqlMetas);
$stmtF->bind_param('ii', $mesSel, $anoSel);
$stmtF->execute();
$metasMap = [];
foreach ($stmtF->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $metasMap[(int)$r['funcao_id']] = (int)$r['quantidade_meta'];
}
$stmtF->close();
$conn->close();

foreach ($funcoesTv as &$f) {
    $f['quantidade_meta'] = isset($metasMap[$f['idfuncao']]) ? $metasMap[$f['idfuncao']] : '';
}
unset($f);

$funcoes = $funcoesTv;

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
    'Dezembro'
];
$anoAtual = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV — Configurar Metas</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <style>
        :root {
            --bg-body: #f0f2f5;
            --bg-surface: #ffffff;
            --bg-card: #ffffff;
            --border-card: #e2e6eb;
            --text-primary: #1a1d23;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --accent: #4f80e1;
            --accent-hover: #3b6fd6;
            --accent-subtle: rgba(79, 128, 225, 0.1);
            --status-finalizado: #10b981;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --radius-md: 12px;
            --radius-sm: 8px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            display: grid;
            grid-template-columns: 60px 1fr;
            min-height: 100vh;
            background: var(--bg-body);
            font-family: "Inter", sans-serif;
            color: var(--text-primary);
        }

        .container {
            padding: 32px 36px;
            overflow-y: auto;
            grid-column: 2;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .page-header .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tv-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--bg-surface);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.15s;
        }

        .tv-link:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Filtros */
        .filtros {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-surface);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filtros label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-muted);
        }

        .filtros select {
            padding: 7px 12px;
            border: 1px solid #d1d5db;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: inherit;
            background: #fff;
            color: var(--text-primary);
            cursor: pointer;
        }

        .btn-filtrar {
            padding: 8px 18px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s;
        }

        .btn-filtrar:hover {
            background: var(--accent-hover);
        }

        /* Card de metas */
        .metas-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .metas-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-card);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metas-card-header h2 {
            font-size: 14px;
            font-weight: 700;
        }

        .periodo-badge {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            background: var(--accent-subtle);
            padding: 4px 12px;
            border-radius: 999px;
        }

        table.metas-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.metas-table thead th {
            padding: 10px 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            text-align: left;
            background: #f8f9fb;
            border-bottom: 1px solid var(--border-card);
        }

        table.metas-table tbody tr {
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.12s;
        }

        table.metas-table tbody tr:last-child {
            border-bottom: none;
        }

        table.metas-table tbody tr:hover {
            background: #f4f7ff;
        }

        table.metas-table tbody td {
            padding: 14px 20px;
            font-size: 14px;
            vertical-align: middle;
        }

        .func-color-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }

        input.meta-input {
            width: 110px;
            padding: 7px 12px;
            border: 1px solid #d1d5db;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            text-align: center;
            transition: border-color 0.15s;
        }

        input.meta-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-subtle);
        }

        .metas-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-card);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-salvar {
            padding: 10px 28px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }

        .btn-salvar:hover {
            background: var(--accent-hover);
        }

        .btn-salvar:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #saveMsg {
            font-size: 13px;
            font-weight: 500;
            display: none;
        }

        #saveMsg.success {
            color: var(--status-finalizado);
        }

        #saveMsg.error {
            color: #ef4444;
        }
    </style>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fa-solid fa-tv" style="color:var(--accent);margin-right:10px"></i>Configurar Metas — TV Dashboard</h1>
            <div class="header-actions">
                <a href="index.php" target="_blank" class="tv-link">
                    <i class="fa-solid fa-display"></i> Abrir TV
                </a>
            </div>
        </div>

        <!-- Filtro de mês/ano -->
        <form class="filtros" method="get" id="formFiltro">
            <label for="mes">Mês</label>
            <select id="mes" name="mes">
                <?php foreach ($nomeMeses as $i => $nome): ?>
                    <option value="<?= $i + 1 ?>" <?= ($i + 1 === $mesSel) ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach; ?>
            </select>
            <label for="ano">Ano</label>
            <select id="ano" name="ano">
                <?php for ($a = $anoAtual; $a >= $anoAtual - 3; $a--): ?>
                    <option value="<?= $a ?>" <?= ($a === $anoSel) ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn-filtrar">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar
            </button>
        </form>

        <!-- Tabela de metas -->
        <div class="metas-card">
            <div class="metas-card-header">
                <h2>Metas por função</h2>
                <span class="periodo-badge"><?= $nomeMeses[$mesSel - 1] . ' / ' . $anoSel ?></span>
            </div>

            <table class="metas-table" id="metasTable">
                <thead>
                    <tr>
                        <th>Função</th>
                        <th>Meta do mês</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $funcColors = [
                        'Caderno'                          => '#38bdf8',
                        'Filtro de assets'                 => '#a78bfa',
                        'Modelagem'                        => '#fb923c',
                        'Composição'                       => '#34d399',
                        'Pré-Finalização'                  => '#fbbf24',
                        'Finalização Parcial'              => '#f87171',
                        'Finalização Completa'             => '#4ade80',
                        'Finalização de Planta Humanizada' => '#2dd4bf',
                        'Pós-produção'                     => '#c084fc',
                        'Alteração'                        => '#94a3b8',
                    ];
                    foreach ($funcoes as $f):
                        $cor = $funcColors[$f['nome_funcao']] ?? '#94a3b8';
                    ?>
                        <tr>
                            <td>
                                <span class="func-color-dot" style="background:<?= htmlspecialchars($cor) ?>"></span>
                                <?= htmlspecialchars($f['nome_funcao']) ?>
                                <input type="hidden" name="funcao_id[]" value="<?= (int)$f['idfuncao'] ?>">
                            </td>
                            <td>
                                <input
                                    type="number"
                                    class="meta-input"
                                    data-funcao-id="<?= (int)$f['idfuncao'] ?>"
                                    value="<?= htmlspecialchars($f['quantidade_meta']) ?>"
                                    min="0"
                                    placeholder="—">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="metas-footer">
                <span id="saveMsg"></span>
                <button class="btn-salvar" id="btnSalvar" type="button">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar metas
                </button>
            </div>
        </div>
    </div>

    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <script>
        document.getElementById('btnSalvar').addEventListener('click', () => {
            const mes = <?= $mesSel ?>;
            const ano = <?= $anoSel ?>;
            const inputs = document.querySelectorAll('input.meta-input');
            const metas = [];
            inputs.forEach(inp => {
                const val = inp.value.trim();
                if (val !== '') {
                    metas.push({
                        funcao_id: parseInt(inp.dataset.funcaoId, 10),
                        quantidade_meta: parseInt(val, 10),
                    });
                }
            });

            if (metas.length === 0) {
                showMsg('Nenhuma meta preenchida.', 'error');
                return;
            }

            const btn = document.getElementById('btnSalvar');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

            fetch('salvar_meta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        mes,
                        ano,
                        metas
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        showMsg(`${data.saved} meta(s) salva(s) com sucesso!`, 'success');
                    } else {
                        showMsg(data.error || 'Erro ao salvar.', 'error');
                    }
                })
                .catch(() => showMsg('Falha de conexão.', 'error'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar metas';
                });
        });

        function showMsg(txt, type) {
            const el = document.getElementById('saveMsg');
            el.textContent = txt;
            el.className = type;
            el.style.display = 'inline';
            setTimeout(() => {
                el.style.display = 'none';
            }, 4000);
        }
    </script>
</body>

</html>