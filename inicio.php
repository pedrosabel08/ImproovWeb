<?php
session_start();

include 'conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];

$sql = "SELECT l.funcao_imagem_id, l.status_anterior, l.status_novo, l.data, i.imagem_nome, o.nome_obra
        FROM log_alteracoes l
        INNER JOIN funcao_imagem f ON f.idfuncao_imagem = l.funcao_imagem_id 
        INNER JOIN imagens_cliente_obra i ON f.imagem_id = i.idimagens_cliente_obra
        INNER JOIN obra o ON i.obra_id = o.idobra
        WHERE l.colaborador_id = ?
        ORDER BY l.data DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idusuario);
$stmt->execute();
$result = $stmt->get_result();

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
            <a href="#" id="tab-imagens" onclick="visualizarTabela()">Visualizar tabela com imagens</a>
            <a href="#" onclick="listaPos()">Lista Pós-Produção</a>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="#" onclick="clientes()">Informações clientes</a>
                <a href="#" onclick="acomp()">Acompanhamentos</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="#" onclick="animacao()">Lista Animação</a>
            <?php endif; ?>
            <a href="#" onclick="metas()">Metas e progresso</a>
            <a id="calendar" class="calendar-btn" href="#" onclick="calendar()">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>
        <h1>Página Inicial</h1>
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
                    <p><i class="fa-solid fa-check"></i>&nbsp;Tarefas concluídas</p>
                    <p id="count">0</p>
                </div>
                <div class="tasks-to-do">
                    <p><i class="fa-solid fa-xmark"></i>&nbsp;Tarefas para fazer</p>
                    <p id="count">0</p>
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