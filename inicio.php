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
    <main>
        <header>

            <div class="right">
                <img src="gif/assinatura_branco.gif" alt="" style="width: 200px;">
                <button id="showMenu"><i class="fa-solid fa-user"></i></button>
                <div id="menu2" class="hidden">
                    <a href="infos.php" id="editProfile"><i class="fa-regular fa-user"></i>Editar Informações</a>
                    <hr>
                    <a href="index.html" id="logout"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
                </div>
            </div>
        </header>

        <div class="infos-pessoais">
            <div id="data"></div>
            <div>
                <p id="saudacao"></p>
                <span id="nome-user"></span>
            </div>
            <div class="tasks">
                <div class="tasks-check">
                    <p><i class="fa-solid fa-check"></i>&nbsp;&nbsp;Tarefas concluídas</p>
                    <p id="count-check"><?php echo $count_finalizadas; ?></p>
                </div>
                <div class="tasks-to-do">
                    <p><i class="fa-solid fa-xmark"></i>&nbsp;&nbsp;Tarefas para fazer</p>
                    <p id="count-to-do"><?php echo $count_pendentes; ?></p>
                </div>
            </div>
        </div>
        <div class="main-container">

            <div id="container-calendario" class="container active">
                <div>
                    <div class="calendario">
                        <div id="calendarFull"></div>
                    </div>
                </div>
                <!-- <div class="last-tasks">
                    <h2>Notificações</h2>
                    <ul>
                        <?php if ($resultNotificacoes->num_rows > 0): ?>
                            <?php while ($row = $resultNotificacoes->fetch_assoc()): ?>
                                <li>
                                    <span class="notification-message"><?php echo htmlspecialchars($row['mensagem']); ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li>Não há notificações recentes.</li>
                        <?php endif; ?>
                    </ul>
                </div> -->
            </div>

            <div id="container-andamento" class="container">
                <select id="colaboradorSelectAndamento">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="imagensColaboradorAndamento">

                </div>
            </div>

            <div id="priority-container" class="container">
                <h2>Gerenciar Prioridades</h2>
                <select id="colaboradorSelectPrioridade">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Áreas de prioridade -->
                <div id="priority-zones">
                    <div class="priority-group" id="alta-prioridade">
                        <h3>Alta Prioridade</h3>
                        <div class="drop-zone" data-priority="1"></div>
                    </div>
                    <div class="priority-group" id="media-prioridade">
                        <h3>Média Prioridade</h3>
                        <div class="drop-zone" data-priority="2"></div>
                    </div>
                    <div class="priority-group" id="baixa-prioridade">
                        <h3>Baixa Prioridade</h3>
                        <div class="drop-zone" data-priority="3"></div>
                    </div>
                </div>

                <div id="priorityDropZones">
                    <!-- As imagens serão exibidas aqui -->
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

    </main>

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
        <div class="modal-content" style="width:90vw;max-width: 70vw;height: 90vh;position:relative;">
            <!-- <button onclick="fecharModalIframe()" style="position:absolute;top:10px;right:10px;z-index:2;">Fechar</button> -->
            <iframe id="iframeChangelog" src="CHANGELOG/Flow/uploadArquivos.html" frameborder="0" style="width:100%;height:100%;border:none;"></iframe>
        </div>
    </div>

    <script>
        function abrirModalIframe() {
            document.getElementById('modalIframeChangelog').style.display = 'flex';
        }

        const modalIframeChangelog = document.getElementById('modalIframeChangelog');

        const CHANGELOG_VERSION = "3.2.20"; // Altere este valor sempre que atualizar o changelog

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

        const primeiro_nome = nome_user.split(" ")[0];


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
        saudacao.textContent = obterSaudacao() + ", " + primeiro_nome + "!";

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

    <script src="script/notificacoes.js"></script>
    <script src="script/scriptIndex.js"></script>
    <script src="./script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

</body>

</html>