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
$sql_imagens = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra";
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
    <link rel="stylesheet" href="../css/styleArquitetura.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Lista Arquitetura</title>
</head>

<header>
    <button id="voltar" onclick="window.location.href='../inicio.php'">Voltar</button>
    <h1>Lista Arquitetura</h1>
    <div id="buttons">
        <button id="openFiltro">Inserir filtro de assets</button>
        <button id="openAcomp">Inserir acompanhamento</button>
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
                <th>Nome colaborador</th>
                <th>Nome cliente</th>
                <th>Nome obra</th>
                <th>Nome imagem</th>
                <th>Status</th>
                <th>Prazo</th>
            </tr>
        </thead>
        <tbody id="lista-imagens">
        </tbody>
    </table>
</div>

<div id="modalCaderno" class="modalCaderno">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="form-inserir">
            <h2>Formulário de Caderno</h2>
            <form id="formCaderno">
                <div>
                    <label for="nomeFinalizador">Nome Colaborador</label>
                    <select name="final_id" id="opcao_finalizador" required>
                        <option value="1">Nicolle</option>
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
                            <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                <?= htmlspecialchars($imagem['imagem_nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>

                <div>
                    <label for="prazo">Prazo</label>
                    <input type="date" name="prazo" id="prazo">
                </div>

                <input type="text" name="idfuncao_imagem" id="idfuncao_imagem" hidden>
                <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">

                <div>
                    <button type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalFiltro" class="modalFiltro">
    <div class="modal-content">
        <span class="close-filtro">&times;</span>
        <div id="form-inserir">
            <h2>Formulário de Filtro</h2>
            <form id="formFiltro">
                <div>
                    <label for="nomeFinalizador">Nome Colaborador</label>
                    <select name="final_id" id="opcao_finalizador" required>
                        <option value="1">Nicolle</option>
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
                            <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                <?= htmlspecialchars($imagem['imagem_nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>

                <div>
                    <label for="prazo">Prazo</label>
                    <input type="date" name="prazo" id="prazo">
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