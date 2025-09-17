<?php
session_start();

include 'conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
$ultima_atividade = date('Y-m-d H:i:s');

$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = ?
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'ssi' indica os tipos: string, string, integer
$stmt2->bind_param("ssi", $tela_atual, $ultima_atividade, $idusuario);

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
include 'conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styleIndex.css">
    <link rel="stylesheet" href="css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.css"
        integrity="sha512-kJlvECunwXftkPwyvHbclArO8wszgBGisiLeuDFwNM8ws+wKIw0sv1os3ClWZOcrEB2eRXULYUsm8OVRGJKwGA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

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
                    <img src="gif/assinatura_preto.gif" alt="" style="width: 200px;">
                </div>
                <nav>
                    <div class="nav-left">
                        <button id="overview"><span>Overview</span></button>
                        <button id="kanban" class="active"><i class="ri-kanban-view"></i><span>Kanban</span></button>
                        <button id="activities"><i class="fa-solid fa-chart-line"><span></i>Activity</span></button>
                        <button id="timeline"><span>Timeline</span></button>
                    </div>
                    <div class="nav-right">
                        <button id="date"><i class="ri-calendar-todo-fill"></i><span></span></button>
                        <button id="filter"><i class="ri-equalizer-fill"></i><span>Filtros</span></button>
                        <button id="add-task"><i class="ri-add-line"></i></i><span>Adicionar tarefa</span></button>
                    </div>
                </nav>
            </header>
            <div class="kanban">
                <div class="kanban-box" id="to-do">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-play"></i><span>Não iniciado</span></div>
                        <span class="task-count"></span>
                        <!-- <i class="fa fa-ellipsis-v"></i> -->
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="in-progress">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-hourglass-start"></i><span>Em andamento</span></div>
                        <span class="task-count"></span>
                        <!-- <i class="fa fa-ellipsis-v"></i> -->
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="in-review">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-magnifying-glass"></i><span>Em aprovação</span></div>
                        <span class="task-count"></span>
                        <!-- <i class="fa fa-ellipsis-v"></i> -->
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="ajuste">
                    <div class="header">
                        <div class="title"><i class="ri-error-warning-line"></i><span>Em ajuste</span></div>
                        <span class="task-count"></span>
                        <!-- <i class="fa fa-ellipsis-v"></i> -->
                    </div>
                    <div class="content">
                    </div>
                </div>
                <div class="kanban-box" id="done">
                    <div class="header">
                        <div class="title"><i class="fa-solid fa-check"></i><span>Finalizado</span></div>
                        <span class="task-count"></span>
                        <!-- <i class="fa fa-ellipsis-v"></i> -->
                    </div>
                    <div class="content">
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="modal" id="task-modal">
        <div class="modal-content">
            <span class="close-button" id="close-modal">&times;</span>
            <h2>Adicionar tarefa</h2>
            <form id="task-form">
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
            <label for="modalPrazo">Prazo:</label>
            <input type="date" id="modalPrazo">

            <label for="modalObs">Observação:</label>
            <textarea id="modalObs" rows="4"></textarea>

            <div class="upload-wrapper">
                <input type="file" id="modalFile" class="file-input" />
                <label for="modalFile" class="file-label">
                    <i class="fa-solid fa-upload"></i> Escolher arquivo
                </label>
                <span id="fileName" class="file-name">Nenhum arquivo selecionado</span>
                <button onclick="enviarImagens()">Enviar Prévia</button>
            </div>

            <div class="buttons">
                <button id="salvarModal">Salvar</button>
                <button id="fecharModal">Fechar</button>
            </div>
        </div>
    </div>

    <div id="modalFilter" class="card-modal" style="width: 300px !important;">
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
            </div>
        </div>
    </div>

    <div class="modal" id="modal">
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

    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
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
            <h3>Evento</h3>
            <form id="eventForm">
                <input type="hidden" name="id" id="eventId">
                <label for="opcao">Obra:</label>
                <select name="opcao" id="obra_calendar">
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Título:</label>
                <input type="text" name="title" id="eventTitle" required>
                <label>Tipo de Evento:</label>
                <select name="eventType" id="eventType" required>
                    <option value="">Selecione</option>
                    <option value="Entrega">Entrega</option>
                    <option value="Arquivos">Arquivos</option>
                    <option value="Reunião">Reunião</option>
                    <option value="Outro">Outro</option>
                </select>
                <label>Data:</label>
                <input type="date" name="date" id="eventDate" required>
                <div class="buttons">
                    <button type="submit" style="background-color: green;">Salvar</button>
                    <button type="button" style="background-color: red;" onclick="deleteEvent()">Excluir</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para o iframe do changelog -->
    <div id="modalIframeChangelog" class="modal" style="display:none;">
        <div class="modal-content" style="width:90vw;max-width: 50vw;height: 40vh;position:relative;">
            <!-- <button onclick="fecharModalIframe()" style="position:absolute;top:10px;right:10px;z-index:2;">Fechar</button> -->
            <iframe id="iframeChangelog" src="CHANGELOG/Flow/suporte.html" frameborder="0" style="width:100%;height:100%;border:none;"></iframe>
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


        document.getElementById('modal').style.display = 'none';

        // checkDailyAccess agora retorna uma Promise
        function checkDailyAccess() {
            return new Promise((resolve, reject) => {
                fetch('verifica_respostas.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `idcolaborador=${idColaborador}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.hasResponses) {
                            // Se já respondeu, segue para checkRender
                            resolve();
                        } else {
                            // Se não respondeu, exibe modal e interrompe fluxo (não resolve ainda)
                            document.getElementById('modal').style.display = 'flex';
                            // Resolve apenas após o envio do formulário
                            document.getElementById('dailyForm').addEventListener('submit', function onSubmit(e) {
                                e.preventDefault();
                                this.removeEventListener('submit', onSubmit); // evita múltiplas submissões

                                const formData = new FormData(this);

                                fetch('submit_respostas.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            document.getElementById('modal').style.display = 'none';
                                            Swal.fire({
                                                icon: 'success',
                                                text: 'Respostas enviadas com sucesso!',
                                                showConfirmButton: false,
                                                timer: 2000
                                            }).then(() => resolve()); // continua depois de enviar
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                text: 'Erro ao enviar as tarefas, tente novamente!',
                                                showConfirmButton: false,
                                                timer: 2000
                                            });
                                            reject(); // interrompe a sequência
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Erro:', error);
                                        reject();
                                    });
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao verificar respostas:', error);
                        reject();
                    });
            });
        }

        // checkRenderItems também retorna uma Promise
        function checkRenderItems(idColaborador) {
            return new Promise((resolve, reject) => {
                fetch('verifica_render.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `idcolaborador=${idColaborador}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.total > 0) {
                            Swal.fire({
                                title: `Você tem ${data.total} item(ns) na sua lista de render!`,
                                text: "Deseja ver agora ou depois?",
                                icon: "info",
                                showCancelButton: true,
                                confirmButtonText: "Ver agora",
                                cancelButtonText: "Ver depois",
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "./Render/";
                                } else {
                                    resolve(); // segue o fluxo
                                }
                            });
                        } else {
                            resolve(); // segue o fluxo mesmo sem render
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao verificar itens de render:', error);
                        reject();
                    });
            });
        }


        // 🚀 Dispara tudo ao carregar a página
        checkDailyAccess()
            .then(() => checkRenderItems(idColaborador))
            .then(() => {
                buscarTarefas();
                mostrarChangelogSeNecessario(); // Só mostra se não viu esta versão
            })
            .catch(() => {
                console.log('Fluxo interrompido devido a erro ou resposta incompleta.');
            });
    </script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="script/notificacoes.js"></script>
    <script src="script/scriptIndex.js"></script>
    <script src="./script/sidebar.js"></script>

</body>

</html>