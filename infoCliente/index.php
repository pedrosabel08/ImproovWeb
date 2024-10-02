<?php

include 'conexao.php';

$sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
$result_cliente = $conn->query($sql_clientes);

$clientes = array();
if ($result_cliente->num_rows > 0) {
    while ($row = $result_cliente->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleEditClientes.css">
    <title>Document</title>

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">

</head>

<body>
    <div class="header">

        <div>
            <button id="voltar" onclick="window.location.href='../main.html'">Voltar</button>
        </div>
        <div>
            <img src="../assets/ImproovFlow - logo.png" alt="Improov" class="logo">
        </div>

    </div>

    <div class="container mt-5">
        <h2>Selecionar Cliente</h2>
        <select name="cliente" id="cliente" class="form-control" onchange="buscarCliente()">
            <option value="0">Selecione um cliente:</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                    <?= htmlspecialchars($cliente['nome_cliente']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="infoClienteModal" tabindex="-1" role="dialog" aria-labelledby="infoClienteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="infoClienteModalLabel">Informações do Cliente</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalBody">
                    <form id="formCliente">
                        <div class="form-group">
                            <label for="nomeCliente">Nome do Cliente</label>
                            <input type="text" class="form-control" id="nomeCliente" name="nome_cliente" required>
                        </div>
                        <div id="contatosContainer2">

                        </div>
                        <div id="contatosContainer" class="hidden">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="form-group">
                                <label for="nomeContato">Nome do Contato</label>
                                <input type="text" class="form-control" id="nome_contato" name="nome_contato">
                            </div>
                            <div class="form-group">
                                <label for="cargo">Cargo</label>
                                <input type="text" class="form-control" id="cargo" name="cargo">
                            </div>
                        </div>
                        <input type="text" name="idcliente" id="idcliente" hidden>
                        <input type="text" name="idcontato" id="idcontato" hidden>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="addContatoButton">Adicionar Contato</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                            <button type="submit">Salvar mudanças</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../script/scriptEditCliente.js"></script>

</body>

</html>