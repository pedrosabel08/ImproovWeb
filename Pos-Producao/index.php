<?php
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

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

$sql_status = "SELECT idstatus, nome_status FROM status_imagem order by idstatus";
$result_status = $conn->query($sql_status);
$status_imagens = array();
if ($result_status->num_rows > 0) {
    while ($row = $result_status->fetch_assoc()) {
        $status_imagens[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stylePos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">

    <title>Lista Pós-Produção</title>
</head>

<body>
    <header>
        <button id="voltar" onclick="window.location.href='index.html'">Voltar</button>
        <h1>Lista Pós-Produção</h1>
        <button id="openModalBtn">Inserir render</button>
    </header>

    <div>
        <table>
            <thead>
                <tr>
                    <th>Nome Finalizador</th>
                    <th>Nome Cliente</th>
                    <th>Nome Obra</th>
                    <th>Data</th>
                    <th>Nome imagem</th>
                    <th>Caminho Pasta</th>
                    <th>Número BG</th>
                    <th>Referências/Caminho</th>
                    <th>Observação</th>
                    <th>Status</th>
                    <th>Revisão</th>
                </tr>
            </thead>
            <tbody id="lista-imagens">
            </tbody>
        </table>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="form-inserir">
                <h2>Formulário de Dados</h2>
                <form id="formPosProducao">
                    <div>
                        <label for="nomeFinalizador">Nome Finalizador</label>
                        <select name="final_id" id="opcao_finalizador">
                            <option value="">Selecione um colaborador:</option>
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeCliente">Nome Cliente</label>
                        <select name="cliente_id" id="opcao_cliente">
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                                    <?= htmlspecialchars($cliente['nome_cliente']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeObra">Nome Obra</label>
                        <select name="obra_id" id="opcao_obra" onchange="buscarImagens()">
                            <option value="">Selecione uma obra primeiro:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeImagem">Nome Imagem</label>
                        <select id="nomeImagem" name="imagem_id" required>
                            <option value="">Selecione uma obra primeiro</option>
                        </select>
                    </div>

                    <div>
                        <label for="caminhoPasta">Caminho Pasta</label>
                        <input type="text" id="caminhoPasta" name="caminho_pasta">
                    </div>

                    <div>
                        <label for="numeroBG">Número BG</label>
                        <input type="text" id="numeroBG" name="numero_bg">
                    </div>

                    <div>
                        <label for="referenciasCaminho">Referências/Caminho</label>
                        <input type="text" id="referenciasCaminho" name="refs">
                    </div>

                    <div>
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="obs" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="status">Revisão</label>
                        <select name="status_id" id="opcao_status">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status_pos">Status</label>
                        <input type="checkbox" name="status_pos" id="status_pos" disabled>
                    </div>

                    <div>
                        <button type="submit">Enviar</button>
                    </div>
                </form>
            </div>



            <script src="../script/scriptPos.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>

</html>