<?php
// Arquivo: revisao.php
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['idusuario'])) {
    header("Location: ../index.html"); // Redireciona para a página de login se o usuário não estiver logado
    exit;
}

$idusuario = $_SESSION['idusuario'];

// Buscar as tarefas de revisão do banco de dados
if ($idusuario == 1) {
    $sql = "SELECT f.funcao_id, 
       fun.nome_funcao, 
       f.status, 
       f.check_funcao, 
       f.imagem_id, 
       i.imagem_nome, 
       f.colaborador_id, 
       c.nome_colaborador, 
       l.data
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
    $sql = "SELECT funcao_id, status, check_funcao, imagem_id, colaborador_id 
            FROM funcao_imagem 
            WHERE funcao_id = 4 AND check_funcao = 0 AND status = 'Finalizado'";
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
    <title>Revisão de Tarefas</title>
    <style>
        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .task-item {
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            background-color: #fff;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            cursor: pointer;
        }

        .task-item h3 {
            margin: 0;
        }

        .task-item p {
            margin: 5px 0;
        }

        .action-btn {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-align: center;
        }

        .action-btn:hover {
            background-color: #45a049;
        }

        .whatsapp-btn {
            padding: 8px 16px;
            background-color: #25D366;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-align: center;
        }

        .whatsapp-btn:hover {
            background-color: #128C7E;
        }

        .task-details {
            display: none;
            margin-top: 10px;
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
        }

        .task-item.open .task-details {
            display: block;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Tarefas de Revisão</h2>

        <?php if (count($tarefas) > 0): ?>
            <?php foreach ($tarefas as $tarefa): ?>
                <div class="task-item" onclick="toggleTaskDetails(this)">
                    <div>
                        <h3>Função: <?= $tarefa['nome_funcao'] ?></h3>
                        <p><strong>Status:</strong> <?= $tarefa['status'] ?></p>
                    </div>
                    <div>
                        <button class="action-btn" onclick="revisarTarefa(<?= $tarefa['imagem_id'] ?>)">Revisar</button>
                        <!-- <a href="https://wa.me/55<?= preg_replace('/\D/', '', $tarefa['telefone']) ?>" target="_blank"> -->
                            <button class="whatsapp-btn">WhatsApp</button>
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
            <p>Não há tarefas de revisão no momento.</p>
        <?php endif; ?>

    </div>

    <script>
        // Função para simular a revisão de uma tarefa
        function revisarTarefa(imagemId) {
            alert("Revisão iniciada para a Imagem ID: " + imagemId);
            // Aqui você pode adicionar lógica para marcar a tarefa como revisada no banco de dados,
            // ou redirecionar para uma página de revisão de detalhes
        }

        // Função para alternar a visibilidade da "gaveta" de detalhes da tarefa
        function toggleTaskDetails(taskElement) {
            taskElement.classList.toggle('open');
        }
    </script>

</body>

</html>