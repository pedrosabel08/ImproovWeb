<?php
session_start();

include 'conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];

$sql = "SELECT n.mensagem, op.prazo, nu.lida 
        FROM notificacoes n
        JOIN notificacoes_usuarios nu ON n.id = nu.notificacao_id
        LEFT JOIN obra_prazo op ON n.id = op.notificacoes_id
        WHERE nu.usuario_id = ?
        AND n.tipo_notificacao <> 'pos'
        AND op.prazo >= CURDATE() 
        ORDER BY op.prazo ASC;";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idusuario);
$stmt->execute();
$resultNotificacoes = $stmt->get_result();


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

$stmt->close();
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
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Improov+Flow</title>
</head>

<body>
    <header>
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div id="menu" class="hidden">
            <a href="inicio.php" id="tab-imagens">Página Principal</a>
            <a href="main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="Pos-Producao/index.php">Lista Pós-Produção</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="infoCliente/index.php">Informações clientes</a>
                <a href="Acompanhamento/index.php">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="Animacao/index.php">Lista Animação</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                <a href="Imagens/index.php">Lista Imagens</a>
                <a href="Pagamento/index.php">Pagamento</a>
                <a href="Obras/index.php">Obras</a>
            <?php endif; ?>

            <a href="Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>

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
    <main>
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
            <div class="button-container">
                <div class="icon">
                    <button class="main-button icon" id="toggleButton"><i class="fa-solid fa-gear" style="color: #f1f1f1"></i>
                    </button>
                    <div class="tooltip ferramenta">Ferramentas</div>
                </div>
                <div class="icon-container" id="iconContainer">
                    <div class="icon">
                        <button id="calendario">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <div class="tooltip">Calendário</div>
                    </div>
                    <div class="icon">
                        <button id="show-prioridade-btn">
                            <i class="fas fa-table"></i>
                        </button>
                        <div class="tooltip">Prioridades</div>
                    </div>
                    <div class="icon">
                        <button id="show-andamento-btn">
                            <i class="fas fa-tasks"></i>
                        </button>
                        <div class="tooltip">Em andamento</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="main-container">
            <div id="container-calendario" class="container active">
                <div>
                    <iframe src="https://calendar.google.com/calendar/embed?height=600&wkst=1&ctz=America%2FSao_Paulo&showPrint=0&showTz=0&showTitle=0&showCalendars=0&src=dHJhZmVnb2ltcHJvb3ZAZ21haWwuY29t&src=MjE2NGI4MjJkNzVlZDk2MzA2YzVjOTJhZjEyOWYzNjdlYjE5M2JmZDc4ZDljNDhjNjg4N2QyZmExNTk1M2I1OEBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&color=%23039BE5&color=%23E67C73" style="border:solid 1px #777" width="1200" height="600" frameborder="0" scrolling="no"></iframe>
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


    </main>

    <?php if (isset($_SESSION['idusuario']) && ($_SESSION['idusuario'] == 1 || $_SESSION['idusuario'] == 2 || $_SESSION['idusuario'] == 9)): ?>
        <div id="notificacao-sino" class="notificacao-sino">
            <i class="fas fa-bell sino" id="icone-sino"></i>
            <span id="contador-tarefas" class="contador-tarefas">0</span>
        </div>
    <?php endif; ?>

    <script>
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
    </script>

    <script src="script/notificacoes.js"></script>
    <script src="./script/scriptIndex.js"></script>
</body>

</html>