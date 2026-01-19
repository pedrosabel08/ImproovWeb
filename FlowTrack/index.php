<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}


$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flow | Track</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
    <style>
        /* Entry modal styles */
        .ft-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .ft-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .ft-modal-content {
            position: relative;
            background: #fff;
            color: #000;
            padding: 20px;
            border-radius: 8px;
            width: 420px;
            max-width: 95%;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.3);
            text-align: left;
        }

        .ft-modal-content h2 {
            margin: 0 0 8px 0;
        }

        .ft-modal-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .ft-btn {
            padding: 8px 12px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            cursor: pointer;
            border-radius: 4px;
        }

        .ft-btn-primary {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }
    </style>
</head>

<body>
    <!-- Entry modal -->
    <div id="ft-entry-modal" class="ft-modal" role="dialog" aria-modal="true">
        <div class="ft-modal-backdrop" data-close></div>
        <div class="ft-modal-content" aria-labelledby="ft-modal-title">
            <h2 id="ft-modal-title">Para onde deseja ir?</h2>
            <p>Escolha uma opção para continuar:</p>
            <div class="ft-modal-buttons">
                <button type="button" class="ft-btn ft-btn-primary" data-target="Report/index.php">Report (Obras)</button>
                <button type="button" class="ft-btn" data-target="index.php">Finalizações</button>
                <button type="button" class="ft-btn" data-target="Radar/index.php">Tarefas</button>
            </div>
            <div style="margin-top:12px;text-align:right;"><button id="ft-modal-close" class="ft-btn">Fechar</button></div>
        </div>
    </div>

    <?php
    include '../sidebar.php';
    ?>
    <div class="container">

        <header class="ft-header">
            <h1>Imagens em Finalização (P00 / R00)</h1>
            <div class="ft-filters">
                <select id="filterObra">
                    <option value="">Todas as obras</option>
                </select>
                <select id="filterTipoImagem">
                    <option value="">Todos os tipos</option>
                </select>
                <select id="filterFinalizador">
                    <option value="">Todos os finalizadores</option>
                </select>
                <select id="filterEtapa">
                    <option value="">Todas etapas</option>
                    <option value="P00">P00</option>
                    <option value="R00">R00</option>
                </select>
                <select id="filterStatusFuncao">
                    <option value="">Todos status</option>
                </select>
                <button id="btnLimpar">Limpar</button>
            </div>
            <div id="ft-kpis" class="ft-kpis" aria-live="polite">
                <div class="kpi total"><strong>Total:</strong> <span id="kpiTotal">0</span></div>
                <div class="kpi p00"><strong>P00:</strong> <span id="kpiP00">0</span></div>
                <div class="kpi r00"><strong>R00:</strong> <span id="kpiR00">0</span></div>
                <div class="kpi statusSummary"><strong>Por status:</strong> <span id="kpiStatus">-</span></div>
            </div>
        </header>

        <main class="ft-main">
            <section class="ft-col">
                <h2>P00</h2>
                <div id="colP00" class="ft-cards"></div>
            </section>
            <section class="ft-col">
                <h2>R00</h2>
                <div id="colR00" class="ft-cards"></div>
            </section>
        </main>

        <template id="cardTemplate">
            <article class="ft-card">
                <div class="ft-card-title">
                    <span class="ft-imagem-nome"></span>
                </div>
                <div class="ft-card-meta">
                    <div><strong>Finalizador:</strong> <span class="ft-finalizador"></span></div>
                    <div><strong>Prazo:</strong> <span class="ft-prazo"></span></div>
                    <div><strong>Status:</strong> <span class="ft-status"></span></div>
                    <div><strong>Obs.:</strong> <span class="ft-observacao"></span></div>
                </div>
            </article>
        </template>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script>
        (function() {
            const modal = document.getElementById('ft-entry-modal');
            if (!modal) return;
            const backdrop = modal.querySelector('.ft-modal-backdrop');
            const closeBtn = document.getElementById('ft-modal-close');
            const btns = modal.querySelectorAll('[data-target]');

            function close() {
                modal.style.display = 'none';
            }

            backdrop && backdrop.addEventListener('click', close);
            closeBtn && closeBtn.addEventListener('click', close);

            btns.forEach(b => {
                b.addEventListener('click', (ev) => {
                    const t = b.getAttribute('data-target');
                    if (t) window.location.href = t;
                });
            });

            // keep modal visible on first paint; focus first button for accessibility
            window.addEventListener('DOMContentLoaded', () => {
                const first = modal.querySelector('[data-target]');
                if (first) first.focus();
            });
        })();
    </script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
</body>

</html>