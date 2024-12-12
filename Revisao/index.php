<?php
// Arquivo: revisao.php
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];

// Buscar as tarefas de revisão do banco de dados
if ($idusuario == 1) {
    $sql = "SELECT 
        f.idfuncao_imagem,
        f.funcao_id, 
       fun.nome_funcao, 
       f.status, 
       f.check_funcao, 
       f.imagem_id, 
       i.imagem_nome, 
       f.colaborador_id, 
       c.nome_colaborador, 
       l.data,
       c.telefone
FROM funcao_imagem f
LEFT JOIN log_alteracoes l ON f.idfuncao_imagem = l.funcao_imagem_id
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.funcao_id BETWEEN 2 AND 3 
  AND f.check_funcao = 0 
  AND l.status_novo = 'Finalizado'
ORDER BY l.data DESC";
} elseif ($idusuario == 2) {
    $sql = "SELECT 
        f.idfuncao_imagem,
        f.funcao_id, 
       fun.nome_funcao, 
       f.status, 
       f.check_funcao, 
       f.imagem_id, 
       i.imagem_nome, 
       f.colaborador_id, 
       c.nome_colaborador, 
       l.data,
       c.telefone
FROM funcao_imagem f
LEFT JOIN log_alteracoes l ON f.idfuncao_imagem = l.funcao_imagem_id
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.funcao_id = 4 
  AND f.check_funcao = 0 
  AND l.status_novo = 'Em aprovação'
ORDER BY l.data DESC";
}


$result = $conn->query($sql);

$tarefas = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tarefas[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Revisão de Tarefas</title>
</head>

<body>
    <header>
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div id="menu" class="hidden">
            <a href="../inicio.php" id="tab-imagens">Página Principal</a>
            <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>
            <a href="../Render/index.php">Lista Render</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="../infoCliente/index.php">Informações clientes</a>
                <a href="../Acompanhamento/index.php">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="../Animacao/index.php">Lista Animação</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                <a href="../Imagens/index.php">Lista Imagens</a>
                <a href="../Pagamento/index.php">Pagamento</a>
                <a href="../Obras/index.php">Obras</a>
            <?php endif; ?>

            <a href="../Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>

        <img src="../gif/assinatura_preto.gif" alt="Logo Improov + Flow" style="width: 150px;">

    </header>

    <div class="container">
        <h2>Tarefas de Revisão</h2>

        <?php if (count($tarefas) > 0): ?>
            <?php foreach ($tarefas as $tarefa): ?>
                <div class="task-item" onclick="toggleTaskDetails(this)">
                    <div class="task-info">
                        <h3><?= $tarefa['nome_funcao'] ?></h3><span><?= $tarefa['nome_colaborador'] ?></span>
                        <p> <?= $tarefa['imagem_nome'] ?></p>
                    </div>
                    <div class="task-actions">
                        <button class="action-btn" onclick="revisarTarefa(<?= $tarefa['idfuncao_imagem'] ?>)"><i class="fa-solid fa-check"></i></button>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $tarefa['telefone']) ?>?text=Olá, tenho uma dúvida sobre a tarefa. Poderia me ajudar?" target="_blank">
                            <button class="whatsapp-btn"><i class="fa-brands fa-whatsapp"></i></button>
                        </a>

                    </div>
                    <div class="task-details">
                        <p><strong>Imagem:</strong> <?= $tarefa['imagem_nome'] ?></p>
                        <p><strong>Colaborador:</strong> <?= $tarefa['nome_colaborador'] ?></p>
                        <p><strong>Data de Alteração:</strong> <?= $tarefa['data'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>
        <?php endif; ?>
    </div>

    <script src="script.js"></script>
</body>

</html>