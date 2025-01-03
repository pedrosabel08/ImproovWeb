<?php

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
$result_cliente = $conn->query($sql_clientes);
$clientes = array();
if ($result_cliente->num_rows > 0) {
    while ($row = $result_cliente->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$sql_obras = "SELECT idobra, nome_obra FROM obra";
$result_obra = $conn->query($sql_obras);
$obras = array();
if ($result_obra->num_rows > 0) {
    while ($row = $result_obra->fetch_assoc()) {
        $obras[] = $row;
    }
}

$sql_colaboradores = "SELECT idcolaborador, nome_colaborador FROM colaborador order by nome_colaborador";
$result_colaboradores = $conn->query($sql_colaboradores);
$colaboradores = array();
if ($result_colaboradores->num_rows > 0) {
    while ($row = $result_colaboradores->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

$sql_status = "SELECT idstatus, nome_status 
               FROM status_imagem 
               ORDER BY 
                   CASE 
                       WHEN nome_status = 'Sem status' THEN 0 
                       ELSE 1 
                   END, 
                   idstatus";

$result_status = $conn->query($sql_status);
$status_imagens = array();
if ($result_status->num_rows > 0) {
    while ($row = $result_status->fetch_assoc()) {
        $status_imagens[] = $row;
    }
}
$sql_imagens = "SELECT idimagem_animacao, imagem_nome FROM imagem_animacao";
$result_imagens = $conn->query($sql_imagens);
$imagens = array();
if ($result_imagens->num_rows > 0) {
    while ($row = $result_imagens->fetch_assoc()) {
        $imagens[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleAnimacao.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <title>Lista Animação</title>
</head>

<header>
    <button id="menuButton">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div id="menu" class="hidden">
        <a href="../inicio.php" id="tab-imagens">Página Principal</a>
        <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
        <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
            <a href="../infoCliente/index.php">Informações clientes</a>
            <a href="../Acompanhamento/index.php">Acompanhamentos</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
            <a href="Animacao/index.php">Lista Animação</a>
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
    <h1>Lista Animação</h1>
    <div id="buttons">
        <button id="add_imagem">Inserir Imagem</button>
        <button id="openModalBtn">Inserir animação</button>
    </div>
</header>

<div id="filtro">
    <label for="colunaFiltro">Filtrar por:</label>
    <select id="colunaFiltro">
        <option value="0">Nome Imagem</option>
        <option value="1">Cena</option>
        <option value="2">Render</option>
        <option value="3">Pós</option>
        <option value="4">Duração</option>
    </select>

    <input type="text" id="filtro-input" placeholder="Digite para filtrar" onkeyup="filtrarTabela()">

    <table>
        <thead>
            <tr>
                <th>Nome cliente</th>
                <th>Nome obra</th>
                <th>Nome imagem</th>
                <th>Status Animação</th>
                <th>Cena</th>
                <th>Prazo</th>
                <th>Render</th>
                <th>Prazo</th>
                <th>Pós</th>
                <th>Prazo</th>
                <th>Duração (em segundos)</th>
            </tr>
        </thead>
        <tbody id="lista-imagens">
        </tbody>
    </table>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <button id="deleteButton">Excluir</button>
        <div id="form-inserir">
            <h2>Formulário de Dados</h2>
            <form id="formAnimacao">
                <div>
                    <label for="nomeFinalizador">Nome Finalizador</label>
                    <select name="final_id" id="opcao_finalizador" required>
                        <option value="13">André Tavares</option>
                    </select>
                </div>

                <div>
                    <label for="nomeCliente">Nome Cliente</label>
                    <select name="cliente_id" id="opcao_cliente" required>
                        <option value="0">Selecione um cliente:</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                                <?= htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="nomeObra">Nome Obra</label>
                    <select name="obra_id" id="opcao_obra" onchange="buscarImagens()" required>
                        <option value="0">Selecione uma obra:</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="imagem_id">Nome Imagem</label>
                    <select id="imagem_id" name="imagem_id" required>
                        <option value="">Selecione uma imagem:</option>
                        <?php foreach ($imagens as $imagem): ?>
                            <option value="<?= $imagem['idimagem_animacao']; ?>">
                                <?= htmlspecialchars($imagem['imagem_nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <h3 for="">Cena</h3>
                    <select name="status_cena" id="status_cena">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                    <input type="date" name="prazo_cena" id="prazo_cena">
                </div>

                <div>
                    <h3 for="">Render</h3>
                    <select name="status_render" id="status_render">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                    <input type="date" name="prazo_render" id="prazo_render">
                </div>
                <div>
                    <h3 for="">Pós</h3>
                    <select name="status_pos" id="status_pos">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                    <input type="date" name="prazo_pos" id="prazo_pos">
                </div>

                <div>
                    <label for="duracao">Duração (em segundos)</label>
                    <input type="text" id="duracao" name="duracao" step="1">
                </div>

                <input type="text" name="idanimacao" id="idanimacao" hidden>
                <input type="text" name="idpos" id="idpos" hidden>
                <input type="text" name="idcena" id="idcena" hidden>
                <input type="text" name="idrender" id="idrender" hidden>
                <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">

                <div>
                    <label for="status_anima">Status</label>
                    <select name="status_anima" id="status_anima">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>

                <div>
                    <button type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal_imagem" id="modal_imagem">
    <div class="modal-content">
        <span class="close_imagem">&times;</span>
        <div id="form-inserir-imagem">
            <h2>Adicionar imagem</h2>
            <form id="formImagemAnimacao">

                <div>
                    <label for="nomeObra">Nome Obra</label>
                    <select name="obra_id" id="opcao_obra2" required>
                        <option value="0">Selecione uma obra:</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="">Nome da Imagem:</label>
                    <input type="text" name="imagem_nome" id="imagem_nome">
                </div>

                <div>
                    <button type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="script.js"></script>

</body>

</html>