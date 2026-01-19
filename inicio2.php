<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// Prevent caching of user-specific pages (helps avoid reverse-proxy serving other's HTML)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
header('Vary: Cookie');

// Harden session settings
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
// If using HTTPS in production, enable the secure flag:
// ini_set('session.cookie_secure', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}

$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];


$sql_finalizadas = "SELECT COUNT(*) as count_finalizadas FROM funcao_imagem WHERE status = 'Finalizado' AND colaborador_id = ?";
$stmt_finalizadas = $conn->prepare($sql_finalizadas);
$stmt_finalizadas->bind_param("i", $idcolaborador);
$stmt_finalizadas->execute();
$result_finalizadas = $stmt_finalizadas->get_result();
$row_finalizadas = $result_finalizadas->fetch_assoc();
$count_finalizadas = $row_finalizadas['count_finalizadas'];

// Consulta para contar as tarefas pendentes
$sql_pendentes = "SELECT COUNT(*) as count_pendentes FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ?";
$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $idcolaborador);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
$row_pendentes = $result_pendentes->fetch_assoc();
$count_pendentes = $row_pendentes['count_pendentes'];

$conn->close();
?>


<?php

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('PaginaPrincipal/styleIndex.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/styleSidebar.css'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <title>Improov+Flow</title>
</head>

<body>

    <?php

    include 'sidebar.php';

    ?>
    <div class="container">
        <main>
            <header>
                <div class="top">
                    <h3 id="saudacao"></h3>
                    <img id="gif" src="gif/assinatura_preto.gif" alt="Assinatura" style="width: 200px;">
                </div>
                <nav>
                    <div class="nav-left">
                        <button id="overviewBtn"><i class="ri-dashboard-line"></i><span>Overview</span></button>
                        <button id="kanbanBtn" class="active"><i class="ri-kanban-view"></i><span>Kanban</span></button>
                        <!-- <button id="activities"><i class="fa-solid fa-chart-line"><span></i>Activity</span></button> -->
                        <!-- <button id="timeline"><span>Timeline</span></button> -->
                    </div>
                    <div class="nav-right">
                        <!-- Mini calendar (semana) -->

                        <div id="mini-calendar-container" style="display:inline-block; vertical-align: middle; margin-right:8px;">
                            <div id="mini-calendar" style="width:350px; height:80px;"></div>
                        </div>
                        <select name="idcolab" id="idcolab">

                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="date"><i class="ri-calendar-todo-fill"></i><span></span></button>
                        <button id="filter"><i class="ri-equalizer-fill"></i><span>Filtros</span></button>
                        <button id="add-task"><i class="ri-add-line"></i></i><span>Adicionar tarefa</span></button>
                    </div>
                </nav>
            </header>
            <div class="kanban" id="kanban-section">
                <div class="kanban-box" id="to-do">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-play"></i><span>Não iniciado</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="hold">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-play"></i><span>HOLD</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="in-progress">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-hourglass-start"></i><span>Em andamento</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="in-review">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-magnifying-glass"></i><span>Em aprovação</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="ajuste">
                    <div class="header">
                        <div class="title"><i class="ri-error-warning-line"></i><span>Em ajuste</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="done">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-check"></i><span>Finalizado</span></div>
                        <span class="task-count"></span>
                    </div>
                    <div class="content">
                    </div>
                </div>

            </div>
            <!-- Overview embed container (hidden by default) -->
            <div id="overview-section" style="display:none; width:100%; height: calc(100vh - 140px);">
                <iframe id="overview-iframe" src="PaginaPrincipal/Overview/index.php" frameborder="0" style="width:100%; height:100%;"></iframe>
            </div>
        </main>
    </div>

    <div class="modal" id="task-modal">
        <div class="modal-content">
            <span class="close-button" id="close-modal">&times;</span>
            <h2>Adicionar tarefa</h2>
            <form id="task-form">
                <div class="task-type">
                    <label for="task-colab">Colaborador:</label>
                    <select name="task-colab" id="task-colab">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="task-type">
                    <label for="task-title">Título:</label>
                    <input type="text" id="task-title" name="task-title" required>
                </div>

                <div class="task-type">
                    <label for="task-desc">Descrição:</label>
                    <textarea id="task-desc" name="task-desc" required></textarea>
                </div>

                <div class="task-type">
                    <label for="task-prioridade">Prioridade:</label>
                    <select id="task-prioridade" name="task-prioridade" required>
                        <option value="alta">Alta</option>
                        <option value="media">Média</option>
                        <option value="baixa">Baixa</option>
                    </select>
                </div>

                <div class="task-type">
                    <label for="task-prazo-date">Prazo:</label>
                    <input type="date" id="task-prazo-date" name="task-prazo-date" required>
                </div>

                <button type="submit">Adicionar Tarefa</button>
            </form>
        </div>
    </div>

    <div id="cardModal" class="card-modal">
        <div class="modal-content">

            <h3>Editar Card</h3>
            <div class="modal-item modalPrazo">
                <h4>Prazo de entrega:</h4>
                <input type="date" id="modalPrazo">
            </div>

            <div class="modal-item modalObs">
                <h4>Observação:</h4>
                <textarea id="modalObs" rows="4"></textarea>
            </div>

            <div class="modal-item statusAnterior">
                <h4>A função anterior está <span class="aprovadaComAjustes">Aprovada com ajustes</span>, verifique no Flow Review!</h4>
            </div>
            <div class="modal-item modalUploads">

                <div id="etapaPrevia">
                    <h4>Prévias</h4>
                    <div id="drop-area-previa" class="drop-area">
                        Arraste suas imagens aqui ou clique para selecionar
                        <input type="file" id="fileElemPrevia" accept="image/*" multiple style="display:none;" required>
                    </div>
                    <ul class="file-list" id="fileListPrevia"></ul>
                    <div class="buttons-upload" id="addEnviarPrevia">
                        <button onclick="enviarImagens()" style="background-color: green;">Enviar Prévia</button>
                    </div>
                </div>

                <!-- Conteúdo da etapa 2 -->
                <div id="etapaFinal">
                    <h4>Arquivo</h4>
                    <div id="drop-area-final" class="drop-area">
                        Arraste o arquivo final aqui ou clique para selecionar
                        <input type="file" id="fileElemFinal" multiple style="display:none;">
                    </div>
                    <ul class="file-list" id="fileListFinal"></ul>
                    <div class="buttons-upload" id="addEnviarArquivo">
                        <button onclick="enviarArquivo()" style="background-color: green;">Enviar Arquivo Final</button>
                    </div>
                </div>
            </div>


            <div class="buttons">
                <button id="salvarModal">Salvar</button>
                <button id="fecharModal">Fechar</button>
            </div>
        </div>
    </div>

    <div id="modalFilter" class="" style="width: 300px !important;">
        <div class="modal-content">
            <h3>Filtros</h3>
            <!-- Filtros -->
            <div id="filtros">
                <div class="dropdown">
                    <button class="dropbtn">🏢 Obras</button>
                    <div class="dropdown-content" id="filtroObra"></div>
                </div>

                <div class="dropdown">
                    <button class="dropbtn">💼 Funções</button>
                    <div class="dropdown-content" id="filtroFuncao"></div>
                </div>

                <div class="dropdown">
                    <button class="dropbtn">✅ Status</button>
                    <div class="dropdown-content" id="filtroStatus">
                        <label><input type="checkbox" value=""> Todos os status</label>
                        <label><input type="checkbox" value="Não iniciado"> Não iniciado</label>
                        <label><input type="checkbox" value="Em andamento"> Em andamento</label>
                        <label><input type="checkbox" value="Em aprovação"> Em aprovação</label>
                        <label><input type="checkbox" value="Finalizado"> Finalizado</label>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="dropbtn">📅 Prazo</button>
                    <div class="dropdown-content" id="filtroPrazo">
                        <input id="prazoRange" type="text" placeholder="Selecione o intervalo de datas" readonly>
                        <button type="button" id="resetPrazo">❌</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal" id="modalDaily">
        <div class="modal-content" style="width: 500px;">
            <h1>Daily meet Assíncrono</h1>
            <form id="dailyForm">
                <label for="finalizado">✅ O que finalizei ontem?</label>
                <textarea id="finalizado" name="finalizado" required></textarea>

                <label for="hoje">⏳ O que vou fazer hoje?</label>
                <textarea id="hoje" name="hoje" required></textarea>

                <label for="bloqueio">🚧 Algum bloqueio ou dúvida?</label>
                <textarea id="bloqueio" name="bloqueio" required></textarea>

                <button type="submit">Enviar respostas</button>
            </form>
        </div>
    </div>

    <!-- Painel 'O que fazer agora' (mostra uma vez por dia) -->
    <div class="modal" id="dailyPanelModal">
        <div class="modal-content">
            <h2>O que fazer agora?</h2>
            <p>Resumo rápido das suas prioridades para hoje.</p>

            <div class="panel-row">
                <div class="panel-card">
                    <strong id="daily_renders">0</strong>
                    <div>Renders em aprovação</div>
                </div>
                <div class="panel-card">
                    <strong id="daily_ajustes">0</strong>
                    <div>Tarefas em <span>ajuste</span></div>
                </div>
                <div class="panel-card">
                    <strong id="daily_atrasadas">0</strong>
                    <div>Tarefas <span>atrasadas</span></div>
                </div>
                <div class="panel-card">
                    <strong id="daily_hoje">0</strong>
                    <div>Tarefas para <span class="">hoje</span></div>
                </div>
            </div>

            <h3 class="panel-heading">Últimas telas visitadas</h3>
            <ul id="daily_recent_pages"></ul>

            <div class="panel-actions">
                <button id="daily_go_tasks" class="btn">Ir para Minhas Tarefas</button>
            </div>
        </div>
    </div>

    <!-- Resumo inteligente modal (após Daily) -->
    <div class="modal" id="resumoModal" style="display:none;">
        <div class="modal-content" style="width:600px;">
            <h2>Resumo inteligente</h2>
            <div id="resumo-content">
                <!-- Conteúdo preenchido dinamicamente -->
                <p>Carregando resumo...</p>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                <button id="resumo-overview" class="btn">Ir para Overview</button>
                <button id="resumo-kanban" class="btn">Ir para Kanban</button>
                <button id="resumo-close" class="btn">Fechar</button>
            </div>
        </div>
    </div>

    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
    </div>

    <!-- Modal para calendário full (expand) -->
    <div id="calendarFullModal" class="modal" style="display:none;">
        <div class="modal-content" style="width:90vw; max-width:1100px; height:80vh; padding:12px;">
            <div id="calendarFull" style="width:100%; height:100%;"></div>
            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                <button id="closeFullCalendar" class="btn" style="background:#ef4444;color:#fff;border:none;padding:6px 12px;border-radius:6px;">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Popover unificado -->
    <div id="popover-tarefas" class="popover oculto">
        <!-- Tarefas -->
        <div class="secao">
            <div class="secao-titulo secao-tarefas" onclick="toggleSecao('tarefas')">
                <strong>Tarefas</strong>
                <span id="badge-tarefas" class="badge-interna"></span>
            </div>
            <div id="conteudo-tarefas" class="secao-conteudo"></div>
        </div>

        <!-- Notificações -->
        <div class="secao">
            <div class="secao-titulo secao-notificacoes">
                <strong>Notificações</strong>
                <span id="badge-notificacoes" class="badge-interna"></span>
            </div>
            <div id="conteudo-notificacoes" class="secao-conteudo">
            </div>
        </div>
        <button id="btn-ir-revisao">Ir para Revisão</button>
    </div>


    <!-- Modal simples para adicionar evento -->
    <div id="eventModal">
        <div class="eventos">

            <!-- Detail view: shown when clicking an existing event -->
            <div id="eventDetail">
                <p><strong>Nome da função:</strong> <span id="detailNomeFuncao">-</span></p>
                <p><strong>Nome da imagem:</strong> <span id="detailNomeImagem">-</span></p>
                <p><strong>Status:</strong> <span id="detailStatus">-</span></p>
                <p><strong>Prazo:</strong> <span id="detailPrazo">-</span></p>
            </div>
        </div>
    </div>

    <!-- Modal para o iframe do changelog -->
    <div id="modalIframeChangelog" class="modal" style="display:none;">
        <div class="modal-content" style="width:90vw;max-width: 50vw;height: 40vh;position:relative;">
            <!-- <button onclick="fecharModalIframe()" style="position:absolute;top:10px;right:10px;z-index:2;">Fechar</button> -->
            <iframe id="iframeChangelog" src="CHANGELOG/Flow/suporte.html" frameborder="0" style="width:100%;height:100%;border:none;"></iframe>
        </div>
    </div>

    <div id="loading" style="display:none; position:fixed; top:50%; left:50%;
 transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,.3);">
        <i class="ri-loader-4-line ri-spin"></i> Carregando...
    </div>

    <div class="sidebar-right" id="sidebar-right">
        <div class="sidebar-header">
            <h2>Detalhes da Tarefa</h2>
            <span class="close-button" id="close-sidebar">&times;</span>
        </div>
        <div class="sidebar-content" id="sidebar-content">
            <!-- Conteúdo dinâmico será carregado aqui -->
        </div>
    </div>


    <script>
        function abrirModalIframe() {
            document.getElementById('modalIframeChangelog').style.display = 'flex';
        }

        const modalIframeChangelog = document.getElementById('modalIframeChangelog');

        const CHANGELOG_VERSION = "3.2.3"; // Altere este valor sempre que atualizar o changelog

        function mostrarChangelogSeNecessario() {
            const chave = "changelog_visto_" + CHANGELOG_VERSION;
            if (!localStorage.getItem(chave)) {
                abrirModalIframe();
                localStorage.setItem(chave, "1");
            }
        }

        ['click', 'touchstart', 'keydown'].forEach(eventType => {
            window.addEventListener(eventType, function(event) {
                // Fecha os modais ao clicar fora ou pressionar Esc
                if (eventType === 'keydown' && event.key !== 'Escape') return;

                if (event.target == modalIframeChangelog || (eventType === 'keydown' && event.key === 'Escape')) {
                    modalIframeChangelog.style.display = 'none';
                }
            })
        });

        const nome_user = <?php echo json_encode($nome_usuario); ?>;

        function obterSaudacao() {
            const agora = new Date();
            const hora = agora.getHours();

            if (hora < 12) {
                return "Bom dia";
            } else if (hora < 18) {
                return "Boa tarde";
            } else {
                return "Boa noite";
            }
        }

        const saudacao = document.getElementById('saudacao');
        saudacao.textContent = obterSaudacao() + ", " + nome_user + "!";

        const idUsuario = <?php echo json_encode($idusuario); ?>;
        localStorage.setItem('idusuario', idUsuario);

        const idColaborador = <?php echo json_encode($idcolaborador); ?>;
        localStorage.setItem('idcolaborador', idColaborador);
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="<?php echo asset_url('script/notificacoes.js'); ?>"></script>
    <script src="<?php echo asset_url('PaginaPrincipal/scriptIndex.js'); ?>"></script>
    <script src="<?php echo asset_url('./script/sidebar.js'); ?>"></script>
    <script>
        // Toggle between Kanban and Overview (embedded iframe)
        (function() {
            const btnOverview = document.getElementById('overviewBtn');
            const btnKanban = document.getElementById('kanbanBtn');
            const kanbanSec = document.getElementById('kanban-section');
            const overviewSec = document.getElementById('overview-section');

            function showOverview() {
                kanbanSec.style.display = 'none';
                overviewSec.style.display = 'block';
                btnOverview.classList.add('active');
                btnKanban.classList.remove('active');
            }

            function showKanban() {
                overviewSec.style.display = 'none';
                kanbanSec.style.display = 'grid';
                btnKanban.classList.add('active');
                btnOverview.classList.remove('active');
            }

            btnOverview.addEventListener('click', showOverview);
            btnKanban.addEventListener('click', showKanban);
        })();
    </script>

</body>

</html>