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

$sql = "SELECT l.funcao_imagem_id, l.status_anterior, l.status_novo, l.data, i.imagem_nome, o.nome_obra
        FROM log_alteracoes l
        INNER JOIN funcao_imagem f ON f.idfuncao_imagem = l.funcao_imagem_id 
        INNER JOIN imagens_cliente_obra i ON f.imagem_id = i.idimagens_cliente_obra
        INNER JOIN obra o ON i.obra_id = o.idobra
        WHERE l.colaborador_id = ?
        ORDER BY l.data DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idcolaborador);
$stmt->execute();
$result = $stmt->get_result();


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
            <a href="main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="Pos-Producao/index.php">Lista Pós-Produção</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="infoCliente/index.php">Informações clientes</a>
                <a href="Acompanhamento/index.html">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="Animacao/index.php">Lista Animação</a>
            <?php endif; ?>

            <a href="Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>
        <div class="right">
            <h1>Página Inicial</h1>
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
        </div>
        <div class="nav">
            <div>
                <iframe src="https://www.improov.com.br/sistema/Calendario/index.php"></iframe>
            </div>
            <div class="last-tasks">
                <h2>Últimas Tarefas</h2>
                <ul>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <li>
                                <span class="task-title"><?php echo "Status: " . $row['status_novo']; ?> - <?php echo "Imagem: " . $row['imagem_nome']; ?> - <?php echo "Obra: " . $row['nome_obra']; ?></span>
                                <span class="task-date"> - <?php echo date('d/m/Y', strtotime($row['data'])); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li>Não há tarefas recentes.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

    </main>

    <script>
        const nome_user = <?php echo json_encode($nome_usuario); ?>;
        const idusuario = <?php echo json_encode($idusuario); ?>;

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
    </script>

    <script src="./script/scriptIndex.js"></script>
</body>

</html>